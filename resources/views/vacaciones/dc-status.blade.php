@php
  use Illuminate\Support\Str;

  $exports = $exports ?? collect();
  $opcionLabels = $opcionLabels ?? [];
  $okCount      = $exports->filter(fn ($e) => (int) $e->response_ok === 1)->count();
  $errorCount   = $exports->filter(fn ($e) => $e->response_status !== null && (int) $e->response_ok !== 1)->count();
  $pendingCount = $exports->filter(fn ($e) => $e->response_status === null)->count();
  $totalCount   = $exports->count();
@endphp

@extends('layouts.app', [
  'activePage' => $activePage ?? 'solicitudes-dc-vacaciones-status',
  'menuParent' => $menuParent ?? 'solicitudes-dc',
  'titlePage'  => $titlePage ?? 'Estatus Vacaciones DC',
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
          <div class="card-footer"><div class="stats"><i class="material-icons">storage</i> Envíos zws_pago_vac</div></div>
        </div>
      </div>
      <div class="col-lg-3 col-md-6 col-sm-6">
        <div class="card card-stats">
          <div class="card-header card-header-success card-header-icon">
            <div class="card-icon"><i class="material-icons">check</i></div>
            <p class="card-category">Exitosos</p>
            <h3 class="card-title">{{ number_format($okCount) }}</h3>
          </div>
          <div class="card-footer"><div class="stats"><i class="material-icons">done</i> type=S</div></div>
        </div>
      </div>
      <div class="col-lg-3 col-md-6 col-sm-6">
        <div class="card card-stats">
          <div class="card-header card-header-danger card-header-icon">
            <div class="card-icon"><i class="material-icons">error_outline</i></div>
            <p class="card-category">Con error</p>
            <h3 class="card-title">{{ number_format($errorCount) }}</h3>
          </div>
          <div class="card-footer"><div class="stats"><i class="material-icons">warning</i> Revisar mensaje</div></div>
        </div>
      </div>
      <div class="col-lg-3 col-md-6 col-sm-6">
        <div class="card card-stats">
          <div class="card-header card-header-warning card-header-icon">
            <div class="card-icon"><i class="material-icons">schedule</i></div>
            <p class="card-category">Pendientes</p>
            <h3 class="card-title">{{ number_format($pendingCount) }}</h3>
          </div>
          <div class="card-footer"><div class="stats"><i class="material-icons">hourglass_empty</i> Sin respuesta</div></div>
        </div>
      </div>
    </div>

    <div class="row">
      <div class="col-md-12">
        <div class="card">
          <div class="card-header card-header-rose card-header-icon">
            <div class="card-icon"><i class="material-icons">compare_arrows</i></div>
            <h4 class="card-title">{{ $titlePage }}</h4>
            <p class="card-category">Historial POST zws_pago_vac · Vacaciones DC (opción 1/2/3)</p>
          </div>

          <div class="card-body">
            <div class="row solicitudes-table-toolbar align-items-end mb-4">
              <div class="col-lg-4 col-md-5 mb-3 mb-md-0">
                <label for="filter-usuario">Buscar empleado</label>
                <div class="input-group">
                  <div class="input-group-prepend"><span class="input-group-text"><i class="material-icons">search</i></span></div>
                  <input type="text" id="filter-usuario" class="form-control" placeholder="Nombre o código">
                </div>
              </div>
              <div class="col-lg-7 col-md-12 text-lg-right">
                <label class="d-none d-lg-block">&nbsp;</label>
                <form id="form-export-dc-sap" action="{{ route('solicitudes.dc.runExportSap') }}" method="POST">
                  @csrf
                  <button type="submit" class="btn btn-rose btn-lg" id="btn-export-dc">
                    <i class="material-icons">send</i> Enviar automáticos a SAP
                  </button>
                </form>
              </div>
            </div>

            <div class="material-datatables">
              <div class="table-responsive">
                <table id="dc-exports-table" class="table table-striped table-no-bordered table-hover" cellspacing="0" width="100%">
                  <thead class="text-primary">
                    <tr>
                      <th>ID sol.</th>
                      <th>Empleado</th>
                      <th>Código</th>
                      <th>Opción</th>
                      <th>Fecha inicio</th>
                      <th>Origen</th>
                      <th>Resultado</th>
                      <th>Enviado</th>
                      <th class="text-center">Detalle</th>
                    </tr>
                  </thead>
                  <tbody>
                    @foreach($exports as $e)
                      @php
                        $msg = $e->response_text ?? '';
                        $ok = (int) ($e->response_ok ?? 0) === 1;
                        $hasResp = $e->response_status !== null;
                        if (!$hasResp) { $resClass = 'badge-warning'; $resLab = 'PENDIENTE'; }
                        elseif ($ok) { $resClass = 'badge-success'; $resLab = 'OK'; }
                        else { $resClass = 'badge-danger'; $resLab = 'ERROR'; }
                      @endphp
                      <tr>
                        <td><small>#{{ $e->request_id }}</small></td>
                        <td><strong>{{ $e->issuer_full_name ?: '—' }}</strong></td>
                        <td>{{ $e->codigo_col ?: '—' }}</td>
                        <td>
                          <span class="badge badge-info">{{ $e->opcion }}</span>
                          <small class="d-block">{{ $opcionLabels[$e->opcion] ?? '' }}</small>
                        </td>
                        <td>{{ optional($e->fecha_inicio)->format('d/m/Y') }}</td>
                        <td><span class="badge badge-secondary">{{ strtoupper($e->source) }}</span></td>
                        <td><span class="badge {{ $resClass }}">{{ $resLab }}</span></td>
                        <td><small>{{ optional($e->created_at)->format('d/m/Y H:i') }}</small></td>
                        <td class="text-center td-actions">
                          <button type="button" class="btn btn-info btn-link btn-just-icon view-msg" data-message="{{ e($msg) }}" title="Ver respuesta">
                            <i class="material-icons">receipt_long</i>
                          </button>
                        </td>
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
        <h5 class="modal-title">Respuesta SAP</h5>
        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <div class="modal-body"><pre id="modalMsgBody" class="mb-0" style="white-space:pre-wrap;font-family:inherit;"></pre></div>
      <div class="modal-footer"><button type="button" class="btn btn-rose" data-dismiss="modal">Cerrar</button></div>
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
      const dt = $('#dc-exports-table').DataTable({
        pagingType: 'full_numbers',
        lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
        responsive: true,
        order: [[7, 'desc']],
        language: {
          search: '_INPUT_', searchPlaceholder: 'Buscar…', lengthMenu: 'Mostrar _MENU_',
          zeroRecords: 'Sin coincidencias', info: '_START_–_END_ de _TOTAL_',
          infoEmpty: '0 registros', infoFiltered: '(de _MAX_)', emptyTable: 'Sin envíos DC registrados.',
          paginate: { first: '«', last: '»', next: '›', previous: '‹' }
        },
        columnDefs: [{ targets: 8, orderable: false, searchable: false }]
      });

      $.fn.dataTable.ext.search.push(function (settings, data) {
        const q = ($('#filter-usuario').val() || '').toLowerCase();
        if (q && !(data[1].toLowerCase().includes(q) || data[2].toLowerCase().includes(q))) return false;
        return true;
      });

      $('#filter-usuario').on('input', () => dt.draw());

      $(document).on('click', '.view-msg', function () {
        let txt = $('<textarea/>').html($(this).data('message') || '').text().trim();
        try { txt = JSON.stringify(JSON.parse(txt), null, 2); } catch (e) {}
        $('#modalMsgBody').text(txt);
        $('#msgModal').modal('show');
      });

      $('#form-export-dc-sap').on('submit', function (e) {
        e.preventDefault();
        const $btn = $('#btn-export-dc');
        $btn.prop('disabled', true).html('<i class="material-icons">hourglass_empty</i> Enviando…');
        $.ajax({
          url: this.action, method: 'POST', data: $(this).serialize(),
          headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
          timeout: 300000
        })
        .done(() => location.reload())
        .fail(xhr => alert(xhr.responseJSON?.message || 'Error al exportar.'))
        .always(() => $btn.prop('disabled', false).html('<i class="material-icons">send</i> Enviar automáticos a SAP'));
      });
    });
  </script>
@endpush
