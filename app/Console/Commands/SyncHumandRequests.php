<?php

namespace App\Console\Commands;

use App\Services\HumandTimeOffEtlService;
use Illuminate\Console\Command;

class SyncHumandRequests extends Command
{
    protected $signature = 'humand:sync-requests
        {group : Grupo fc|dc}
        {policy : Slug de política (ej. lego, vacaciones-fc)}';

    protected $description = 'Descarga solicitudes de tiempo libre desde Humand hacia SQL Server';

    public function handle(HumandTimeOffEtlService $etl): int
    {
        $group  = strtolower(trim((string) $this->argument('group')));
        $policy = strtolower(trim((string) $this->argument('policy')));

        if (!in_array($group, ['fc', 'dc'], true)) {
            $this->error('Grupo inválido. Use fc o dc.');
            return self::FAILURE;
        }

        $config = config("time_off_policies.{$group}.{$policy}");
        if (!$config) {
            $this->error("Política no configurada: {$group}/{$policy}");
            return self::FAILURE;
        }

        $policyTypeId = (int) ($config['policy_type_id'] ?? 0);
        $label        = (string) ($config['label'] ?? $policy);

        if (function_exists('set_time_limit')) {
            @set_time_limit((int) config('scripts.etl_timeout', 600) + 120);
        }

        $this->info("ETL Humand — {$label} (policyTypeId={$policyTypeId})…");

        try {
            $count = $etl->run($policyTypeId);
        } catch (\Throwable $e) {
            $this->error('ETL falló: ' . $e->getMessage());
            return self::FAILURE;
        }

        $this->info("ETL OK — {$count} filas · desde {$etl->activeSinceDate()}");

        return self::SUCCESS;
    }
}
