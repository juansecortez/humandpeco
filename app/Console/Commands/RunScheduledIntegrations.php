<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class RunScheduledIntegrations extends Command
{
    protected $signature = 'integrations:run-scheduled';

    protected $description = 'Ejecuta el ciclo completo de integraciones Humand ↔ SAP (ETL, saldos, export SAP)';

    public function handle(): int
    {
        if (!config('schedule_integrations.enabled', true)) {
            $this->warn('Integraciones programadas deshabilitadas (SCHEDULE_INTEGRATIONS_ENABLED=false).');
            return self::SUCCESS;
        }

        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }

        $this->info('=== Ciclo programado de integraciones — ' . now()->format('Y-m-d H:i:s') . ' ===');
        $failures = [];

        foreach ($this->etlPolicies() as [$group, $slug, $label]) {
            $this->newLine();
            $this->line("→ ETL Humand: {$label}");
            $code = $this->runStep("humand:sync-requests {$group} {$slug}", function () use ($group, $slug) {
                return $this->call('humand:sync-requests', ['group' => $group, 'policy' => $slug]);
            });
            if ($code !== self::SUCCESS) {
                $failures[] = "ETL {$label}";
            }
        }

        $this->newLine();
        $this->line('→ Sincronización de saldos SAP ↔ Humand');
        $balanceArgs = ['--background' => true];
        if (config('schedule_integrations.balance_sync_apply', true)) {
            $balanceArgs['--apply'] = true;
        }
        $code = $this->runStep('balances:sync', function () use ($balanceArgs) {
            return $this->call('balances:sync', $balanceArgs);
        });
        if ($code !== self::SUCCESS) {
            $failures[] = 'Sincronización de saldos';
        }

        foreach ((array) config('schedule_integrations.sap_export_scopes', []) as $scope) {
            $scope = trim((string) $scope);
            if ($scope === '') {
                continue;
            }

            $this->newLine();
            $this->line("→ Export SAP: {$scope}");
            $code = $this->runStep("sap:export-time-off {$scope}", function () use ($scope) {
                return $this->call('sap:export-time-off', ['scope' => $scope]);
            });
            if ($code !== self::SUCCESS) {
                $failures[] = "Export SAP {$scope}";
            }
        }

        $this->newLine();
        $this->line('→ Export SAP Vacaciones DC');
        $code = $this->runStep('sap:export-dc-pago-vac', function () {
            return $this->call('sap:export-dc-pago-vac');
        });
        if ($code !== self::SUCCESS) {
            $failures[] = 'Export SAP Vacaciones DC';
        }

        $this->newLine();
        if ($failures === []) {
            $this->info('=== Ciclo completado sin errores ===');
            return self::SUCCESS;
        }

        $this->error('=== Ciclo completado con errores en: ' . implode(', ', $failures) . ' ===');
        return self::FAILURE;
    }

    /**
     * @return list<array{0: string, 1: string, 2: string}>
     */
    private function etlPolicies(): array
    {
        $out = [];

        foreach (['fc', 'dc'] as $group) {
            foreach ((array) config("time_off_policies.{$group}", []) as $slug => $cfg) {
                $out[] = [
                    $group,
                    (string) $slug,
                    (string) ($cfg['label'] ?? $slug),
                ];
            }
        }

        return $out;
    }

    private function runStep(string $label, callable $callback): int
    {
        try {
            return (int) $callback();
        } catch (\Throwable $e) {
            $this->error("[{$label}] " . $e->getMessage());
            return self::FAILURE;
        }
    }
}
