<?php

namespace App\Console\Commands;

use App\Services\Balances\BalanceSyncService;
use Illuminate\Console\Command;

class SyncBalancesWorker extends Command
{
    protected $signature = 'balances:sync-worker {runId : ID del balance_sync_runs en ejecución}';

    protected $description = 'Procesa en segundo plano un run de sincronización de saldos (bulk)';

    public function handle(BalanceSyncService $service): int
    {
        $runId = (int) $this->argument('runId');

        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }

        $this->info("Worker iniciado para run #{$runId}.");

        try {
            $run = $service->runBulkWorker($runId);
        } catch (\Throwable $e) {
            $this->error('Worker falló: ' . $e->getMessage());

            return self::FAILURE;
        }

        $this->info("Run #{$run->id} finalizado ({$run->status}): {$run->total_users} empleados, {$run->errors} errores.");

        return self::SUCCESS;
    }
}
