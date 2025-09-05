@php
  use Illuminate\Support\Str;

  function stateColors($state) {
    $s = strtoupper((string)$state);
    $map = [
      'APPROVED'  => ['rgb(46, 204, 113)',  'rgb(255,255,255)'],
      'CANCELLED' => ['rgb(149, 165, 166)', 'rgb(255,255,255)'],
    ];
    return $map[$s] ?? ['rgb(142, 68, 173)', 'rgb(255,255,255)'];
  }
  function resultColors($ok, $status) {
    if ($status === null) return ['rgb(149,165,166)', 'rgb(255,255,255)', 'PENDIENTE'];
    if ((int)$ok === 1)   return ['rgb(46, 204, 113)', 'rgb(255,255,255)', 'OK'];
    return ['rgb(231, 76, 60)', 'rgb(255,255,255)', 'ERROR'];
  }
@endphp

@extends('layouts.app', [
  'activePage' => $activePage ?? 'vacaciones-status',
  'menuParent' => $menuParent ?? 'vacaciones',
  'titlePage'  => $titlePage  ?? 'Estatus de Vacaciones (SAP)'
])

@section('content')
<div class="content">
  <div class="container-fluid">
    <div class="card">
      <div class="card-header card-header-rose card-header-icon">
        <div class="card-icon"><i class="material-icons">compare_arrows</i></div>
        <h4 class="card-title">{{ $titlePage }}</h4>
        <p class="card-category">Log de exportaciones a SAP (pendientes, éxitos y errores).</p>
      </div>

      <div class="card-body">

        {{-- Flash --}}
        @if (session('status'))  <div class="alert alert-success">{{ session('status') }}</div> @endif
        @if (session('error'))   <div class="alert alert-danger">{{ session('error') }}</div> @endif

        {{-- CONTROLES --}}
        <div class="row align-items-stretch mb-3">
          {{-- Botón exportar --}}
          <div class="col-12 col-lg-3 mb-2 mb-lg-0">
            <form action="{{ route('vacaciones.runExportSap') }}" method="POST" class="w-100">
              @csrf
              <button type="submit" class="btn btn-rose w-100" id="btn-run-export">
                <i class="material-icons">send</i> Enviar pendientes a SAP
              </button>
            </form>
          </div>

          {{-- Filtros --}}
          <div class="col-12 col-lg-9">
            <div class="card filters-card mb-0">
              <div class="card-body py-2">
                <div class="form-row">
                  <div class="form-group col-12 col-xl-5 mb-2">
                    <label class="mb-1 text-muted"><small>Usuario</small></label>
                    <div class="input-group">
                      <div class="input-group-prepend">
                        <span class="input-group-text"><i class="material-icons">person_search</i></span>
                      </div>
                      <input type="text" id="filter-usuario" class="form-control" placeholder="Buscar usuario (usuario_id o correo)">
                    </div>
                  </div>

                  <div class="form-group col-6 col-md-3 col-xl-3 mb-2">
                    <label class="mb-1 text-muted"><small>Estado procesado</small></label>
                    <select id="filter-estado" class="form-control">
                      <option value="">Todos</option>
                      @foreach(($procStates ?? collect()) as $s)
                        <option value="{{ strtoupper($s) }}">{{ strtoupper($s) }}</option>
                      @endforeach
                    </select>
                  </div>

                  <div class="form-group col-6 col-md-3 col-xl-3 mb-2">
                    <label class="mb-1 text-muted"><small>Política</small></label>
                    <select id="filter-politica" class="form-control">
                      <option value="">Todas</option>
                      @foreach(($policies ?? collect()) as $p)
                        <option value="{{ $p }}">{{ $p }}</option>
                      @endforeach
                    </select>
                  </div>

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

        {{-- TABLA --}}
        <div class="table-responsive">
          <table id="sap-exports-table" class="table table-striped table-no-bordered table-hover" width="100%">
            <thead class="text-primary">
              <tr>
                <th>ID</th>
                <th>Request ID</th>
                <th>Usuario</th>
                <th>CódigoCol</th>
                <th>Política</th>
                <th>Clave</th>
                <th>Infotipo</th>
                <th>Desde</th>
                <th>Hasta</th>
                <th>Días</th>
                <th>Estado</th>
                <th>HTTP</th>
                <th>Resultado</th>
                <th>Creado</th>
                <th>Respondido</th>
                <th>Mensaje</th>
                <th>URL</th>
              </tr>
            </thead>
            <tbody>
              @forelse($exports as $e)
                @php
                  [$bgS, $fgS] = stateColors($e->processed_state);
                  [$bgR, $fgR, $labR] = resultColors($e->response_ok, $e->response_status);
                @endphp
                <tr>
                  <td>{{ $e->id }}</td>
                  <td>{{ $e->request_id }}</td>
                  <td>
                    {{ $e->usuario_id ?: Str::before($e->issuer_employee_internal_id, '@') }}
                    <small class="text-muted d-block">{{ $e->issuer_employee_internal_id }}</small>
                  </td>
                  <td>{{ $e->codigo_col }}</td>
                  <td>{{ $e->policy_name }}</td>
                  <td>{{ $e->clave }}</td>
                  <td>{{ $e->infotipo }}</td>
                  <td>{{ optional($e->from_date)->format('Y-m-d') }}</td>
                  <td>{{ optional($e->to_date)->format('Y-m-d') }}</td>
                  <td>{{ $e->dias }}</td>
                  <td>
                    <span class="badge" style="background-color: {{ $bgS }}; color: {{ $fgS }}; border-radius:12px; padding:6px 10px; font-weight:600;">
                      {{ strtoupper($e->processed_state) }}
                    </span>
                  </td>
                  <td>{{ $e->response_status ?? '—' }}</td>
                  <td>
                    <span class="badge" style="background-color: {{ $bgR }}; color: {{ $fgR }}; border-radius:12px; padding:6px 10px; font-weight:600;">
                      {{ $labR }}
                    </span>
                  </td>
                  <td>{{ optional($e->created_at)->format('Y-m-d H:i') }}</td>
                  <td>{{ optional($e->responded_at)->format('Y-m-d H:i') ?: '—' }}</td>
                  <td title="{{ $e->response_text }}">{{ Str::limit($e->response_text, 80) }}</td>
                  <td>
                    @if($e->request_url)
                      <a href="{{ $e->request_url }}" target="_blank" rel="noopener" class="btn btn-link btn-info btn-just-icon">
                        <i class="material-icons">open_in_new</i>
                      </a>
                    @endif
                  </td>
                </tr>
              @empty
                <tr><td colspan="16" class="text-center text-muted">Aún no hay registros.</td></tr>
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
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap4.min.css">
  <style>
    .filters-card { border:1px solid rgba(0,0,0,.05); box-shadow:none; }
    .filters-card .card-body { padding-top:.75rem; padding-bottom:.75rem; }
    .card .card-body .form-row .form-group { margin-bottom: .5rem; }
    .input-group-text { background:#fff; }
    table.dataTable tbody td { vertical-align: middle; }
  </style>
@endpush

@push('js')
  <script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap4.min.js"></script>

  <script>
    $(function () {
      if (typeof $.fn.DataTable === 'undefined') {
        console.error('DataTables no está cargado.');
        return;
      }

      // Filtro combinado (usuario / estado / política)
      $.fn.dataTable.ext.search.push(function(settings, data) {
        const userFilter   = ($('#filter-usuario').val() || '').toLowerCase();
        const stateFilter  = ($('#filter-estado').val()   || '').toUpperCase();
        const policyFilter = ($('#filter-politica').val() || '');

        const usuarioCell  = ((data[2] || '') + ' ' + (data[2] || '')).toLowerCase(); // usuario + correo mostrados
        const policyCell   = (data[4] || '');
        const procState    = (data[10] || '').replace(/<[^>]*>/g,'').toUpperCase().trim();

        if (userFilter && !usuarioCell.includes(userFilter)) return false;
        if (stateFilter && procState !== stateFilter) return false;
        if (policyFilter && policyCell !== policyFilter) return false;
        return true;
      });

      const dt = $('#sap-exports-table').DataTable({
        pagingType: "full_numbers",
        lengthMenu: [[10, 25, 50, -1],[10, 25, 50, "Todos"]],
        responsive: true,
        order: [[13, 'desc']], // Creado desc
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
          { targets: [15,16], orderable: false }, // Mensaje, URL
          { targets: [10,12], render: function(data, type) { // badges -> texto para ordenar/filtrar
              if (type === 'sort' || type === 'filter') return String(data).replace(/<[^>]*>/g,'').trim();
              return data;
            }
          }
        ]
      });

      // Disparadores
      $('#filter-usuario').on('input', function(){ dt.draw(); });
      $('#filter-estado').on('change', function(){ dt.draw(); });
      $('#filter-politica').on('change', function(){ dt.draw(); });

      $('#btn-reset-filtros').on('click', function(){
        $('#filter-usuario').val('');
        $('#filter-estado').val('');
        $('#filter-politica').val('');
        dt.search('').columns().search(''); dt.draw();
      });

      // UX botón
      const btn = document.getElementById('btn-run-export');
      if (btn) {
        btn.addEventListener('click', function() {
          btn.disabled = true;
          btn.innerHTML = '<i class="material-icons">hourglass_empty</i> Ejecutando...';
        });
      }
    });
  </script>
@endpush
