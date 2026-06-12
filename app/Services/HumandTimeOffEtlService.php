<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Carbon;

class HumandTimeOffEtlService
{
    public function run(int $policyTypeId): int
    {
        $items = $this->fetchAll($policyTypeId);

        return $this->upsert($items);
    }

    public function activeSinceDate(): string
    {
        return $this->createdAtSince();
    }

    private function fetchAll(int $policyTypeId): array
    {
        [$baseUrl, $baseParams] = $this->apiUrlParts();

        $all = [];
        $page = 1;

        while (true) {
            $params = array_merge($baseParams, [
                'policyTypeIds' => (string) $policyTypeId,
                'page'          => (string) $page,
            ]);

            $response = Http::withOptions($this->httpOptions())
                ->connectTimeout(30)
                ->timeout(120)
                ->withHeaders([
                    'Accept'        => 'application/json',
                    'Authorization' => $this->envClean('HUMAND_API_AUTH'),
                ])
                ->get($baseUrl, $params);

            $response->throw();

            $items = $response->json('items') ?? [];
            if ($items === []) {
                break;
            }

            $all = array_merge($all, $items);
            $page++;
        }

        return $all;
    }

    /** @return array{0: string, 1: array<string, string>} */
    private function apiUrlParts(): array
    {
        $raw = $this->envClean('HUMAND_API_URL')
            ?: 'https://api-prod.humand.co/public/api/v1/time-off/requests?page=1';

        $parts = parse_url($raw);
        if (!$parts || empty($parts['host'])) {
            throw new \RuntimeException('HUMAND_API_URL no válida.');
        }

        $baseUrl = sprintf(
            '%s://%s%s',
            $parts['scheme'] ?? 'https',
            $parts['host'],
            $parts['path'] ?? ''
        );

        parse_str($parts['query'] ?? '', $query);
        $params = array_map('strval', $query);

        if ($states = $this->envClean('HUMAND_ETL_STATES')) {
            $params['states'] = $states;
        }
        $params['createdAtSince'] = $this->createdAtSince();

        return [$baseUrl, $params];
    }

    /** Fecha mínima de createdAt en Humand (YYYY-MM-DD). */
    private function createdAtSince(): string
    {
        if ($fixed = $this->envClean('HUMAND_ETL_CREATED_AT_SINCE')) {
            return $fixed;
        }

        $months = config('scripts.etl_lookback_months', 2);

        return now()->subMonths($months)->format('Y-m-d');
    }

    private function httpOptions(): array
    {
        $verify = env('HUMAND_VERIFY_SSL', env('SAP_VERIFY_SSL', 'true'));

        return [
            'proxy'  => false,
            'verify' => filter_var($verify, FILTER_VALIDATE_BOOLEAN),
        ];
    }

    private function upsert(array $items): int
    {
        if ($items === []) {
            return 0;
        }

        $count = 0;

        foreach (array_chunk($items, 100) as $chunk) {
            foreach ($chunk as $item) {
                $issuer = $item['issuer'] ?? [];
                $policy = $item['policyType'] ?? [];

                $row = [
                    'request_id'                  => $item['id'],
                    'issuer_employee_internal_id' => $issuer['employeeInternalId'] ?? null,
                    'issuer_full_name'            => $this->issuerFullName($issuer),
                    'policy_type_id'              => $policy['id'] ?? null,
                    'policy_name'                 => $policy['name'] ?? null,
                    'from_date'                   => ($item['from']['date'] ?? null),
                    'to_date'                     => ($item['to']['date'] ?? null),
                    'amount_requested'            => $item['amountRequested'] ?? null,
                    'state'                       => $item['state'] ?? null,
                    'step_state'                  => $item['stepState'] ?? null,
                    'created_at'                  => $this->parseApiDate($item['createdAt'] ?? null),
                    'resolution_date'             => $this->parseApiDate($item['resolutionDate'] ?? null),
                    'description'                 => $item['description'] ?? null,
                    'etl_synced_at'               => now()->utc(),
                ];

                DB::table('time_off_requests')->updateOrInsert(
                    ['request_id' => $row['request_id']],
                    $row
                );
                $count++;
            }
        }

        return $count;
    }

    private function issuerFullName(array $issuer): ?string
    {
        $first = trim($issuer['firstName'] ?? '');
        $last  = trim($issuer['lastName'] ?? '');

        if ($first && $last) {
            return "{$first} {$last}";
        }
        if ($first || $last) {
            return $first ?: $last;
        }

        $email = trim($issuer['email'] ?? '');
        if ($email !== '') {
            return explode('@', $email, 2)[0];
        }

        return null;
    }

    private function parseApiDate(?string $value): ?Carbon
    {
        if (!$value) {
            return null;
        }

        try {
            return Carbon::parse($value)->utc();
        } catch (\Throwable) {
            return null;
        }
    }

    private function envClean(string $key): ?string
    {
        $v = env($key);
        if ($v === null || $v === '') {
            return null;
        }
        $v = trim($v);

        return trim($v, " '\"");
    }
}
