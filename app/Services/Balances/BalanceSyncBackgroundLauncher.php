<?php

namespace App\Services\Balances;

/**
 * Arranca balances:sync-worker en segundo plano (sin bloquear la petición web).
 */
class BalanceSyncBackgroundLauncher
{
    public function launch(int $runId): void
    {
        $php    = $this->phpBinary();
        $artisan = base_path('artisan');
        $log    = storage_path('logs/balance-sync-worker.log');

        $cmd = sprintf('"%s" "%s" balances:sync-worker %d', $php, $artisan, $runId);

        if (PHP_OS_FAMILY === 'Windows') {
            pclose(popen('cmd /c start /B "" ' . $cmd . ' >> "' . $log . '" 2>&1', 'r'));

            return;
        }

        exec($cmd . ' >> ' . escapeshellarg($log) . ' 2>&1 &');
    }

    private function phpBinary(): string
    {
        $configured = config('scripts.php_bin');

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        $binary = PHP_BINARY;
        if (PHP_OS_FAMILY === 'Windows' && str_ends_with(strtolower($binary), 'php-cgi.exe')) {
            return substr($binary, 0, -strlen('php-cgi.exe')) . 'php.exe';
        }

        return $binary;
    }
}
