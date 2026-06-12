<?php

namespace App\Services\Balances;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

/**
 * Cliente Humand para saldos:
 *  - GET  /time-off/balances?employeeInternalId=...&policyTypeIds=...
 *  - POST /time-off/policy-types/{id}/balances/correction
 */
class HumandBalanceClient
{
    /**
     * Saldo actual del empleado en una política, eligiendo el ciclo vigente (que contiene HOY).
     *
     * @return array{found: bool, current_balance: ?float, cycle_title: ?string, cycle_from: ?string, cycle_to: ?string, raw: ?array}
     */
    public function currentBalance(string $employeeInternalId, int $policyTypeId): array
    {
        $base = $this->apiBase();

        $response = Http::withOptions($this->httpOptions())
            ->withHeaders([
                'Accept'        => 'application/json',
                'Authorization' => $this->auth(),
            ])
            ->connectTimeout(20)
            ->timeout(60)
            ->get("{$base}/time-off/balances", [
                'employeeInternalId' => $employeeInternalId,
                'policyTypeIds'      => (string) $policyTypeId,
                'limit'              => 50,
                'page'               => 1,
            ]);

        $response->throw();

        $items = $response->json('items') ?? [];
        if ($items === []) {
            return ['found' => false, 'current_balance' => null, 'cycle_title' => null, 'cycle_from' => null, 'cycle_to' => null, 'raw' => null];
        }

        $chosen = $this->pickCycle($items);
        if ($chosen === null) {
            return ['found' => false, 'current_balance' => null, 'cycle_title' => null, 'cycle_from' => null, 'cycle_to' => null, 'raw' => null];
        }

        $cycle = $chosen['cycle'] ?? [];

        return [
            'found'           => true,
            'current_balance' => isset($chosen['currentBalance']) ? (float) $chosen['currentBalance'] : null,
            'cycle_title'     => $cycle['title'] ?? null,
            'cycle_from'      => $cycle['fromDate'] ?? null,
            'cycle_to'        => $cycle['toDate'] ?? null,
            'raw'             => $chosen,
        ];
    }

    /**
     * Aplica una corrección SET de saldo.
     *
     * @return array{ok: bool, status: int, body: string}
     */
    public function setBalance(int $policyTypeId, string $employeeInternalId, float $amount, string $observations, ?string $accreditationYear): array
    {
        $base = $this->apiBase();

        $body = [
            'employeeInternalId' => $employeeInternalId,
            'operation'          => 'SET',
            'amount'             => $amount,
            'observations'       => $observations,
        ];
        if ($accreditationYear !== null && $accreditationYear !== '') {
            $body['accreditationYear'] = $accreditationYear;
        }

        $response = Http::withOptions($this->httpOptions())
            ->withHeaders([
                'Accept'        => 'application/json',
                'Content-Type'  => 'application/json',
                'Authorization' => $this->auth(),
            ])
            ->connectTimeout(20)
            ->timeout(60)
            ->post("{$base}/time-off/policy-types/{$policyTypeId}/balances/correction", $body);

        return [
            'ok'     => $response->successful(),
            'status' => $response->status(),
            'body'   => trim((string) $response->body()),
        ];
    }

    /**
     * Elige el ciclo vigente: el que contiene HOY; si ninguno, el más reciente por fromDate.
     */
    private function pickCycle(array $items): ?array
    {
        $today = Carbon::today();
        $latest = null;
        $latestFrom = null;

        foreach ($items as $item) {
            $cycle = $item['cycle'] ?? [];
            $from = isset($cycle['fromDate']) ? Carbon::parse($cycle['fromDate']) : null;
            $to   = isset($cycle['toDate']) ? Carbon::parse($cycle['toDate']) : null;

            if ($from && $to && $today->betweenIncluded($from, $to)) {
                return $item;
            }

            if ($from && ($latestFrom === null || $from->greaterThan($latestFrom))) {
                $latestFrom = $from;
                $latest = $item;
            }
        }

        return $latest;
    }

    private function apiBase(): string
    {
        $base = $this->envClean('HUMAND_API_BASE') ?: 'https://api-prod.humand.co/public/api/v1';

        return rtrim($base, '/');
    }

    private function auth(): string
    {
        return (string) $this->envClean('HUMAND_API_AUTH');
    }

    private function httpOptions(): array
    {
        $verify = env('HUMAND_VERIFY_SSL', env('SAP_VERIFY_SSL', 'true'));

        return [
            'proxy'  => false,
            'verify' => filter_var($verify, FILTER_VALIDATE_BOOLEAN),
        ];
    }

    private function envClean(string $key): ?string
    {
        $v = env($key);
        if ($v === null || $v === '') {
            return null;
        }

        return trim(trim((string) $v), " '\"");
    }
}
