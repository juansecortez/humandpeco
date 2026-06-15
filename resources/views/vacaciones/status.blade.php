@php
  use Illuminate\Support\Str;

  $exports = $exports ?? collect();
  $okCount      = $exports->filter(fn ($e) => (int) $e->response_ok === 1)->count();
  $errorCount   = $exports->filter(fn ($e) => $e->response_status !== null && (int) $e->response_ok !== 1)->count();
  $pendingCount = $exports->filter(fn ($e) => $e->response_status === null)->count();
  $totalCount   = $exports->count();
@endphp

@extends('layouts.app', [
  'activePage' => $activePage ?? 'solicitudes-fc-status',
  'menuParent' => $menuParent ?? 'solicitudes-fc',
  'titlePage'  => $titlePage ?? 'Estatus SAP',
])

@section('content')
<div class="content">
  <div class="container-fluid">

    <div class="row">
      <div class="col-lg-3 col-md-6 col-sm-6">
        <div class="card card-stats">
          <div class="card-header card-header-info card-header-icon">
            <div class="card-icon"><i class="material-icons">cloud_upload</i></div>
            <p class="card-category">Registros</p>
            <h3 class="card-title">{{ number_format($totalCount) }}</h3>
          </div>
          <div class="card-footer">
            <div class="stats"><i class="material-icons">storage</i> Envíos a SAP</div>
          </div>
        </div>
      </div>
      <div class="col-lg-3 col-md-6 col-sm-6">
        <div class="card card-stats">
          <div class="card-header card-header-success card-header-icon">
            <div class="card-icon"><i class="material-icons">check</i></div>
            <p class="card-category">Exitosos</p>
            <h3 class="card-title">{{ number_format($okCount) }}</h3>
          </div>
          <div class="card-footer">
            <div class="stats"><i class="material-icons">done</i> Respuesta OK</div>
          </div>
        </div>
      </div>
      <div class="col-lg-3 col-md-6 col-sm-6">
        <div class="card card-stats">
          <div class="card-header card-header-danger card-header-icon">
            <div class="card-icon"><i class="material-icons">error_outline</i></div>
            <p class="card-category">Con error</p>
            <h3 class="card-title">{{ number_format($errorCount) }}</h3>
          </div>
          <div class="card-footer">
            <div class="stats"><i class="material-icons">warning</i> Revisar mensaje</div>
          </div>
        </div>
      </div>
      <div class="col-lg-3 col-md-6 col-sm-6">
        <div class="card card-stats">
          <div class="card-header card-header-warning card-header-icon">
            <div class="card-icon"><i class="material-icons">schedule</i></div>
            <p class="card-category">Pendientes</p>
            <h3 class="card-title">{{ number_format($pendingCount) }}</h3>
          </div>
          <div class="card-footer">
            <div class="stats"><i class="material-icons">hourglass_empty</i> Sin respuesta</div>
          </div>
        </div>
      </div>
    </div>

    <div class="row">
      <div class="col-md-12">
        <div class="card">
          <div class="card-header card-header-rose card-header-icon">
            <div class="card-icon"><i class="material-icons">compare_arrows</i></div>
            <h4 class="card-title">{{ $titlePage }}</h4>
            <p class="card-category">Historial de exportaciones a SAP</p>
          </div>

          <div class="card-body">

            <div id="flash" class="mb-3">
              @if (session('status'))
                <div class="alert alert-success alert-dismissible fade show">{{ session('status') }}
                  <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
                </div>
              @endif
              @if (session('error'))
                <div class="alert alert-danger alert-dismissible fade show">{{ session('error') }}
                  <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
                </div>
              @endif
            </div>

            <div class="row solicitudes-table-toolbar align-items-end mb-4">
              <div class="col-lg-3 col-md-4 mb-3 mb-md-0">
                <label for="filter-usuario">Buscar empleado</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="material-icons">search</i></span>
                  </div>
                  <input type="text" id="filter-usuario" class="form-control" placeholder="Usuario o nombre">
                </div>
              </div>
              <div class="col-lg-2 col-md-3 mb-3 mb-md-0">
                <label for="filter-estado">Estado</label>
                <select id="filter-estado" class="form-control">
                  <option value="">Todos</option>
                  @foreach(($procStates ?? collect()) as $s)
                    <option value="{{ strtoupper($s) }}">{{ strtoupper($s) }}</option>
                  @endforeach
                </select>
              </div>
              <div class="col-lg-2 col-md-3 mb-3 mb-md-0">
                <label for="filter-politica">Política</label>
                <select id="filter-politica" class="form-control">
                  <option value="">Todas</option>
                  @foreach(($policies ?? collect()) as $p)
                    <option value="{{ $p }}">{{ $p }}</option>
                  @endforeach
                </select>
              </div>
              <div class="col-lg-2 col-md-2 mb-3 mb-md-0">
                <label>&nbsp;</label>
                <button type="button" class="btn btn-default btn-block" id="btn-reset-filtros">
                  <i class="material-icons" style="font-size:18px;vertical-align:middle;">refresh</i> Limpiar
                </button>
              </div>
              <div class="col-lg-3 col-md-12 text-lg-right">
                <label class="d-none d-lg-block">&nbsp;</label>
                <form id="form-export-sap" action="{{ $exportAction ?? route('vacaciones.runExportSap') }}" method="POST">
                  @csrf
                  <button type="submit" class="btn btn-rose btn-block btn-lg" id="btn-run-export">
                    <i class="material-icons">send</i> Enviar a SAP
                  </button>
                </form>
                <p class="text-muted small mt-2 mb-0 text-lg-right">{{ $exportInfo ?? 'Incluye Vacaciones FC, LEGO y Supervisores. Anticipos DC → Estatus Anticipos DC.' }}</p>
              </div>
            </div>

            <div class="material-datatables">
              <div class="table-responsive">
                <table id="sap-exports-table" class="table table-striped table-no-bordered table-hover" cellspacing="0" width="100%">
                  <thead class="text-primary">
                    <tr>
                      <th>ID</th>
                      <th>Empleado</th>
                      <th>Nombre</th>
                      <th>No. personal</th>
                      <th>Política</th>
                      <th>Desde</th>
                      <th>Hasta</th>
                      <th>Días</th>
                      <th>Estado</th>
                      <th>Resultado</th>
                      <th>Enviado</th>
                      <th class="text-center">SAP</th>
                      <th class="d-none">URL</th>
                    </tr>
                  </thead>
                  <tbody>
                    @foreach($exports as $e)
                      @php
                        $msg = $e->response_text ?? '';
                        $nombre = $e->issuer_full_name ?: Str::before($e->issuer_employee_internal_id, '@');
                        $ok = (int) ($e->response_ok ?? 0) === 1;
                        $hasResp = $e->response_status !== null;
                        if (!$hasResp) { $resClass = 'badge-warning'; $resLab = 'PENDIENTE'; }
                        elseif ($ok) { $resClass = 'badge-success'; $resLab = 'OK'; }
                        else { $resClass = 'badge-danger'; $resLab = 'ERROR'; }
                      @endphp
                      <tr>
                        <td><small class="text-muted">#{{ $e->request_id }}</small></td>
                        <td>
                          <strong>{{ $e->usuario_id ?: Str::before($e->issuer_employee_internal_id, '@') }}</strong>
                          <span class="table-email">{{ $e->issuer_employee_internal_id }}</span>
                        </td>
                        <td>{{ $nombre }}</td>
                        <td>{{ $e->codigo_col ?: '—' }}</td>
                        <td>{{ $e->policy_name }}</td>
                        <td>{{ optional($e->from_date)->format('d/m/Y') }}</td>
                        <td>{{ optional($e->to_date)->format('d/m/Y') }}</td>
                        <td>{{ $e->dias }}</td>
                        <td>@include('vacaciones.partials.state-badge', ['state' => $e->processed_state])</td>
                        <td><span class="badge {{ $resClass }}">{{ $resLab }}</span></td>
                        <td><small>{{ optional($e->created_at)->format('d/m/Y H:i') }}</small></td>
                        <td class="text-center td-actions">
                          <button type="button" class="btn btn-info btn-link btn-just-icon view-msg" data-message="{{ e($msg) }}" title="Ver respuesta">
                            <i class="material-icons">receipt_long</i>
                          </button>
                        </td>
                        <td class="d-none">{{ $e->request_url }}</td>
                      </tr>
                    @endforeach
                  </tbody>
                </table>
              </div>
            </div>

          </div>
        </div>
      </div>
    </div>

  </div>
</div>

<div class="modal fade" id="msgModal" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Respuesta de SAP</h5>
        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <div class="modal-body"><pre id="modalMsgBody" class="mb-0" style="white-space:pre-wrap;font-family:inherit;"></pre></div>
      <div class="modal-footer">
        <button type="button" class="btn btn-rose" data-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>
@endsection

@push('css')
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap4.min.css">
  @include('vacaciones.partials.solicitudes-styles')
@endpush

@push('js')
  <script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap4.min.js"></script>
  <script>
    $(function () {
      const dt = $('#sap-exports-table').DataTable({
        pagingType: 'full_numbers',
        lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
        responsive: true,
        order: [[10, 'desc']],
        language: {
          search: '_INPUT_', searchPlaceholder: 'Buscar…', lengthMenu: 'Mostrar _MENU_',
          zeroRecords: 'Sin coincidencias', info: '_START_–_END_ de _TOTAL_',
          infoEmpty: '0 registros', infoFiltered: '(de _MAX_)', emptyTable: 'Sin envíos registrados.',
          paginate: { first: '«', last: '»', next: '›', previous: '‹' }
        },
        columnDefs: [
          { targets: [8, 9], render: (d, t) => (t === 'sort' || t === 'filter') ? String(d).replace(/<[^>]*>/g, '').trim() : d },
          { targets: 12, visible: false, searchable: false },
          { targets: 11, orderable: false, searchable: false }
        ]
      });

      $.fn.dataTable.ext.search.push(function (settings, data) {
        const q = ($('#filter-usuario').val() || '').toLowerCase();
        const st = ($('#filter-estado').val() || '').toUpperCase();
        const pol = ($('#filter-politica').val() || '');
        if (q && !(data[1].toLowerCase().includes(q) || data[2].toLowerCase().includes(q))) return false;
        if (st && data[8].replace(/<[^>]*>/g, '').toUpperCase().trim() !== st) return false;
        if (pol && data[4] !== pol) return false;
        return true;
      });

      $('#filter-usuario').on('input', () => dt.draw());
      $('#filter-estado, #filter-politica').on('change', () => dt.draw());
      $('#btn-reset-filtros').on('click', () => {
        $('#filter-usuario, #filter-estado, #filter-politica').val('');
        dt.search('').columns().search('');
        dt.draw();
      });

      function formatMessage(raw) {
        const txt = $('<textarea/>').html(raw || '').text().trim();
        const m = txt.match(/^\s*ACCION\s*=\s*([A-Z]+)\s*\|\s*(\{[\s\S]*\})\s*$/i);
        if (m) {
          try {
            const obj = JSON.parse(m[2]);
            let out = 'ACCION: ' + m[1].toUpperCase() + '\nESTATUS: ' + (obj.ESTATUS || '') + '\n';
            if (Array.isArray(obj.MENSAJES) && obj.MENSAJES.length) out += 'MENSAJES:\n - ' + obj.MENSAJES.join('\n - ');
            return out;
          } catch (e) { return txt; }
        }
        try { return JSON.stringify(JSON.parse(txt), null, 2); } catch (e) { return txt; }
      }

      $(document).on('click', '.view-msg', function () {
        $('#modalMsgBody').text(formatMessage($(this).data('message')));
        $('#msgModal').modal('show');
      });

      $('#form-export-sap').on('submit', function (e) {
        e.preventDefault();
        const $btn = $('#btn-run-export');
        $btn.prop('disabled', true).html('<i class="material-icons">hourglass_empty</i> Enviando…');
        $.ajax({
          url: this.action, method: 'POST', data: $(this).serialize(),
          headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
          timeout: 130000
        })
        .done(() => location.reload())
        .fail(xhr => {
          let msg = xhr.responseJSON?.message || 'Error al exportar.';
          $('#flash').html('<div class="alert alert-danger alert-dismissible fade show">' + msg + '<button type="button" class="close" data-dismiss="alert"><span>&times;</span></button></div>');
        })
        .always(() => $btn.prop('disabled', false).html('<i class="material-icons">send</i> Enviar a SAP'));
      });
    });
  </script>
@endpush
