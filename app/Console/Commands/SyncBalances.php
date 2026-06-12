<?php

namespace App\Console\Commands;

use App\Services\Balances\BalanceSyncService;
use Illuminate\Console\Command;

class SyncBalances extends Command
{
    protected $signature = 'balances:sync
        {--apply : Aplica los ajustes en Humand (por defecto solo simula / dry-run)}
        {--codigo= : Procesa uno o varios CodigoCol separados por coma (ej. 8758,8021)}';

    protected $description = 'Concilia saldos de vacaciones SAP -> Humand (dry-run por defecto)';

    public function handle(BalanceSyncService $service): int
    {
        $dryRun  = !$this->option('apply');
        $codigos = array_filter(array_map('trim', explode(',', (string) $this->option('codigo'))), fn ($c) => $c !== '');

        $this->info(($dryRun ? '[DRY-RUN] ' : '[APLICAR] ') . 'Iniciando sincronización de saldos...');
        if ($codigos !== []) {
            $this->line('Filtro: CodigoCol = ' . implode(', ', $codigos));
        }

        try {
            $run = $service->run($dryRun, $codigos, 'cli');
        } catch (\Throwable $e) {
            $this->error('Falló: ' . $e->getMessage());
            return self::FAILURE;
        }

        $this->newLine();
        $this->table(
            ['Empleados', 'Ítems', 'Aplicados/Simulados', 'Sin cambio', 'Omitidos', 'Errores'],
            [[$run->total_users, $run->total_items, $run->applied, $run->unchanged, $run->skipped, $run->errors]]
        );
        $this->info("Run #{$run->id} ({$run->status}).");

        return self::SUCCESS;
    }
}
