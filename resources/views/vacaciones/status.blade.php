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

        <div id="flash">
          @if (session('status'))  <div class="alert alert-success">{{ session('status') }}</div> @endif
          @if (session('error'))   <div class="alert alert-danger">{{ session('error') }}</div> @endif
        </div>

        {{-- CONTROLES --}}
        <div class="row align-items-stretch mb-3">
          {{-- Botón exportar (AJAX) --}}
          <div class="col-12 col-lg-3 mb-2 mb-lg-0">
            <form id="form-export-sap" action="{{ route('vacaciones.runExportSap') }}" method="POST" class="w-100">
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
                    <label class="mb-1 text-muted"><small>Usuario / Nombre</small></label>
                    <div class="input-group">
                      <div class="input-group-prepend">
                        <span class="input-group-text"><i class="material-icons">person_search</i></span>
                      </div>
                      <input type="text" id="filter-usuario" class="form-control" placeholder="Buscar usuario, correo o nombre">
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
                <th>Request ID</th>     {{-- 0 --}}
                <th>Usuario</th>        {{-- 1 (usuario_id + correo) --}}
                <th>Nombre</th>         {{-- 2 (issuer_full_name) --}}
                <th>CódigoCol</th>      {{-- 3 --}}
                <th>Política</th>       {{-- 4 --}}
                <th>Desde</th>          {{-- 5 --}}
                <th>Hasta</th>          {{-- 6 --}}
                <th>Días</th>           {{-- 7 --}}
                <th>Estado</th>         {{-- 8 badge --}}
                <th>Resultado</th>      {{-- 9 badge --}}
                <th>Creado</th>         {{-- 10 --}}
                <th>Mensaje</th>        {{-- 11 icono -> modal --}}
                <th>URL</th>            {{-- 12 (oculta) --}}
              </tr>
            </thead>
            <tbody>
              @if(($exports ?? collect())->count())
                @foreach($exports as $e)
                  @php
                    [$bgS, $fgS] = stateColors($e->processed_state);
                    [$bgR, $fgR, $labR] = resultColors($e->response_ok, $e->response_status);
                    $msg = $e->response_text ?? '';
                    $nombre = $e->issuer_full_name ?: Str::before($e->issuer_employee_internal_id, '@');
                  @endphp
                  <tr>
                    <td>{{ $e->request_id }}</td>
                    <td>
                      {{ $e->usuario_id ?: Str::before($e->issuer_employee_internal_id, '@') }}
                      <small class="text-muted d-block">{{ $e->issuer_employee_internal_id }}</small>
                    </td>
                    <td>{{ $nombre }}</td>
                    <td>{{ $e->codigo_col }}</td>
                    <td>{{ $e->policy_name }}</td>
                    <td>{{ optional($e->from_date)->format('Y-m-d') }}</td>
                    <td>{{ optional($e->to_date)->format('Y-m-d') }}</td>
                    <td>{{ $e->dias }}</td>
                    <td>
                      <span class="badge" style="background-color: {{ $bgS }}; color: {{ $fgS }}; border-radius:12px; padding:6px 10px; font-weight:600;">
                        {{ strtoupper($e->processed_state) }}
                      </span>
                    </td>
                    <td>
                      <span class="badge" style="background-color: {{ $bgR }}; color: {{ $fgR }}; border-radius:12px; padding:6px 10px; font-weight:600;">
                        {{ $labR }}
                      </span>
                    </td>
                    <td>{{ optional($e->created_at)->format('Y-m-d H:i') }}</td>
                    <td>
                      <button type="button"
                        class="btn btn-link btn-info btn-just-icon view-msg"
                        title="Ver mensaje"
                        data-message="{{ e($msg) }}">
                        <i class="material-icons">message</i>
                      </button>
                    </td>
                    <td>{{ $e->request_url }}</td> {{-- oculta por DataTables --}}
                  </tr>
                @endforeach
              @endif
              {{-- Importante: NO poner fila con colspan cuando no hay datos --}}
            </tbody>
          </table>
        </div>

      </div>
    </div>
  </div>
</div>

{{-- Modal para mensaje --}}
<div class="modal fade" id="msgModal" tabindex="-1" role="dialog" aria-labelledby="msgModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="msgModalLabel">Mensaje de SAP</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body" style="white-space:pre-wrap;">
        <pre id="modalMsgBody" class="mb-0" style="white-space:pre-wrap;"></pre>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-rose" data-dismiss="modal">Cerrar</button>
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
      // --- DataTable ---
      const dt = $('#sap-exports-table').DataTable({
        pagingType: "full_numbers",
        lengthMenu: [[10, 25, 50, -1],[10, 25, 50, "Todos"]],
        responsive: true,
        order: [[10, 'desc']], // "Creado" ahora es la columna 10
        language: {
          search: "_INPUT_",
          searchPlaceholder: "Buscar en toda la tabla…",
          lengthMenu: "Mostrar _MENU_ registros",
          zeroRecords: "No se encontraron resultados",
          info: "Mostrando _START_ a _END_ de _TOTAL_ registros",
          infoEmpty: "Mostrando 0 a 0 de 0 registros",
          infoFiltered: "(filtrado de _MAX_ registros totales)",
          emptyTable: "Aún no hay registros.", // evita fila con colspan
          paginate: { first: "Primero", last: "Último", next: "Siguiente", previous: "Anterior" }
        },
        columnDefs: [
          // Badges: usar texto para ordenar/filtrar
          { targets: [8,9], render: function(data, type) {
              if (type === 'sort' || type === 'filter') return String(data).replace(/<[^>]*>/g,'').trim();
              return data;
            }
          },
          // Ocultar URL (col 12) pero mantenerla en el DOM
          { targets: 12, visible: false, searchable: false },
          // Mensaje no ordenable
          { targets: 11, orderable: false }
        ]
      });

      // --- Filtros combinados ---
      $.fn.dataTable.ext.search.push(function(settings, data) {
        const userFilter   = ($('#filter-usuario').val() || '').toLowerCase();
        const stateFilter  = ($('#filter-estado').val()   || '').toUpperCase();
        const policyFilter = ($('#filter-politica').val() || '');

        const usuarioCell  = (data[1] || '').toLowerCase(); // usuario + correo
        const nombreCell   = (data[2] || '').toLowerCase(); // nombre
        const policyCell   = (data[4] || '');
        const procState    = (data[8] || '').replace(/<[^>]*>/g,'').toUpperCase().trim();

        // Usuario/Correo/Nombre
        if (userFilter && !(usuarioCell.includes(userFilter) || nombreCell.includes(userFilter))) return false;
        if (stateFilter && procState !== stateFilter) return false;
        if (policyFilter && policyCell !== policyFilter) return false;
        return true;
      });

      $('#filter-usuario').on('input',  () => dt.draw());
      $('#filter-estado').on('change', () => dt.draw());
      $('#filter-politica').on('change',() => dt.draw());
      $('#btn-reset-filtros').on('click', function(){
        $('#filter-usuario').val('');
        $('#filter-estado').val('');
        $('#filter-politica').val('');
        dt.search('').columns().search(''); dt.draw();
      });

      // --- Util: decodificar entidades HTML (&quot; -> ")
      function htmlDecode(str) {
        const txt = document.createElement('textarea');
        txt.innerHTML = (str || '');
        return txt.value;
      }

      // --- Pretty print del mensaje
      function formatMessage(raw) {
        const decoded = htmlDecode(raw).trim();

        // Caso 1: "ACCION=INS | {...json...}"
        const m = decoded.match(/^\s*ACCION\s*=\s*([A-Z]+)\s*\|\s*(\{[\s\S]*\})\s*$/i);
        if (m) {
          const accion = m[1].toUpperCase();
          const jsonStr = m[2];
          try {
            const obj = JSON.parse(jsonStr);
            const estatus = (obj.ESTATUS || '').toString().toUpperCase();
            const mensajes = Array.isArray(obj.MENSAJES) ? obj.MENSAJES : [];
            let out = `ACCION: ${accion}\nESTATUS: ${estatus}\n`;
            if (mensajes.length) {
              out += `MENSAJES:\n - ` + mensajes.join('\n - ');
            }
            return out;
          } catch (e) {
            // Si falla el parseo, devolvemos decodificado tal cual
            return decoded;
          }
        }

        // Caso 2: JSON puro
        try {
          const obj = JSON.parse(decoded);
          return JSON.stringify(obj, null, 2);
        } catch (e) {
          // No es JSON => retornar texto decodificado
          return decoded;
        }
      }

      // --- Modal Mensaje ---
      $(document).on('click', '.view-msg', function() {
        const raw = $(this).data('message') || '';
        const formatted = formatMessage(raw);
        $('#modalMsgBody').text(formatted);
        $('#msgModal').modal('show');
      });

      // --- Botón Exportar por AJAX ---
      $('#form-export-sap').on('submit', function(e) {
        e.preventDefault();
        const $btn = $('#btn-run-export');
        $btn.prop('disabled', true).html('<i class="material-icons">hourglass_empty</i> Ejecutando...');
        $.ajax({
          url: this.action,
          method: 'POST',
          data: $(this).serialize(),
          headers: {'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')},
          timeout: 130000
        })
        .done(function(resp){
          const msg = (resp && resp.message) ? resp.message : 'Exportación a SAP ejecutada.';
          $('#flash').html('<div class="alert alert-success">'+ msg +'</div>');
          location.reload(); // datos frescos
        })
        .fail(function(xhr){
          let msg = 'Exportación a SAP falló.';
          if (xhr.responseJSON && xhr.responseJSON.message) msg = xhr.responseJSON.message;
          else if (xhr.responseText) msg = xhr.responseText;
          $('#flash').html('<div class="alert alert-danger">'+ msg +'</div>');
        })
        .always(function(){
          $btn.prop('disabled', false).html('<i class="material-icons">send</i> Enviar pendientes a SAP');
        });
      });
    });
  </script>
@endpush
