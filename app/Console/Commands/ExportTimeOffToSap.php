<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

class ExportTimeOffToSap extends Command
{
    protected $signature = 'sap:export-time-off
        {scope=vacaciones-fc : fc|vacaciones-fc|lego|supervisores|anticipos}';

    protected $description = 'Exporta solicitudes aprobadas/canceladas a SAP (zrh_vacacion)';

    public function handle(): int
    {
        $scope = strtolower(trim((string) $this->argument('scope')));
        $allowed = ['fc', 'vacaciones-fc', 'lego', 'supervisores', 'anticipos'];

        if (!in_array($scope, $allowed, true)) {
            $this->error('Scope inválido: ' . $scope);
            return self::FAILURE;
        }

        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }

        $python = $this->pythonBinary();
        $script = base_path('scripts/export_time_off_to_sap.py');

        $this->info("Export SAP — scope={$scope}…");

        $process = new Process([$python, $script, $scope], base_path(), null, null, null);
        $process->setIdleTimeout(null);

        try {
            $process->run(function ($type, $buffer) {
                $this->output->write($buffer);
            });
        } catch (ProcessTimedOutException $e) {
            $this->error('Exportación a SAP: tiempo agotado.');
            return self::FAILURE;
        }

        if (!$process->isSuccessful()) {
            $err = trim($process->getErrorOutput() ?: $process->getOutput());
            $this->error('Exportación a SAP falló: ' . ($err !== '' ? $err : 'código ' . $process->getExitCode()));
            return self::FAILURE;
        }

        $out = trim($process->getOutput());
        if ($out !== '') {
            $this->line($out);
        }

        $this->info('Export SAP finalizado.');

        return self::SUCCESS;
    }

    private function pythonBinary(): string
    {
        $bin = config('scripts.python_bin');

        if (is_string($bin) && $bin !== '' && is_file($bin)) {
            return $bin;
        }

        $fallback = base_path('scripts/.venv/Scripts/python.exe');
        if (is_file($fallback)) {
            return $fallback;
        }

        return 'python';
    }
}
