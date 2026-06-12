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
 *   3. Por cada política objetivo, lee el saldo actual en Humand.
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
        $tolerance = (float) config('balance_sync.tolerance', 0.01);
        $fecha     = config('balance_sync.sap_fecha') ?: now()->format('Y-m-d');

        $codigos = array_values(array_filter(array_map('trim', $codigos), fn ($c) => $c !== ''));

        $run = BalanceSyncRun::create([
            'dry_run'      => $dryRun,
            'status'       => 'running',
            'triggered_by' => $triggeredBy,
            'scope'        => $codigos !== [] ? 'codigo:' . implode(',', $codigos) : 'all',
            'note'         => "fecha SAP: {$fecha}",
            'started_at'   => now(),
        ]);

        $counters = ['users' => 0, 'items' => 0, 'applied' => 0, 'unchanged' => 0, 'skipped' => 0, 'errors' => 0];

        try {
            $employees = $this->organigrama->employees($codigos);

            foreach ($employees as $emp) {
                $counters['users']++;
                $this->processEmployee($run->id, $emp, $fecha, $dryRun, $tolerance, $counters);
            }

            $run->update([
                'status'      => 'done',
                'total_users' => $counters['users'],
                'total_items' => $counters['items'],
                'applied'     => $counters['applied'],
                'unchanged'   => $counters['unchanged'],
                'skipped'     => $counters['skipped'],
                'errors'      => $counters['errors'],
                'finished_at' => now(),
            ]);
        } catch (\Throwable $e) {
            $run->update([
                'status'      => 'failed',
                'total_users' => $counters['users'],
                'total_items' => $counters['items'],
                'applied'     => $counters['applied'],
                'unchanged'   => $counters['unchanged'],
                'skipped'     => $counters['skipped'],
                'errors'      => $counters['errors'],
                'note'        => $e->getMessage(),
                'finished_at' => now(),
            ]);
            throw $e;
        }

        return $run->fresh();
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

        foreach ($this->adjustmentsFor($emp['person_type'], $sapData) as $adj) {
            $this->processAdjustment($runId, $emp, $internalId, $adj, $dryRun, $tolerance, $counters);
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

    private function processAdjustment(int $runId, array $emp, string $internalId, array $adj, bool $dryRun, float $tolerance, array &$counters): void
    {
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

        try {
            $hb = $this->humand->currentBalance($internalId, $policyTypeId);
        } catch (\Throwable $e) {
            $this->logItem($runId, $emp, $base, null, 'error', 'Error leyendo saldo Humand: ' . $e->getMessage(), $counters);
            return;
        }

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
