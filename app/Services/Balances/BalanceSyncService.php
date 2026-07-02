<?php

namespace App\Services\Balances;

use App\Models\BalanceSyncItem;
use App\Models\BalanceSyncRun;
use Illuminate\Support\Carbon;

/**
 * Orquesta la conciliación de saldos SAP -> Humand.
 *
 * Por cada empleado de Organigrama:
 *   1. Determina tipo (fc / dc / supervisor) e employeeInternalId.
 *   2. Baja saldos de SAP (una llamada por empleado).
 *   3. Lee saldos Humand (una llamada por empleado, todas las políticas).
 *   4. Si difiere (> tolerancia), aplica SET (o lo simula en dry-run).
 *   5. Registra cada resultado en balance_sync_items.
 */
class BalanceSyncService
{
    public function __construct(
        private OrganigramaBalanceRepository $organigrama,
        private SapBalanceClient $sap,
        private HumandBalanceClient $humand,
    ) {
    }

    /**
     * @param array<int, string> $codigos Lista de CodigoCol; vacío = toda la población.
     */
    public function run(bool $dryRun, array $codigos = [], ?string $triggeredBy = null): BalanceSyncRun
    {
        $this->failStaleRuns();

        $tolerance = (float) config('balance_sync.tolerance', 0.01);
        $fecha     = $this->sapFecha();
        $codigos   = array_values(array_filter(array_map('trim', $codigos), fn ($c) => $c !== ''));

        $run = BalanceSyncRun::create([
            'dry_run'      => $dryRun,
            'status'       => 'running',
            'triggered_by' => $triggeredBy,
            'scope'        => $codigos !== [] ? 'codigo:' . implode(',', $codigos) : 'all',
            'note'         => "fecha SAP: {$fecha}",
            'started_at'   => now(),
        ]);

        $counters = $this->emptyCounters();

        try {
            $employees = $this->organigrama->employees($codigos);

            foreach ($employees as $emp) {
                $counters['users']++;
                $this->processEmployee($run->id, $emp, $fecha, $dryRun, $tolerance, $counters);
            }

            $this->finalizeRun($run, $counters, 'done');
        } catch (\Throwable $e) {
            $this->finalizeRun($run, $counters, 'failed', $e->getMessage());
            throw $e;
        }

        return $run->fresh();
    }

    public function startBulkRun(bool $dryRun, ?string $triggeredBy): BalanceSyncRun
    {
        $this->failStaleRuns();
        $this->assertNoRunningBulk();

        $fecha = $this->sapFecha();
        $total = $this->organigrama->countEmployees();

        return BalanceSyncRun::create([
            'dry_run'      => $dryRun,
            'status'       => 'running',
            'triggered_by' => $triggeredBy,
            'scope'        => "all:{$total}",
            'note'         => "fecha SAP: {$fecha} | modo: background",
            'started_at'   => now(),
        ]);
    }

    public function runBulkWorker(int $runId): BalanceSyncRun
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }

        $run = BalanceSyncRun::query()->findOrFail($runId);

        if ($run->status !== 'running') {
            return $run;
        }

        $dryRun    = (bool) $run->dry_run;
        $batchSize = max(1, (int) config('balance_sync.batch_size', 50));
        $offset    = (int) $run->total_users;

        try {
            while (true) {
                $result = $this->processBulkBatch($runId, $offset, $batchSize, $dryRun);

                if ($result['done']) {
                    return $result['run'];
                }

                $offset = $result['offset'];
            }
        } catch (\Throwable $e) {
            $run->update([
                'status'      => 'failed',
                'note'        => trim((string) $run->note) . ' | ' . $e->getMessage(),
                'finished_at' => now(),
            ]);

            throw $e;
        }
    }

    /**
     * @return array{run_id: int, status: string, offset: int, total: int, done: bool, message: string}
     */
    public function runStatus(int $runId): array
    {
        $run   = BalanceSyncRun::query()->findOrFail($runId);
        $total = $this->bulkTotalFromScope((string) $run->scope);
        $done  = in_array($run->status, ['done', 'failed'], true);

        $modo = $run->dry_run ? 'Simulación' : 'Ajustes aplicados';
        $msg  = $done
            ? "{$modo} (run #{$run->id}): {$run->total_items} ítems · {$run->applied} a ajustar/aplicados · {$run->unchanged} sin cambio · {$run->skipped} omitidos · {$run->errors} errores."
            : "Procesando {$run->total_users} de {$total} personas…";

        if ($run->status === 'failed') {
            $msg = 'Sincronización falló: ' . (string) $run->note;
        }

        return [
            'run_id'  => $run->id,
            'status'  => $run->status,
            'offset'  => (int) $run->total_users,
            'total'   => $total,
            'done'    => $done,
            'message' => $msg,
        ];
    }

    public function assertNoRunningBulk(): void
    {
        $running = BalanceSyncRun::query()
            ->where('status', 'running')
            ->where('scope', 'like', 'all:%')
            ->exists();

        if ($running) {
            throw new \RuntimeException('Ya hay una sincronización masiva en curso. Espera a que termine o revisa el log del worker.');
        }
    }

    /**
     * @return array{done: bool, offset: int, total: int, run: BalanceSyncRun}
     */
    public function processBulkBatch(int $runId, int $offset, int $limit, bool $dryRun): array
    {
        $run = BalanceSyncRun::query()->findOrFail($runId);

        if ($run->status !== 'running') {
            throw new \RuntimeException("El run #{$runId} ya no está en ejecución (estado: {$run->status}).");
        }

        $total = $this->bulkTotalFromScope((string) $run->scope);
        if ($total <= 0) {
            $total = $this->organigrama->countEmployees();
        }

        $fecha     = $this->sapFechaFromNote((string) $run->note);
        $tolerance = (float) config('balance_sync.tolerance', 0.01);
        $limit     = max(1, $limit);
        $employees = $this->organigrama->employees([], $offset, $limit);
        $counters  = $this->emptyCounters();

        foreach ($employees as $emp) {
            $counters['users']++;
            $this->processEmployee($run->id, $emp, $fecha, $dryRun, $tolerance, $counters);
        }

        $run->increment('total_users', $counters['users']);
        $run->increment('total_items', $counters['items']);
        $run->increment('applied', $counters['applied']);
        $run->increment('unchanged', $counters['unchanged']);
        $run->increment('skipped', $counters['skipped']);
        $run->increment('errors', $counters['errors']);

        $nextOffset = $offset + count($employees);
        $done       = $nextOffset >= $total || $employees === [];

        if ($done) {
            $run->update([
                'status'      => 'done',
                'finished_at' => now(),
            ]);
        }

        return [
            'done'   => $done,
            'offset' => $nextOffset,
            'total'  => $total,
            'run'    => $run->fresh(),
        ];
    }

    public function failStaleRuns(?int $minutes = null): void
    {
        $minutes = $minutes ?? (int) config('balance_sync.stale_run_minutes', 240);

        $stuck = BalanceSyncRun::query()
            ->where('status', 'running')
            ->where('scope', 'like', 'all:%')
            ->where('total_users', 0)
            ->where('started_at', '<', now()->subMinutes(5))
            ->get();

        foreach ($stuck as $run) {
            $note = trim((string) $run->note);
            $note = $note !== '' ? "{$note} | interrumpida (sin progreso)" : 'interrumpida (sin progreso)';

            $run->update([
                'status'      => 'failed',
                'note'        => $note,
                'finished_at' => now(),
            ]);
        }

        $stale = BalanceSyncRun::query()
            ->where('status', 'running')
            ->where('started_at', '<', now()->subMinutes($minutes))
            ->get();

        foreach ($stale as $run) {
            $note = trim((string) $run->note);
            $note = $note !== '' ? "{$note} | interrumpida (timeout o cierre de sesión)" : 'interrumpida (timeout o cierre de sesión)';

            $run->update([
                'status'      => 'failed',
                'note'        => $note,
                'finished_at' => now(),
            ]);
        }
    }

    private function sapFecha(): string
    {
        return config('balance_sync.sap_fecha') ?: now()->format('Y-m-d');
    }

    private function sapFechaFromNote(string $note): string
    {
        if (preg_match('/fecha SAP:\s*(\d{4}-\d{2}-\d{2})/', $note, $m)) {
            return $m[1];
        }

        return $this->sapFecha();
    }

    private function bulkTotalFromScope(string $scope): int
    {
        if (preg_match('/^all:(\d+)$/', $scope, $m)) {
            return (int) $m[1];
        }

        return 0;
    }

    /**
     * @param array{users: int, items: int, applied: int, unchanged: int, skipped: int, errors: int} $counters
     */
    private function finalizeRun(BalanceSyncRun $run, array $counters, string $status, ?string $note = null): void
    {
        $payload = [
            'status'      => $status,
            'total_users' => $counters['users'],
            'total_items' => $counters['items'],
            'applied'     => $counters['applied'],
            'unchanged'   => $counters['unchanged'],
            'skipped'     => $counters['skipped'],
            'errors'      => $counters['errors'],
            'finished_at' => now(),
        ];

        if ($note !== null) {
            $payload['note'] = $note;
        }

        $run->update($payload);
    }

    /**
     * @return array{users: int, items: int, applied: int, unchanged: int, skipped: int, errors: int}
     */
    private function emptyCounters(): array
    {
        return ['users' => 0, 'items' => 0, 'applied' => 0, 'unchanged' => 0, 'skipped' => 0, 'errors' => 0];
    }

    private function processEmployee(int $runId, array $emp, string $fecha, bool $dryRun, float $tolerance, array &$counters): void
    {
        $internalId = $emp['internal_id'];

        // Sin internalId no podemos ajustar en Humand.
        if (!$internalId) {
            $this->logItem($runId, $emp, null, null, 'skipped', 'Sin employeeInternalId (correo vacío en Organigrama).', $counters);
            return;
        }

        // SAP: una llamada por empleado.
        try {
            $sapData = $this->sap->balanceFor($emp['codigo_col'], $fecha);
        } catch (\Throwable $e) {
            $this->logItem($runId, $emp, null, null, 'error', 'Error consultando SAP: ' . $e->getMessage(), $counters);
            return;
        }

        if ($sapData === null) {
            $this->logItem($runId, $emp, null, null, 'skipped', 'SAP no devolvió saldos para el código.', $counters);
            return;
        }

        if (!empty($sapData['nombre'])) {
            $emp['nombre'] = $emp['nombre'] ?: $sapData['nombre'];
        }

        $adjustments = $this->adjustmentsFor($emp['person_type'], $sapData);
        $policyIds   = array_column($adjustments, 'policy_type_id');

        try {
            $balances = $this->humand->currentBalances($internalId, $policyIds);
        } catch (\Throwable $e) {
            foreach ($adjustments as $adj) {
                $this->logItem(
                    $runId,
                    $emp,
                    [
                        'policy_type_id' => (int) $adj['policy_type_id'],
                        'policy_label'   => config('balance_sync.policy_labels.' . $adj['policy_type_id'], (string) $adj['policy_type_id']),
                        'sap_concept'    => $adj['concept'],
                        'sap_value'      => $adj['value'],
                        'target_value'   => (float) round($adj['value']),
                    ],
                    null,
                    'error',
                    'Error leyendo saldos Humand: ' . $e->getMessage(),
                    $counters
                );
            }

            return;
        }

        foreach ($adjustments as $adj) {
            $policyTypeId = (int) $adj['policy_type_id'];
            $this->processAdjustment(
                $runId,
                $emp,
                $internalId,
                $adj,
                $balances[$policyTypeId] ?? null,
                $dryRun,
                $tolerance,
                $counters
            );
        }
    }

    /**
     * Define qué políticas ajustar y con qué valor SAP, según el tipo de empleado.
     *
     * @return array<int, array{policy_type_id: int, concept: string, value: float}>
     */
    private function adjustmentsFor(string $type, array $sap): array
    {
        $p = config('balance_sync.policies');

        return match ($type) {
            'supervisor' => [
                ['policy_type_id' => $p['supervisores'], 'concept' => 'vacaciones', 'value' => $sap['vacaciones']],
                ['policy_type_id' => $p['lego'],         'concept' => 'lego',       'value' => $sap['lego']],
            ],
            'fc' => [
                ['policy_type_id' => $p['vacaciones_fc'], 'concept' => 'vacaciones', 'value' => $sap['vacaciones']],
                ['policy_type_id' => $p['lego'],          'concept' => 'lego',       'value' => $sap['lego']],
            ],
            'dc' => [
                ['policy_type_id' => $p['vacaciones_dc'], 'concept' => 'vacaciones+convenio', 'value' => $sap['vacaciones'] + $sap['convenio']],
                ['policy_type_id' => $p['anticipos'],     'concept' => 'anticipo',            'value' => $sap['anticipo']],
            ],
            default => [],
        };
    }

    private function processAdjustment(
        int $runId,
        array $emp,
        string $internalId,
        array $adj,
        ?array $hb,
        bool $dryRun,
        float $tolerance,
        array &$counters
    ): void {
        $policyTypeId = (int) $adj['policy_type_id'];
        $label        = config("balance_sync.policy_labels.{$policyTypeId}", (string) $policyTypeId);
        $target       = (float) round($adj['value']); // FULL_DAYS -> entero

        $base = [
            'policy_type_id' => $policyTypeId,
            'policy_label'   => $label,
            'sap_concept'    => $adj['concept'],
            'sap_value'      => $adj['value'],
            'target_value'   => $target,
        ];

        $hb = $hb ?? [
            'found'           => false,
            'current_balance' => null,
            'cycle_title'     => null,
        ];

        if (!$hb['found']) {
            $this->logItem($runId, $emp, $base, null, 'skipped', 'El empleado no tiene esta política/ciclo vigente en Humand.', $counters);
            return;
        }

        $current  = (float) $hb['current_balance'];
        $accYear  = $hb['cycle_title']; // año de acreditación = ciclo vigente
        $base['humand_before']      = $current;
        $base['cycle_title']        = $hb['cycle_title'];
        $base['accreditation_year'] = $accYear;
        $base['operation']          = 'SET';

        if (abs($target - $current) <= $tolerance) {
            $this->logItem($runId, $emp, $base, null, 'unchanged', "Sin cambios (Humand={$current}, SAP={$target}).", $counters);
            return;
        }

        if ($dryRun) {
            $this->logItem($runId, $emp, $base, null, 'simulated', "Simulado: SET {$current} -> {$target}.", $counters);
            return;
        }

        $obs = "Ajuste automático SAP↔Humand (run #{$runId}) " . Carbon::now()->format('Y-m-d H:i');
        $res = $this->humand->setBalance($policyTypeId, $internalId, $target, $obs, $accYear);

        if ($res['ok']) {
            $this->logItem($runId, $emp, $base, $res['status'], 'applied', "Aplicado: SET {$current} -> {$target}.", $counters);
        } else {
            $this->logItem($runId, $emp, $base, $res['status'], 'error', "Humand rechazó la corrección: " . mb_substr($res['body'], 0, 800), $counters);
        }
    }

    private function logItem(int $runId, array $emp, ?array $base, ?int $httpStatus, string $status, string $message, array &$counters): void
    {
        BalanceSyncItem::create(array_merge([
            'run_id'               => $runId,
            'codigo_col'           => $emp['codigo_col'] ?? null,
            'employee_internal_id' => $emp['internal_id'] ?? null,
            'full_name'            => $emp['nombre'] ?? null,
            'person_type'          => $emp['person_type'] ?? null,
            'policy_type_id'       => null,
            'policy_label'         => null,
            'sap_concept'          => null,
            'sap_value'            => null,
            'target_value'         => null,
            'humand_before'        => null,
            'operation'            => null,
            'cycle_title'          => null,
            'accreditation_year'   => null,
            'http_status'          => $httpStatus,
            'status'               => $status,
            'message'              => $message,
            'created_at'           => now(),
        ], $base ?? []));

        $counters['items']++;

        $map = [
            'applied'   => 'applied',
            'simulated' => 'applied',   // en dry-run cuenta como "cambiaría"
            'unchanged' => 'unchanged',
            'skipped'   => 'skipped',
            'error'     => 'errors',
        ];
        if (isset($map[$status])) {
            $counters[$map[$status]]++;
        }
    }
}
