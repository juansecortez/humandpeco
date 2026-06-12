<?php

namespace App\Services\Dc;

use App\Models\SapDcPagoVacExport;
use App\Models\TimeOffRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DcTimeOffSapExportService
{
    public function __construct(
        private DcOpcionParser $parser,
        private SapDcPagoVacClient $sap,
    ) {
    }

    /** @return array<int> */
    public function dcPolicyTypeIds(): array
    {
        return config('dc_sap_export.policy_type_ids', []);
    }

    public function runAutoExport(): array
    {
        $stats = ['processed' => 0, 'sent' => 0, 'skipped' => 0, 'errors' => 0];

        foreach ($this->exportableAutoRequests() as $request) {
            $opcion = $this->parser->parse($request->description);
            if (!$opcion) {
                $stats['skipped']++;
                continue;
            }

            $stats['processed']++;
            $export = $this->exportRequest($request, $opcion, 'auto');
            if ($export->response_ok) {
                $stats['sent']++;
            } else {
                $stats['errors']++;
            }
        }

        return $stats;
    }

    /** Solicitudes APPROVED con opción 1/2/3 en description, aún no enviadas OK. */
    public function exportableAutoRequests(): Collection
    {
        return $this->baseCandidateQuery()
            ->get()
            ->filter(fn (TimeOffRequest $r) => $this->parser->parse($r->description) !== null)
            ->values();
    }

    /** Solicitudes APPROVED sin opción válida, pendientes de selección manual. */
    public function pendingManualRequests(): Collection
    {
        return $this->baseCandidateQuery()
            ->get()
            ->filter(fn (TimeOffRequest $r) => $this->parser->parse($r->description) === null)
            ->values();
    }

    public function exportRequest(TimeOffRequest $request, string $opcion, string $source = 'manual'): SapDcPagoVacExport
    {
        $opcion = trim($opcion);
        if (!in_array($opcion, config('dc_sap_export.valid_opciones', ['1', '2', '3']), true)) {
            throw new \InvalidArgumentException('Opción inválida. Debe ser 1, 2 o 3.');
        }

        if ($this->alreadyExportedOk($request->request_id)) {
            throw new \RuntimeException("La solicitud #{$request->request_id} ya fue enviada a SAP correctamente.");
        }

        $codigo = $this->codigoColForRequest($request);
        if (!$codigo || !ctype_digit($codigo)) {
            throw new \RuntimeException("No se pudo resolver CodigoCol para la solicitud #{$request->request_id}.");
        }

        if (!$request->from_date) {
            throw new \RuntimeException("La solicitud #{$request->request_id} no tiene fecha de inicio.");
        }

        $fecha = $request->from_date->format('Y-m-d');

        try {
            $result = $this->sap->send((int) $codigo, $opcion, $fecha);
        } catch (\Throwable $e) {
            return $this->saveExport($request, $opcion, $source, $fecha, $codigo, [
                'url'    => config('dc_sap_export.url'),
                'status' => null,
                'ok'     => false,
                'body'   => $e->getMessage(),
            ]);
        }

        return $this->saveExport($request, $opcion, $source, $fecha, $codigo, [
            'url'    => $result['url'],
            'status' => $result['status'],
            'ok'     => $result['ok'],
            'body'   => $result['body'],
        ]);
    }

    public function codigoColForRequest(TimeOffRequest $request): ?string
    {
        $internal = trim((string) $request->issuer_employee_internal_id);
        if ($internal === '') {
            return null;
        }

        // DC: internalId = CodigoCol duplicado (ej. 80218021)
        $len = strlen($internal);
        if ($len >= 2 && $len % 2 === 0) {
            $half = (int) ($len / 2);
            $left = substr($internal, 0, $half);
            $right = substr($internal, $half);
            if ($left === $right && ctype_digit($left)) {
                return ltrim($left, '0') ?: $left;
            }
        }

        // Respaldo: organigrama por UsuarioId (correo) o CodigoCol
        $usuarioId = str_contains($internal, '@')
            ? strstr($internal, '@', true)
            : $internal;

        $row = DB::connection('organigrama')
            ->table('OrganigramaCompleto')
            ->select('CodigoCol')
            ->where('UsuarioId', $usuarioId)
            ->orWhere('Correo', $internal)
            ->first();

        if ($row && $row->CodigoCol) {
            return trim((string) $row->CodigoCol);
        }

        return ctype_digit($internal) ? $internal : null;
    }

    public function alreadyExportedOk(int $requestId): bool
    {
        return SapDcPagoVacExport::query()
            ->where('request_id', $requestId)
            ->where('processed_state', 'APPROVED')
            ->where('response_ok', true)
            ->exists();
    }

    /** @return \Illuminate\Database\Eloquent\Builder<TimeOffRequest> */
    private function baseCandidateQuery()
    {
        $states = array_map('strtoupper', config('dc_sap_export.process_states', ['APPROVED']));
        $policyIds = $this->dcPolicyTypeIds();

        $exportedIds = SapDcPagoVacExport::query()
            ->where('processed_state', 'APPROVED')
            ->where('response_ok', true)
            ->pluck('request_id');

        $placeholders = implode(',', array_fill(0, count($states), '?'));

        return TimeOffRequest::query()
            ->whereIn('policy_type_id', $policyIds)
            ->whereRaw("UPPER(LTRIM(RTRIM(state))) IN ({$placeholders})", $states)
            ->when($exportedIds->isNotEmpty(), fn ($q) => $q->whereNotIn('request_id', $exportedIds))
            ->orderByDesc('created_at');
    }

    private function saveExport(
        TimeOffRequest $request,
        string $opcion,
        string $source,
        string $fecha,
        string $codigo,
        array $result
    ): SapDcPagoVacExport {
        $now = now();

        return SapDcPagoVacExport::updateOrCreate(
            [
                'request_id'      => $request->request_id,
                'processed_state' => strtoupper((string) $request->state),
            ],
            [
                'issuer_employee_internal_id' => $request->issuer_employee_internal_id,
                'issuer_full_name'            => $request->issuer_full_name,
                'codigo_col'                  => $codigo,
                'policy_type_id'              => $request->policy_type_id,
                'policy_name'                 => $request->policy_name,
                'opcion'                      => $opcion,
                'source'                      => $source,
                'fecha_inicio'                => $fecha,
                'request_url'                 => $result['url'] ?? null,
                'response_status'             => $result['status'] ?? null,
                'response_ok'                 => (bool) ($result['ok'] ?? false),
                'response_text'               => $result['body'] ?? null,
                'responded_at'                => $now,
            ]
        );
    }
}
