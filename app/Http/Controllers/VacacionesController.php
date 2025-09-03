<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\TimeOffRequest;
use Symfony\Component\Process\Process;

class VacacionesController extends Controller
{
    public function __construct()
    {
        // permite acceso con cualquiera de los dos guards ya configurados
        $this->middleware('auth:web,organigrama');
    }

    /** Administración de Vacaciones */
public function admin()
{
    // Trae TODO y deja la paginación al DataTables del front
    $requests = \App\Models\TimeOffRequest::orderBy('created_at', 'desc')->get();

    // Listas únicas para filtros
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
        // Configurable por .env (opcional)
        $python = env('PYTHON_BIN', 'python');
        $script = base_path('scripts/etl_time_off_requests.py');

        // Ejecuta: python scripts/etl_time_off_requests.py
        $process = new Process([$python, $script], base_path(), null, null, 120);
        $process->run();

        if (!$process->isSuccessful()) {
            $err = trim($process->getErrorOutput() ?: $process->getOutput());
            return back()->with('error', 'ETL falló: ' . $err);
        }

        $out = trim($process->getOutput());
        return back()->with('status', $out ?: 'ETL ejecutado correctamente.');
    }

    /** Estatus de Vacaciones (vista para usuarios) */
    public function status()
    {
        return view('vacaciones.status', [
            'activePage' => 'vacaciones-status',
            'menuParent' => 'vacaciones',
            'titlePage'  => 'Estatus de Vacaciones',
        ]);
    }
}
