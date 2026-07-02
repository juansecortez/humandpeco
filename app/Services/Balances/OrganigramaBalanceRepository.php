<?php

namespace App\Services\Balances;

use Illuminate\Support\Facades\DB;

/**
 * Lee empleados de Organigrama y los clasifica para la sincronización de saldos.
 *
 * Reglas de clasificación (precedencia):
 *   1. Supervisor : AreaPersonal IN (config supervisor_area_personal)  -> internalId = Correo
 *   2. FC         : NivelFirma >= 1                                     -> internalId = Correo
 *   3. DC         : NivelFirma  < 1                                     -> internalId = CodigoCol + CodigoCol
 */
class OrganigramaBalanceRepository
{
    /**
     * @return array<int, array{
     *   codigo_col: string, usuario_id: ?string, correo: ?string, nombre: ?string,
     *   nivel_firma: int, area_personal: ?int, person_type: string, internal_id: ?string
     * }>
     */
    public function countEmployees(array $codigos = []): int
    {
        $query = DB::connection('organigrama')
            ->table('OrganigramaCompleto')
            ->whereNotNull('CodigoCol');

        $codigos = array_values(array_filter(array_map('trim', $codigos), fn ($c) => $c !== ''));
        if ($codigos !== []) {
            $query->whereIn('CodigoCol', $codigos);
        }

        return (int) $query->count();
    }

    public function employees(array $codigos = [], ?int $offset = null, ?int $limit = null): array
    {
        $query = DB::connection('organigrama')
            ->table('OrganigramaCompleto')
            ->select([
                'UsuarioId',
                'CodigoCol',
                'NombreCompleto',
                'Correo',
                'NivelFirma',
                'AreaPersonal',
            ])
            ->whereNotNull('CodigoCol')
            ->orderBy('CodigoCol');

        $codigos = array_values(array_filter(array_map('trim', $codigos), fn ($c) => $c !== ''));
        if ($codigos !== []) {
            $query->whereIn('CodigoCol', $codigos);
        }

        if ($offset !== null) {
            $query->offset($offset);
        }
        if ($limit !== null) {
            $query->limit($limit);
        }

        $rows = $query->get();

        $out = [];
        foreach ($rows as $r) {
            $codigo = trim((string) $r->CodigoCol);
            if ($codigo === '') {
                continue;
            }

            $nivel = (int) ($r->NivelFirma ?? 0);
            $area  = $r->AreaPersonal !== null ? (int) $r->AreaPersonal : null;
            $type  = $this->classify($nivel, $area);

            $out[] = [
                'codigo_col'    => $codigo,
                'usuario_id'    => $r->UsuarioId !== null ? trim((string) $r->UsuarioId) : null,
                'correo'        => $r->Correo !== null ? trim((string) $r->Correo) : null,
                'nombre'        => $r->NombreCompleto !== null ? trim((string) $r->NombreCompleto) : null,
                'nivel_firma'   => $nivel,
                'area_personal' => $area,
                'person_type'   => $type,
                'internal_id'   => $this->internalId($type, $codigo, $r->Correo),
            ];
        }

        return $out;
    }

    public function classify(int $nivelFirma, ?int $areaPersonal): string
    {
        $supervisorAreas = (array) config('balance_sync.supervisor_area_personal', [2, 5]);

        if ($areaPersonal !== null && in_array($areaPersonal, $supervisorAreas, true)) {
            return 'supervisor';
        }

        return $nivelFirma >= 1 ? 'fc' : 'dc';
    }

    /**
     * FC / Supervisor -> correo en minúsculas.
     * DC             -> CodigoCol duplicado (ej. "8021" -> "80218021").
     */
    public function internalId(string $type, string $codigoCol, ?string $correo): ?string
    {
        if ($type === 'dc') {
            return $codigoCol . $codigoCol;
        }

        $correo = trim((string) $correo);

        return $correo !== '' ? mb_strtolower($correo) : null;
    }
}
