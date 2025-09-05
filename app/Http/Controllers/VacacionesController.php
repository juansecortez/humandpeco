<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Symfony\Component\Process\Process;

use App\Models\TimeOffRequest;
use App\Models\SapTimeOffExport; // <-- añade este modelo

class VacacionesController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:web,organigrama');
    }

    /** Administración de Vacaciones (ya lo tienes) */
    public function admin()
    {
        $requests = TimeOffRequest::orderBy('created_at', 'desc')->get();
        $states   = $requests->pluck('state')->filter()->unique()->values();
        $policies = $requests->pluck('policy_name')->filter()->unique()->values();

        return view('vacaciones.admin', [
            'activePage' => 'vacaciones-admin',
            'menuParent' => 'vacaciones',
            'titlePage'  => 'Administración de Vacaciones',
            'requests'   => $requests,
            'states'     => $states,
            'policies'   => $policies,
        ]);
    }

    public function runEtl(Request $request)
    {
        $python = env('PYTHON_BIN', 'python');
        $script = base_path('scripts/etl_time_off_requests.py');

        $process = new Process([$python, $script], base_path(), null, null, 180);
        $process->run();

        if (!$process->isSuccessful()) {
            $err = trim($process->getErrorOutput() ?: $process->getOutput());
            return back()->with('error', 'ETL falló: ' . $err);
        }
        $out = trim($process->getOutput());
        return back()->with('status', $out ?: 'ETL ejecutado correctamente.');
    }

    /** Estatus de envíos a SAP */
    public function status()
    {
        $exports  = SapTimeOffExport::orderBy('created_at', 'desc')->get();

        // Filtros
        $procStates = $exports->pluck('processed_state')->filter()->unique()->values(); // APPROVED / CANCELLED
        $policies   = $exports->pluck('policy_name')->filter()->unique()->values();

        return view('vacaciones.status', [
            'activePage'  => 'vacaciones-status',
            'menuParent'  => 'vacaciones',
            'titlePage'   => 'Estatus de Vacaciones (SAP)',
            'exports'     => $exports,
            'procStates'  => $procStates,
            'policies'    => $policies,
        ]);
    }

    /** Ejecuta script de exportación a SAP (pendientes) */
    public function runExportSap(Request $request)
    {
        $python = env('PYTHON_BIN', 'python');
        $script = base_path('scripts/export_time_off_to_sap.py');

        $process = new Process([$python, $script], base_path(), null, null, 240);
        $process->run();

        if (!$process->isSuccessful()) {
            $err = trim($process->getErrorOutput() ?: $process->getOutput());
            return back()->with('error', 'Exportación a SAP falló: ' . $err);
        }
        $out = trim($process->getOutput());
        return back()->with('status', $out ?: 'Exportación a SAP ejecutada.');
    }
}
