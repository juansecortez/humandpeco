<?php

namespace App\Console\Commands;

use App\Services\Dc\DcTimeOffSapExportService;
use Illuminate\Console\Command;

class ExportDcPagoVacToSap extends Command
{
    protected $signature = 'sap:export-dc-pago-vac';

    protected $description = 'Exporta solicitudes Vacaciones DC a SAP (zws_pago_vac)';

    public function handle(DcTimeOffSapExportService $service): int
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit(600);
        }

        $this->info('Export SAP Vacaciones DC (pago vacacional)…');

        try {
            $stats = $service->runAutoExport();
        } catch (\Throwable $e) {
            $this->error('Exportación DC falló: ' . $e->getMessage());
            return self::FAILURE;
        }

        $this->table(
            ['Procesadas', 'Enviadas', 'Omitidas', 'Errores'],
            [[$stats['processed'], $stats['sent'], $stats['skipped'], $stats['errors']]]
        );

        return self::SUCCESS;
    }
}
