@php
  use Illuminate\Support\Str;

  // Colores RGB por estado (badge)
  function stateColors($state) {
    $s = strtoupper((string)$state);
    $map = [
      'APPROVED'    => ['rgb(46, 204, 113)',  'rgb(255,255,255)'], // verde
      'IN_PROGRESS' => ['rgb(52, 152, 219)',  'rgb(255,255,255)'], // azul
      'PENDING'     => ['rgb(243, 156, 18)',  'rgb(255,255,255)'], // naranja
      'REJECTED'    => ['rgb(231, 76, 60)',   'rgb(255,255,255)'], // rojo
      'CANCELLED'   => ['rgb(149, 165, 166)', 'rgb(255,255,255)'], // gris
    ];
    return $map[$s] ?? ['rgb(142, 68, 173)', 'rgb(255,255,255)']; // morado default
  }
@endphp

@extends('layouts.app', [
  'activePage' => $activePage ?? 'vacaciones-admin',
  'menuParent' => $menuParent ?? 'vacaciones',
  'titlePage'  => $titlePage  ?? 'Administración de Vacaciones'
])

@section('content')
<div class="content">
  <div class="container-fluid">
    <div class="card">
      <div class="card-header card-header-rose card-header-icon">
        <div class="card-icon">
          <i class="material-icons">beach_access</i>
        </div>
        <h4 class="card-title">{{ $titlePage }}</h4>
        <p class="card-category">Panel para gestionar solicitudes (ver y actualizar desde API)</p>
      </div>

      <div class="card-body">

        {{-- Flash messages --}}
        @if (session('status'))  <div class="alert alert-success">{{ session('status') }}</div> @endif
        @if (session('error'))   <div class="alert alert-danger">{{ session('error') }}</div> @endif

        {{-- ======================= CONTROLES (HEADER BONITO) ======================= --}}
        <div class="row align-items-stretch mb-3">
          {{-- Botón ETL --}}
          <div class="col-12 col-lg-3 mb-2 mb-lg-0">
            <form action="{{ route('vacaciones.runEtl') }}" method="POST" class="w-100">
              @csrf
              <button type="submit" class="btn btn-rose w-100" id="btn-run-etl">
                <i class="material-icons">sync</i> Actualizar desde API
              </button>
            </form>
          </div>

          {{-- Filtros (tarjeta compacta) --}}
          <div class="col-12 col-lg-9">
            <div class="card filters-card mb-0">
              <div class="card-body py-2">
                <div class="form-row">

                  {{-- Usuario --}}
                  <div class="form-group col-12 col-xl-5 mb-2">
                    <label for="filter-usuario" class="mb-1 text-muted"><small>Usuario</small></label>
                    <div class="input-group input-group-merged">
                      <div class="input-group-prepend">
                        <span class="input-group-text"><i class="material-icons">person_search</i></span>
                      </div>
                      <input type="text" id="filter-usuario" class="form-control" placeholder="Buscar usuario (internalId)">
                    </div>
                  </div>

                  {{-- Estado --}}
                  <div class="form-group col-6 col-md-3 col-xl-3 mb-2">
                    <label for="filter-estado" class="mb-1 text-muted"><small>Estado</small></label>
                    <select id="filter-estado" class="form-control form-control-rounded">
                      <option value="">Todos</option>
                      @foreach(($states ?? collect()) as $s)
                        <option value="{{ strtoupper($s) }}">{{ $s }}</option>
                      @endforeach
                    </select>
                  </div>

                  {{-- Política --}}
                  <div class="form-group col-6 col-md-3 col-xl-3 mb-2">
                    <label for="filter-politica" class="mb-1 text-muted"><small>Política</small></label>
                    <select id="filter-politica" class="form-control form-control-rounded">
                      <option value="">Todas</option>
                      @foreach(($policies ?? collect()) as $p)
                        <option value="{{ $p }}">{{ $p }}</option>
                      @endforeach
                    </select>
                  </div>

                  {{-- Reset --}}
                  <div class="form-group col-12 col-md-12 col-xl-1 d-flex mb-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm w-100 mt-auto" id="btn-reset-filtros" title="Limpiar filtros">
                      <i class="material-icons" style="font-size:18px;">clear</i>
                    </button>
                  </div>

                </div>
              </div>
            </div>
          </div>
        </div>
        {{-- ===================== FIN CONTROLES (HEADER BONITO) ===================== --}}

        {{-- TABLA --}}
        <div class="table-responsive">
          <table id="timeoff-table" class="table table-striped table-no-bordered table-hover" width="100%">
            <thead class="text-primary">
              <tr>
                <th>Request ID</th>   {{-- 0 --}}
                <th>Usuario</th>      {{-- 1 --}}
                <th>Política</th>     {{-- 2 --}}
                <th>Desde</th>        {{-- 3 --}}
                <th>Hasta</th>        {{-- 4 --}}
                <th>Días</th>         {{-- 5 --}}
                <th>Estado</th>       {{-- 6 (badge) --}}
                <th>Step</th>         {{-- 7 --}}
                <th>Creada</th>       {{-- 8 --}}
                <th>Resuelta</th>     {{-- 9 --}}
                <th>Descripción</th>  {{-- 10 --}}
              </tr>
            </thead>
            <tbody>
              @forelse($requests as $r)
                @php [$bg,$fg] = stateColors($r->state); @endphp
                <tr>
                  <td>{{ $r->request_id }}</td>
                  <td>{{ $r->issuer_employee_internal_id }}</td>
                  <td>{{ $r->policy_name }}</td>
                  <td>{{ optional($r->from_date)->format('Y-m-d') }}</td>
                  <td>{{ optional($r->to_date)->format('Y-m-d') }}</td>
                  <td>{{ $r->amount_requested }}</td>
                  <td>
                    <span class="badge state-badge"
                          data-state="{{ strtoupper($r->state) }}"
                          style="background-color: {{ $bg }}; color: {{ $fg }}; border-radius:12px; padding:6px 10px; font-weight:600;">
                      {{ strtoupper($r->state) }}
                    </span>
                  </td>
                  <td>{{ $r->step_state }}</td>
                  <td>{{ optional($r->created_at)->format('Y-m-d H:i') }}</td>
                  <td>{{ optional($r->resolution_date)->format('Y-m-d H:i') }}</td>
                  <td>{{ Str::limit($r->description, 120) }}</td>
                </tr>
              @empty
                <tr><td colspan="11" class="text-center text-muted">No hay solicitudes registradas.</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>

      </div>
    </div>
  </div>
</div>
@endsection

@push('css')
  {{-- DataTables CSS (Bootstrap 4) --}}
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap4.min.css">
  <style>
    /* Header limpio y alineado */
    .filters-card { border:1px solid rgba(0,0,0,.05); box-shadow:none; }
    .filters-card .card-body { padding-top:.75rem; padding-bottom:.75rem; }
    .card .card-body .form-row .form-group { margin-bottom: .5rem; }
    .input-group-text { background:#fff; }
    .form-control-rounded { border-radius: 30px; padding-left: 14px; }
    /* Ajustes de la tabla */
    table.dataTable tbody td { vertical-align: middle; }
  </style>
@endpush

@push('js')
  {{-- DataTables (debe cargarse después de jQuery del layout) --}}
  <script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap4.min.js"></script>

  <script>
    $(function () {
      // Asegura que DataTables esté disponible
      if (typeof $.fn.DataTable === 'undefined') {
        console.error('DataTables no está cargado.');
        return;
      }

      // --------- Filtro combinado (usuario/estado/política) ----------
      // Usamos ext.search para combinar los tres controles
      $.fn.dataTable.ext.search.push(function(settings, data) {
        const usuarioFilter  = ($('#filter-usuario').val() || '').toLowerCase();
        const estadoFilter   = ($('#filter-estado').val()   || '').toUpperCase();
        const politicaFilter = ($('#filter-politica').val() || '');

        const usuario  = (data[1] || '').toLowerCase();                 // col 1
        const politica = (data[2] || '');                               // col 2
        const estado   = (data[6] || '').replace(/<[^>]*>/g,'').toUpperCase().trim(); // col 6: limpia HTML del badge

        if (usuarioFilter && !usuario.includes(usuarioFilter)) return false;
        if (estadoFilter  && estado !== estadoFilter)          return false;
        if (politicaFilter && politica !== politicaFilter)     return false;
        return true;
      });

      // --------- Inicializa DataTable -----------
      const dt = $('#timeoff-table').DataTable({
        pagingType: "full_numbers",
        lengthMenu: [[10, 25, 50, -1],[10, 25, 50, "Todos"]],
        responsive: true,
        order: [[8, 'desc']], // Creada desc
        language: {
          search: "_INPUT_",
          searchPlaceholder: "Buscar en toda la tabla…",
          lengthMenu: "Mostrar _MENU_ registros",
          zeroRecords: "No se encontraron resultados",
          info: "Mostrando _START_ a _END_ de _TOTAL_ registros",
          infoEmpty: "Mostrando 0 a 0 de 0 registros",
          infoFiltered: "(filtrado de _MAX_ registros totales)",
          paginate: { first: "Primero", last: "Último", next: "Siguiente", previous: "Anterior" }
        },
        columnDefs: [
          {
            targets: 6, // Estado con badge
            render: function (data, type) {
              if (type === 'sort' || type === 'filter') {
                return String(data).replace(/<[^>]*>/g,'').trim(); // para ordenar/filtrar por texto, no HTML
              }
              return data;
            }
          },
          { targets: 10, orderable: false } // Descripción sin orden
        ]
      });

      // --------- Disparadores de filtros ----------
      $('#filter-usuario').on('input', function(){ dt.draw(); });
      $('#filter-estado').on('change', function(){ dt.draw(); });
      $('#filter-politica').on('change', function(){ dt.draw(); });

      // Reset de filtros
      $('#btn-reset-filtros').on('click', function(){
        $('#filter-usuario').val('');
        $('#filter-estado').val('');
        $('#filter-politica').val('');
        dt.search('').columns().search('');
        dt.draw();
      });

      // UX: botón ETL
      const btn = document.getElementById('btn-run-etl');
      if (btn) {
        btn.addEventListener('click', function() {
          btn.disabled = true;
          btn.innerHTML = '<i class="material-icons">hourglass_empty</i> Ejecutando...';
        });
      }
    });
  </script>
@endpush
