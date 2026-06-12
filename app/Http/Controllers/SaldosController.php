<?php

namespace App\Http\Controllers;

use App\Models\BalanceSyncItem;
use App\Services\Balances\OrganigramaBalanceRepository;
use App\Services\Balances\BalanceSyncService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SaldosController extends Controller
{
    public function __construct(
        private OrganigramaBalanceRepository $organigrama,
    ) {
        $this->middleware('auth:web,organigrama');
    }

    public function index(Request $request)
    {
        $employees = collect($this->organigrama->employees());

        $stats = BalanceSyncItem::query()
            ->select([
                'codigo_col',
                DB::raw('COUNT(*) as total_items'),
                DB::raw("SUM(CASE WHEN status = 'applied' THEN 1 ELSE 0 END) as applied_count"),
                DB::raw("SUM(CASE WHEN status = 'simulated' THEN 1 ELSE 0 END) as simulated_count"),
                DB::raw("SUM(CASE WHEN status = 'error' THEN 1 ELSE 0 END) as error_count"),
                DB::raw('MAX(created_at) as last_sync_at'),
            ])
            ->whereNotNull('codigo_col')
            ->groupBy('codigo_col')
            ->get()
            ->keyBy('codigo_col');

        $personas = $employees->map(function (array $emp) use ($stats) {
            $s = $stats->get($emp['codigo_col']);

            return array_merge($emp, [
                'has_sync'        => $s !== null,
                'total_items'     => (int) ($s->total_items ?? 0),
                'applied_count'   => (int) ($s->applied_count ?? 0),
                'simulated_count' => (int) ($s->simulated_count ?? 0),
                'error_count'     => (int) ($s->error_count ?? 0),
                'last_sync_at'    => $s?->last_sync_at,
            ]);
        })->sortBy('nombre', SORT_NATURAL | SORT_FLAG_CASE)->values();

        $withApplied = $personas->where('applied_count', '>', 0)->count();
        $withSync    = $personas->where('has_sync', true)->count();
        $withoutSync = $personas->count() - $withSync;

        return view('saldos.index', [
            'activePage'  => 'saldos-sync',
            'menuParent'  => 'saldos',
            'titlePage'   => 'Sincronización de saldos',
            'personas'    => $personas,
            'totalCount'  => $personas->count(),
            'withApplied' => $withApplied,
            'withSync'    => $withSync,
            'withoutSync' => $withoutSync,
            'highlight'   => trim((string) $request->query('codigo', '')),
        ]);
    }

    public function personLog(string $codigo)
    {
        $items = BalanceSyncItem::query()
            ->with('run:id,dry_run,triggered_by,started_at')
            ->where('codigo_col', $codigo)
            ->orderByDesc('created_at')
            ->limit(200)
            ->get();

        $employee = collect($this->organigrama->employees([$codigo]))->first();

        return response()->json([
            'codigo'   => $codigo,
            'nombre'   => $employee['nombre'] ?? ($items->first()?->full_name),
            'correo'   => $employee['correo'] ?? null,
            'tipo'     => $employee['person_type'] ?? ($items->first()?->person_type),
            'items'    => $items->map(fn ($it) => [
                'id'             => $it->id,
                'run_id'         => $it->run_id,
                'dry_run'        => (bool) optional($it->run)->dry_run,
                'triggered_by'   => optional($it->run)->triggered_by,
                'run_started_at' => optional($it->run)->started_at?->format('d/m/Y H:i'),
                'policy_label'   => $it->policy_label,
                'sap_concept'    => $it->sap_concept,
                'sap_value'      => $it->sap_value,
                'humand_before'  => $it->humand_before,
                'target_value'   => $it->target_value,
                'cycle_title'    => $it->cycle_title,
                'status'         => $it->status,
                'message'        => $it->message,
                'created_at'     => optional($it->created_at)->format('d/m/Y H:i'),
            ]),
        ]);
    }

    public function run(Request $request, BalanceSyncService $service)
    {
        @set_time_limit(900);

        $apply   = filter_var($request->input('apply', false), FILTER_VALIDATE_BOOLEAN);
        $codigos = array_filter(array_map('trim', explode(',', (string) $request->input('codigo', ''))), fn ($c) => $c !== '');
        $dryRun  = !$apply;

        $user = optional($request->user())->name
            ?? optional($request->user())->getAuthIdentifier()
            ?? 'web';

        try {
            $run = $service->run($dryRun, $codigos, (string) $user);
        } catch (\Throwable $e) {
            $msg = 'Sincronización falló: ' . $e->getMessage();
            if ($request->ajax()) {
                return response()->json(['ok' => false, 'message' => $msg], 500);
            }
            return back()->with('error', $msg);
        }

        $modo = $dryRun ? 'Simulación' : 'Ajustes aplicados';
        $msg  = "{$modo} (run #{$run->id}): {$run->total_items} ítems · {$run->applied} a ajustar/aplicados · {$run->unchanged} sin cambio · {$run->skipped} omitidos · {$run->errors} errores.";

        $redirect = route('saldos.index', $codigos !== [] ? ['codigo' => $codigos[0]] : []);

        if ($request->ajax()) {
            return response()->json([
                'ok'       => true,
                'message'  => $msg,
                'redirect' => $redirect,
            ]);
        }

        return redirect($redirect)->with('status', $msg);
    }
}
