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

        <div id="flash">
          @if (session('status'))  <div class="alert alert-success">{{ session('status') }}</div> @endif
          @if (session('error'))   <div class="alert alert-danger">{{ session('error') }}</div> @endif
        </div>

        {{-- ======================= CONTROLES (HEADER) ======================= --}}
        <div class="row align-items-stretch mb-3">
          {{-- Botón ETL (AJAX) --}}
          <div class="col-12 col-lg-3 mb-2 mb-lg-0">
            <form id="form-run-etl" action="{{ route('vacaciones.runEtl') }}" method="POST" class="w-100">
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

                  {{-- Usuario / Nombre --}}
                  <div class="form-group col-12 col-xl-5 mb-2">
                    <label for="filter-usuario" class="mb-1 text-muted"><small>Usuario o Nombre</small></label>
                    <div class="input-group input-group-merged">
                      <div class="input-group-prepend">
                        <span class="input-group-text"><i class="material-icons">person_search</i></span>
                      </div>
                      <input type="text" id="filter-usuario" class="form-control" placeholder="Buscar usuario (internalId) o nombre">
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
        {{-- ===================== FIN CONTROLES ===================== --}}

        {{-- TABLA --}}
        <div class="table-responsive">
          <table id="timeoff-table" class="table table-striped table-no-bordered table-hover" width="100%">
            <thead class="text-primary">
              <tr>
                <th>Request ID</th>   {{-- 0 --}}
                <th>Usuario</th>      {{-- 1 --}}
                <th>Nombre</th>       {{-- 2 (NUEVA) --}}
                <th>Política</th>     {{-- 3 --}}
                <th>Desde</th>        {{-- 4 --}}
                <th>Hasta</th>        {{-- 5 --}}
                <th>Días</th>         {{-- 6 --}}
                <th>Estado</th>       {{-- 7 (badge) --}}
                <th>Step</th>         {{-- 8 --}}
                <th>Creada</th>       {{-- 9 --}}
                <th>Resuelta</th>     {{-- 10 --}}
                <th>Descripción</th>  {{-- 11 -> ícono modal --}}
              </tr>
            </thead>
            <tbody>
              @if(($requests ?? collect())->count())
                @foreach($requests as $r)
                  @php [$bg,$fg] = stateColors($r->state); @endphp
                  <tr>
                    <td>{{ $r->request_id }}</td>
                    <td>{{ $r->issuer_employee_internal_id }}</td>
                    <td>{{ $r->issuer_full_name }}</td> {{-- NUEVA --}}
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
                    <td class="text-center">
                      @php $desc = $r->description ?? ''; @endphp
                      <button type="button"
                              class="btn btn-link btn-info btn-just-icon view-desc"
                              title="{{ $desc ? 'Ver descripción' : 'Sin descripción' }}"
                              data-description="{{ e($desc) }}">
                        <i class="material-icons">message</i>
                      </button>
                    </td>
                  </tr>
                @endforeach
              @endif
              {{-- IMPORTANTE: no renderizamos fila "colspan" cuando está vacío --}}
            </tbody>
          </table>
        </div>

      </div>
    </div>
  </div>
</div>

{{-- Modal descripción --}}
<div class="modal fade" id="descModal" tabindex="-1" role="dialog" aria-labelledby="descModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="descModalLabel">Descripción de la solicitud</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <pre id="descModalBody" class="mb-0" style="white-space:pre-wrap;"></pre>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-rose" data-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>
@endsection

@push('css')
  {{-- DataTables CSS (Bootstrap 4) --}}
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap4.min.css">
  <style>
    .filters-card { border:1px solid rgba(0,0,0,.05); box-shadow:none; }
    .filters-card .card-body { padding-top:.75rem; padding-bottom:.75rem; }
    .card .card-body .form-row .form-group { margin-bottom: .5rem; }
    .input-group-text { background:#fff; }
    .form-control-rounded { border-radius: 30px; padding-left: 14px; }
    table.dataTable tbody td { vertical-align: middle; }
  </style>
@endpush

@push('js')
  {{-- DataTables (después de jQuery del layout) --}}
  <script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap4.min.js"></script>

  <script>
    $(function () {
      if (typeof $.fn.DataTable === 'undefined') {
        console.error('DataTables no está cargado.');
        return;
      }

      // --- Filtro combinado (usuario/estado/política) ---
      $.fn.dataTable.ext.search.push(function(settings, data) {
        const usuarioFilter  = ($('#filter-usuario').val() || '').toLowerCase();
        const estadoFilter   = ($('#filter-estado').val()   || '').toUpperCase();
        const politicaFilter = ($('#filter-politica').val() || '');

        // Indices actualizados por la nueva columna "Nombre"
        const usuario  = (data[1] || '').toLowerCase(); // col 1 -> internalId
        const nombre   = (data[2] || '').toLowerCase(); // col 2 -> full_name (NUEVA)
        const politica = (data[3] || '');               // col 3
        const estado   = (data[7] || '').replace(/<[^>]*>/g,'').toUpperCase().trim(); // col 7

        // Usuario o Nombre
        if (usuarioFilter && !(usuario.includes(usuarioFilter) || nombre.includes(usuarioFilter))) return false;

        if (estadoFilter  && estado !== estadoFilter)      return false;
        if (politicaFilter && politica !== politicaFilter) return false;
        return true;
      });

      // --- DataTable ---
      const dt = $('#timeoff-table').DataTable({
        pagingType: "full_numbers",
        lengthMenu: [[10, 25, 50, -1],[10, 25, 50, "Todos"]],
        responsive: true,
        order: [[9, 'desc']], // Creada desc (col 9) -- ACTUALIZADO
        language: {
          search: "_INPUT_",
          searchPlaceholder: "Buscar en toda la tabla…",
          lengthMenu: "Mostrar _MENU_ registros",
          zeroRecords: "No se encontraron resultados",
          info: "Mostrando _START_ a _END_ de _TOTAL_ registros",
          infoEmpty: "Mostrando 0 a 0 de 0 registros",
          infoFiltered: "(filtrado de _MAX_ registros totales)",
          emptyTable: "No hay solicitudes registradas.", // << evita fila con colspan
          paginate: { first: "Primero", last: "Último", next: "Siguiente", previous: "Anterior" }
        },
        columnDefs: [
          {
            targets: 7, // estado (badge) -- ACTUALIZADO
            render: function (data, type) {
              if (type === 'sort' || type === 'filter') {
                return String(data).replace(/<[^>]*>/g,'').trim();
              }
              return data;
            }
          },
          { targets: 11, orderable: false } // ícono de descripción -- ACTUALIZADO
        ]
      });

      // --- Disparadores filtros ---
      $('#filter-usuario').on('input', function(){ dt.draw(); });
      $('#filter-estado').on('change', function(){ dt.draw(); });
      $('#filter-politica').on('change', function(){ dt.draw(); });
      $('#btn-reset-filtros').on('click', function(){
        $('#filter-usuario').val('');
        $('#filter-estado').val('');
        $('#filter-politica').val('');
        dt.search('').columns().search(''); dt.draw();
      });

      // --- Modal descripción ---
      $(document).on('click', '.view-desc', function() {
        let msg = $(this).data('description') || '';
        try { const parsed = JSON.parse(msg); msg = JSON.stringify(parsed, null, 2); } catch (e) {}
        $('#descModalBody').text(msg || '—');
        $('#descModal').modal('show');
      });

      // --- Botón ETL por AJAX ---
      $('#form-run-etl').on('submit', function(e){
        e.preventDefault();
        const $btn = $('#btn-run-etl');
        $btn.prop('disabled', true).html('<i class="material-icons">hourglass_empty</i> Ejecutando...');
        $.ajax({
          url: this.action,
          method: 'POST',
          data: $(this).serialize(),
          headers: {'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')},
          timeout: 180000
        })
        .done(function(resp){
          const msg = (resp && resp.message) ? resp.message : 'ETL ejecutado correctamente.';
          $('#flash').html('<div class="alert alert-success">'+ msg +'</div>');
          location.reload();
        })
        .fail(function(xhr){
          let msg = 'ETL falló.';
          if (xhr.responseJSON && xhr.responseJSON.message) msg = xhr.responseJSON.message;
          else if (xhr.responseText) msg = xhr.responseText;
          $('#flash').html('<div class="alert alert-danger">'+ msg +'</div>');
        })
        .always(function(){
          $btn.prop('disabled', false).html('<i class="material-icons">sync</i> Actualizar desde API');
        });
      });
    });
  </script>
@endpush
