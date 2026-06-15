<?php



namespace App\Http\Controllers;



use App\Services\HumandTimeOffEtlService;
use App\Services\Dc\DcTimeOffSapExportService;
use App\Models\SapDcPagoVacExport;

use Illuminate\Http\Request;

use Symfony\Component\Process\Process;

use Symfony\Component\Process\Exception\ProcessTimedOutException;



use App\Models\TimeOffRequest;

use App\Models\SapTimeOffExport;



class VacacionesController extends Controller

{

    public function __construct()

    {

        $this->middleware('auth:web,organigrama');

    }



    /** @deprecated Redirige a LEGO FC */

    public function admin()

    {

        return redirect()->route('solicitudes.admin', ['group' => 'fc', 'policy' => 'lego']);

    }



    public function adminPolicy(string $group, string $policy)

    {

        $config = $this->policyConfig($group, $policy);



        $requests = TimeOffRequest::query()

            ->forPolicyType($config['policy_type_id'], $config['policy_name'])

            ->orderBy('created_at', 'desc')

            ->get();



        $states = $requests->pluck('state')->filter()->unique()->values();

        $sapExportMode = $config['sap_export'] ?? ($group === 'fc' ? 'date_range' : null);
        $isDcPagoVac   = $sapExportMode === 'pago_vac';
        $isDcAnticipos = $group === 'dc' && $sapExportMode === 'date_range';

        $dcExports  = collect();
        $pendingDc  = collect();
        $sapExports = collect();

        if ($isDcPagoVac) {
            $dcExports = SapDcPagoVacExport::query()
                ->whereIn('request_id', $requests->pluck('request_id'))
                ->orderByDesc('id')
                ->get()
                ->unique('request_id')
                ->keyBy('request_id');

            $pendingDc = app(DcTimeOffSapExportService::class)->pendingManualRequests();
        } elseif ($isDcAnticipos) {
            $sapExports = SapTimeOffExport::query()
                ->whereIn('request_id', $requests->pluck('request_id'))
                ->orderByDesc('id')
                ->get()
                ->unique('request_id')
                ->keyBy('request_id');
        }

        return view('vacaciones.admin', [

            'activePage'       => $config['active_page'],

            'menuParent'       => $config['menu_parent'],

            'titlePage'        => $config['group_label'] . ' — ' . $config['label'],

            'policyGroup'      => $group,

            'policySlug'       => $policy,

            'policyLabel'      => $config['label'],

            'requests'         => $requests,

            'states'           => $states,

            'hidePolicyFilter' => true,

            'etlEnabled'       => $config['etl_enabled'],

            'isDcPagoVac'      => $isDcPagoVac,

            'isDcAnticipos'    => $isDcAnticipos,

            'dcExports'        => $dcExports,

            'sapExports'       => $sapExports,

            'pendingDc'        => $pendingDc,

            'opcionLabels'     => config('dc_sap_export.opcion_labels', []),

        ]);

    }



    public function runEtl(Request $request, string $group, string $policy)

    {

        $config = $this->policyConfig($group, $policy);



        if (!$config['etl_enabled']) {

            $msg = 'ETL no disponible para esta política.';

            if ($request->ajax()) {

                return response()->json(['ok' => false, 'message' => $msg], 422);

            }

            return back()->with('error', $msg);

        }



        $timeout = config('scripts.etl_timeout', 600);

        $this->extendPhpRuntime($timeout + 60);



        try {

            $etl = app(HumandTimeOffEtlService::class);

            $count = $etl->run($config['policy_type_id']);

            $message = "ETL OK - policyTypeIds={$config['policy_type_id']} - desde {$etl->activeSinceDate()} - filas procesadas: {$count}";

        } catch (\Throwable $e) {

            $err = $e->getMessage();

            if ($request->ajax()) {

                return response()->json(['ok' => false, 'message' => 'ETL falló: ' . $err], 500);

            }

            return back()->with('error', 'ETL falló: ' . $err);

        }



        if ($request->ajax()) {

            return response()->json(['ok' => true, 'message' => $message]);

        }

        return back()->with('status', $message);

    }



    /** Estatus de envíos a SAP (solo políticas FC) */

    public function status()

    {

        $fcNames = $this->fcPolicyNames();
        $fcIds   = $this->fcPolicyTypeIds();

        $exports = SapTimeOffExport::query()
            ->forPolicies($fcIds, $fcNames, ['%Supervisor%'])
            ->orderByDesc('created_at')
            ->get();



        $procStates = $exports->pluck('processed_state')->filter()->unique()->values();

        $policies   = $exports->pluck('policy_name')->filter()->unique()->values();



        return view('vacaciones.status', [

            'activePage'  => 'solicitudes-fc-status',

            'menuParent'  => 'solicitudes-fc',

            'titlePage'   => 'Estatus de envíos (SAP)',

            'exports'     => $exports,

            'procStates'  => $procStates,

            'policies'    => $policies,

        ]);

    }



    public function runExportSap(Request $request)

    {

        return $this->runDateRangeExport($request, 'fc');

    }



    /** Estatus envíos Vacaciones DC (zws_pago_vac, opción 1/2/3). */

    public function dcVacacionesStatus()

    {

        $policyId = $this->vacacionesDcPolicyTypeId();

        $exports = SapDcPagoVacExport::query()
            ->where('policy_type_id', $policyId)
            ->orderBy('created_at', 'desc')
            ->get();

        return view('vacaciones.dc-status', [

            'activePage'  => 'solicitudes-dc-vacaciones-status',

            'menuParent'  => 'solicitudes-dc',

            'titlePage'   => 'Estatus Vacaciones DC',

            'exports'     => $exports,

            'opcionLabels'=> config('dc_sap_export.opcion_labels', []),

        ]);

    }



    /** @deprecated Usar dcVacacionesStatus */
    public function dcStatus()
    {
        return $this->dcVacacionesStatus();
    }



    /** Estatus envíos Anticipos DC (fecha inicio/fin, mismo flujo que vacaciones FC). */

    public function dcAnticiposStatus()

    {

        $policyId   = (int) config('time_off_policies.dc.anticipos-vacaciones.policy_type_id', 308355);
        $policyName = trim((string) config('time_off_policies.dc.anticipos-vacaciones.policy_name', 'ANTICIPOS DE VACACIONES'));

        $exports = SapTimeOffExport::query()
            ->forPolicies([$policyId], [$policyName], ['%Anticipo%'])
            ->orderByDesc('created_at')
            ->get();

        $procStates = $exports->pluck('processed_state')->filter()->unique()->values();

        return view('vacaciones.anticipos-dc-status', [

            'activePage'  => 'solicitudes-dc-anticipos-status',

            'menuParent'  => 'solicitudes-dc',

            'titlePage'   => 'Estatus Anticipos DC',

            'exports'     => $exports,

            'procStates'  => $procStates,

        ]);

    }



    public function runExportAnticiposDcSap(Request $request)

    {

        return $this->runDateRangeExport($request, 'anticipos');

    }



    /** Envía a SAP las solicitudes DC con opción 1/2/3 en description. */

    public function runExportDcSap(Request $request, DcTimeOffSapExportService $service)

    {

        $this->extendPhpRuntime(300);

        try {

            $stats = $service->runAutoExport();

        } catch (\Throwable $e) {

            $msg = 'Exportación DC falló: ' . $e->getMessage();

            if ($request->ajax()) {

                return response()->json(['ok' => false, 'message' => $msg], 500);

            }

            return back()->with('error', $msg);

        }

        $msg = "Exportación DC: {$stats['sent']} enviadas · {$stats['skipped']} omitidas · {$stats['errors']} errores.";

        if ($request->ajax()) {

            return response()->json(['ok' => true, 'message' => $msg]);

        }

        return back()->with('status', $msg);

    }



    /** Envío manual con opción elegida por el equipo. */

    public function exportDcRequest(Request $request, int $requestId, DcTimeOffSapExportService $service)

    {

        $request->validate(['opcion' => 'required|in:1,2,3']);

        $timeOff = TimeOffRequest::find($requestId);

        if (!$timeOff) {

            return response()->json(['ok' => false, 'message' => 'Solicitud no encontrada.'], 404);

        }

        if (!in_array((int) $timeOff->policy_type_id, $service->dcPolicyTypeIds(), true)) {

            return response()->json(['ok' => false, 'message' => 'No es una solicitud Vacaciones DC (pago vacacional).'], 422);

        }

        try {

            $export = $service->exportRequest($timeOff, $request->input('opcion'), 'manual');

        } catch (\Throwable $e) {

            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);

        }

        $msg = $export->response_ok

            ? "Solicitud #{$requestId} enviada a SAP (opción {$export->opcion})."

            : "SAP respondió error: " . mb_substr((string) $export->response_text, 0, 200);

        return response()->json([

            'ok'      => (bool) $export->response_ok,

            'message' => $msg,

        ], $export->response_ok ? 200 : 422);

    }



    private function policyConfig(string $group, string $policy): array

    {

        if (!in_array($group, ['fc', 'dc'], true)) {

            abort(404);

        }



        $config = config("time_off_policies.{$group}.{$policy}");

        if (!$config) {

            abort(404, 'Política no configurada.');

        }



        return array_merge($config, [

            'group'       => $group,

            'slug'        => $policy,

            'menu_parent' => $group === 'fc' ? 'solicitudes-fc' : 'solicitudes-dc',

            'group_label' => $group === 'fc' ? 'Solicitudes FC' : 'Solicitudes DC',

            'etl_enabled' => true,

        ]);

    }



    /** @return list<string> */
    private function fcPolicyNames(): array
    {
        return collect(config('time_off_policies.fc', []))
            ->pluck('policy_name')
            ->map(fn ($n) => trim((string) $n))
            ->values()
            ->all();
    }

    /** @return list<int> */
    private function fcPolicyTypeIds(): array
    {
        return collect(config('time_off_policies.fc', []))
            ->pluck('policy_type_id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    private function anticiposDcPolicyName(): string
    {
        return strtoupper(trim((string) config(
            'time_off_policies.dc.anticipos-vacaciones.policy_name',
            'ANTICIPOS DE VACACIONES'
        )));
    }

    private function vacacionesDcPolicyTypeId(): int
    {
        return (int) config('time_off_policies.dc.vacaciones-dc.policy_type_id', 179204);
    }

    private function runDateRangeExport(Request $request, string $scope)
    {
        $timeout = 120;
        $this->extendPhpRuntime($timeout + 30);

        $python = $this->pythonBinary();
        $script = base_path('scripts/export_time_off_to_sap.py');

        $process = $this->runPythonProcess([$python, $script, $scope], $timeout);
        $process->setIdleTimeout(30);

        try {
            $process->run();
        } catch (ProcessTimedOutException $e) {
            $kind = $e->isGeneralTimeout() ? 'tiempo total agotado' : 'sin respuesta (idle timeout)';
            return back()->with('error', "Exportación a SAP: $kind. Verifica conectividad/credenciales con SAP.");
        }

        if (!$process->isSuccessful()) {
            $err = $this->processTextOutput($process);
            return back()->with('error', 'Exportación a SAP falló: ' . $err);
        }

        $out = $this->processTextOutput($process, stdoutOnly: true);
        return back()->with('status', $out ?: 'Exportación a SAP ejecutada.');
    }



    private function runPythonProcess(array $cmd, int $timeout): Process

    {

        return new Process($cmd, base_path(), null, null, $timeout);

    }



    private function extendPhpRuntime(int $seconds): void

    {

        if (function_exists('set_time_limit')) {

            @set_time_limit($seconds);

        }

    }



    private function processTextOutput(Process $process, bool $stdoutOnly = false): string

    {

        $text = $stdoutOnly

            ? $process->getOutput()

            : ($process->getErrorOutput() ?: $process->getOutput());



        return $this->sanitizeUtf8(trim($text));

    }



    private function sanitizeUtf8(string $text): string

    {

        if ($text === '') {

            return '';

        }



        if (mb_check_encoding($text, 'UTF-8')) {

            return $text;

        }



        $fromWin = @iconv('CP1252', 'UTF-8//IGNORE', $text);

        if ($fromWin !== false && mb_check_encoding($fromWin, 'UTF-8')) {

            return $fromWin;

        }



        $clean = @iconv('UTF-8', 'UTF-8//IGNORE', $text);



        return $clean !== false ? $clean : '';

    }



    private function pythonBinary(): string

    {

        $bin = config('scripts.python_bin');



        if ($bin && is_file($bin)) {

            return $bin;

        }



        $fallback = base_path('scripts/.venv/Scripts/python.exe');

        if (is_file($fallback)) {

            return $fallback;

        }



        return PHP_OS_FAMILY === 'Windows' ? 'py' : 'python3';

    }

}


