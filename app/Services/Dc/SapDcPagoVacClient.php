<?php

namespace App\Services\Dc;

use Illuminate\Support\Facades\Http;

class SapDcPagoVacClient
{
    /**
     * @return array{ok: bool, status: int, body: string, url: string}
     */
    public function send(int $codigoPersonal, string $opcion, string $fecha): array
    {
        $base   = rtrim((string) config('dc_sap_export.url'), '/');
        $client = config('dc_sap_export.client', '300');
        $url    = "{$base}?sap-client={$client}";

        $payload = [
            'codigoPersonal' => $codigoPersonal,
            'opcion'         => $opcion,
            'fecha'          => $fecha,
        ];

        $response = Http::withOptions([
                'proxy'  => false,
                'verify' => filter_var(env('SAP_VERIFY_SSL', 'false'), FILTER_VALIDATE_BOOLEAN),
            ])
            ->withBasicAuth((string) config('dc_sap_export.user'), (string) config('dc_sap_export.pass'))
            ->withHeaders([
                'Accept'       => 'application/json',
                'Content-Type' => 'application/json',
            ])
            ->connectTimeout((int) env('SAP_CONNECT_TIMEOUT', 8))
            ->timeout((int) env('SAP_READ_TIMEOUT', 30))
            ->post($url, $payload);

        $body = trim((string) $response->body());
        $ok   = $response->successful() && $this->sapSuccess($body);

        return [
            'ok'     => $ok,
            'status' => $response->status(),
            'body'   => $body,
            'url'    => $url,
        ];
    }

    private function sapSuccess(string $body): bool
    {
        if ($body === '') {
            return false;
        }

        try {
            $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
            $row  = is_array($data) && array_is_list($data) ? ($data[0] ?? null) : $data;
            if (is_array($row) && isset($row['type'])) {
                return strtoupper((string) $row['type']) === 'S';
            }
        } catch (\Throwable) {
            // cuerpo no JSON
        }

        return true;
    }
}
