<?php

namespace App\Services\Balances;

use Illuminate\Support\Facades\Http;

/**
 * Cliente del endpoint SAP zws_dias_vac.
 *
 * GET {sap_balance_url}?sap-client=300&codigoPersonal=8021&fecha=YYYY-MM-DD
 * Respuesta: [{ codigoPersonal, nombre, vacaciones, anticipo, convenio, lego, totalDias }]
 */
class SapBalanceClient
{
    /**
     * Devuelve los días por concepto del empleado, o null si SAP no devuelve datos.
     *
     * @return array{codigoPersonal: mixed, nombre: ?string, vacaciones: float, anticipo: float, convenio: float, lego: float, totalDias: float}|null
     */
    public function balanceFor(string $codigoPersonal, ?string $fecha = null): ?array
    {
        $url    = config('balance_sync.sap_balance_url');
        $client = config('balance_sync.sap_client', '300');
        $fecha  = $fecha ?: now()->format('Y-m-d');

        $response = Http::withOptions([
                'proxy'  => false,
                'verify' => filter_var(env('SAP_VERIFY_SSL', 'false'), FILTER_VALIDATE_BOOLEAN),
            ])
            ->withBasicAuth((string) config('balance_sync.sap_user'), (string) config('balance_sync.sap_pass'))
            ->withHeaders(['Accept' => 'application/json'])
            ->connectTimeout((int) env('SAP_CONNECT_TIMEOUT', 8))
            ->timeout((int) env('SAP_READ_TIMEOUT', 20))
            ->get($url, [
                'sap-client'     => $client,
                'codigoPersonal' => $codigoPersonal,
                'fecha'          => $fecha,
            ]);

        $response->throw();

        $data = $response->json();
        if (!is_array($data) || $data === []) {
            return null;
        }

        // El endpoint devuelve un arreglo; tomamos el primer registro.
        $row = array_is_list($data) ? ($data[0] ?? null) : $data;
        if (!is_array($row)) {
            return null;
        }

        return [
            'codigoPersonal' => $row['codigoPersonal'] ?? $codigoPersonal,
            'nombre'         => $row['nombre'] ?? null,
            'vacaciones'     => (float) ($row['vacaciones'] ?? 0),
            'anticipo'       => (float) ($row['anticipo'] ?? 0),
            'convenio'       => (float) ($row['convenio'] ?? 0),
            'lego'           => (float) ($row['lego'] ?? 0),
            'totalDias'      => (float) ($row['totalDias'] ?? 0),
        ];
    }
}
