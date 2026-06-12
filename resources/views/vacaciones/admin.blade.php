@php
  $hidePolicy = !empty($hidePolicyFilter);
  $countByState = ($requests ?? collect())->groupBy(fn ($r) => strtoupper((string) $r->state))->map->count();
  $totalCount    = ($requests ?? collect())->count();
  $approvedCount = $countByState->get('APPROVED', 0);
  $pendingCount  = $countByState->get('IN_PROGRESS', 0) + $countByState->get('PENDING', 0);
  $otherCount    = max(0, $totalCount - $approvedCount - $pendingCount);

  $isDcPagoVac   = $isDcPagoVac ?? false;
  $isDcAnticipos = $isDcAnticipos ?? false;
  $dcExports     = $dcExports ?? collect();
  $sapExports    = $sapExports ?? collect();
  $opcionLabels  = $opcionLabels ?? [];
  $parser = app(\App\Services\Dc\DcOpcionParser::class);

  $col = ($isDcPagoVac && $hidePolicy)
    ? ['usuario' => 1, 'nombre' => 2, 'estado' => 6, 'creada' => 10, 'desc' => 12]
    : ($isDcAnticipos && $hidePolicy
      ? ['usuario' => 1, 'nombre' => 2, 'estado' => 6, 'creada' => 9, 'desc' => 11]
      : [
          'usuario' => 1,
          'nombre'  => 2,
          'estado'  => $hidePolicy ? 6 : 7,
          'creada'  => $hidePolicy ? 8 : 9,
          'desc'    => $hidePolicy ? 10 : 11,
        ]);

  $policyIcons = [
    'lego'                 => 'extension',
    'vacaciones-fc'        => 'beach_access',
    'supervisores'         => 'supervisor_account',
    'anticipos-vacaciones' => 'event_available',
    'vacaciones-dc'        => 'beach_access',
  ];
  $policyIcon = $policyIcons[$policySlug ?? ''] ?? 'assignment';
  $etlEnabled = $etlEnabled ?? true;
@endphp

@extends('layouts.app', [
  'activePage' => $activePage ?? 'solicitudes-fc-lego',
  'menuParent' => $menuParent ?? 'solicitudes-fc',
  'titlePage'  => $titlePage ?? 'Solicitudes',
])

@section('content')
<div class="content">
  <div class="container-fluid">

    {{-- KPIs --}}
    <div class="row">
      <div class="col-lg-3 col-md-6 col-sm-6">
        <div class="card card-stats">
          <div class="card-header card-header-info card-header-icon">
            <div class="card-icon"><i class="material-icons">assignment</i></div>
            <p class="card-category">Total</p>
            <h3 class="card-title">{{ number_format($totalCount) }}</h3>
          </div>
          <div class="card-footer">
            <div class="stats"><i class="material-icons">update</i> {{ $policyLabel ?? 'Humand' }}</div>
          </div>
        </div>
      </div>
      <div class="col-lg-3 col-md-6 col-sm-6">
        <div class="card card-stats">
          <div class="card-header card-header-success card-header-icon">
            <div class="card-icon"><i class="material-icons">check_circle</i></div>
            <p class="card-category">Aprobadas</p>
            <h3 class="card-title">{{ number_format($approvedCount) }}</h3>
          </div>
          <div class="card-footer">
            <div class="stats"><i class="material-icons">done_all</i> Listas para SAP</div>
          </div>
        </div>
      </div>
      <div class="col-lg-3 col-md-6 col-sm-6">
        <div class="card card-stats">
          <div class="card-header card-header-warning card-header-icon">
            <div class="card-icon"><i class="material-icons">hourglass_empty</i></div>
            <p class="card-category">En trámite</p>
            <h3 class="card-title">{{ number_format($pendingCount) }}</h3>
          </div>
          <div class="card-footer">
            <div class="stats"><i class="material-icons">schedule</i> Pendientes de flujo</div>
          </div>
        </div>
      </div>
      <div class="col-lg-3 col-md-6 col-sm-6">
        <div class="card card-stats">
          <div class="card-header card-header-danger card-header-icon">
            <div class="card-icon"><i class="material-icons">block</i></div>
            <p class="card-category">Otras</p>
            <h3 class="card-title">{{ number_format($otherCount) }}</h3>
          </div>
          <div class="card-footer">
            <div class="stats"><i class="material-icons">info</i> Rechazadas / canceladas</div>
          </div>
        </div>
      </div>
    </div>

    {{-- Tabla principal --}}
    <div class="row">
      <div class="col-md-12">
        <div class="card">
          <div class="card-header card-header-rose card-header-icon">
            <div class="card-icon"><i class="material-icons">{{ $policyIcon }}</i></div>
            <h4 class="card-title">{{ $titlePage }}</h4>
            <p class="card-category">Sincronización filtrada por política · ID {{ config("time_off_policies.{$policySlug}.policy_type_id") ?? '—' }}</p>
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

            {{-- Toolbar --}}
            <div class="row solicitudes-table-toolbar align-items-end mb-4">
              <div class="col-lg-4 col-md-5 mb-3 mb-md-0">
                <label for="filter-usuario">Buscar empleado</label>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text"><i class="material-icons">search</i></span>
                  </div>
                  <input type="text" id="filter-usuario" class="form-control" placeholder="Correo, usuario o nombre">
                </div>
              </div>
              <div class="col-lg-3 col-md-4 mb-3 mb-md-0">
                <label for="filter-estado">Estado</label>
                <select id="filter-estado" class="form-control">
                  <option value="">Todos los estados</option>
                  @foreach(($states ?? collect()) as $s)
                    <option value="{{ strtoupper($s) }}">{{ strtoupper($s) }}</option>
                  @endforeach
                </select>
              </div>
              <div class="col-lg-2 col-md-3 mb-3 mb-md-0">
                <label>&nbsp;</label>
                <button type="button" class="btn btn-default btn-block" id="btn-reset-filtros">
                  <i class="material-icons" style="font-size:18px;vertical-align:middle;">refresh</i> Limpiar
                </button>
              </div>
              <div class="col-lg-3 col-md-12 text-lg-right">
                <label class="d-none d-lg-block">&nbsp;</label>
                @if($etlEnabled)
                <form id="form-run-etl" action="{{ route('solicitudes.runEtl', ['group' => $policyGroup ?? 'fc', 'policy' => $policySlug ?? 'lego']) }}" method="POST" class="mb-2">
                  @csrf
                  <button type="submit" class="btn btn-rose btn-block btn-lg" id="btn-run-etl">
                    <i class="material-icons">sync</i> Sincronizar {{ $policyLabel }}
                  </button>
                </form>
                @endif
                @if($isDcPagoVac)
                <form id="form-export-dc-sap" action="{{ route('solicitudes.dc.runExportSap') }}" method="POST">
                  @csrf
                  <button type="submit" class="btn btn-info btn-block btn-lg" id="btn-export-dc-sap">
                    <i class="material-icons">send</i> Enviar automáticos a SAP
                  </button>
                </form>
                <p class="text-muted small mt-2 mb-0">Auto: description = 1, 2 o 3. Inválidas → pendientes abajo.</p>
                @elseif($isDcAnticipos)
                <form id="form-export-anticipos-sap" action="{{ route('solicitudes.dc.runExportAnticiposSap') }}" method="POST">
                  @csrf
                  <button type="submit" class="btn btn-info btn-block btn-lg" id="btn-export-anticipos-sap">
                    <i class="material-icons">send</i> Enviar a SAP
                  </button>
                </form>
                <p class="text-muted small mt-2 mb-0">Envío con fecha inicio, fin y días (igual que vacaciones FC).</p>
                @elseif(!$etlEnabled)
                <button type="button" class="btn btn-default btn-block btn-lg" disabled>
                  <i class="material-icons">schedule</i> Sincronizar (no disponible)
                </button>
                @endif
              </div>
            </div>

            @if($isDcPagoVac)
              @include('vacaciones.partials.dc-pending-sap', ['pendingDc' => $pendingDc ?? collect(), 'opcionLabels' => $opcionLabels])
            @endif

            <div class="material-datatables">
              <div class="table-responsive">
                <table id="timeoff-table" class="table table-striped table-no-bordered table-hover" cellspacing="0" width="100%">
                  <thead class="text-primary">
                    <tr>
                      <th>ID</th>
                      <th>Empleado</th>
                      <th>Nombre</th>
                      @unless($hidePolicy)<th>Política</th>@endunless
                      <th>Desde</th>
                      <th>Hasta</th>
                      <th>Días</th>
                      <th>Estado</th>
                      <th>Paso</th>
                      @if($isDcPagoVac)
                      <th>Opción</th>
                      <th>SAP</th>
                      @elseif($isDcAnticipos)
                      <th>SAP</th>
                      @endif
                      <th>Creada</th>
                      <th>Resuelta</th>
                      <th class="text-center">Detalle</th>
                    </tr>
                  </thead>
                  <tbody>
                    @foreach(($requests ?? collect()) as $r)
                      @php
                        $opcion = $isDcPagoVac ? $parser->parse($r->description) : null;
                        $dcExp = $isDcPagoVac ? ($dcExports->get($r->request_id) ?? null) : null;
                        $sapExp = $isDcAnticipos ? ($sapExports->get($r->request_id) ?? null) : null;
                      @endphp
                      <tr>
                        <td><small class="text-muted">#{{ $r->request_id }}</small></td>
                        <td><span class="table-email" title="{{ $r->issuer_employee_internal_id }}">{{ $r->issuer_employee_internal_id }}</span></td>
                        <td>{{ $r->issuer_full_name ?: '—' }}</td>
                        @unless($hidePolicy)<td>{{ $r->policy_name }}</td>@endunless
                        <td>{{ optional($r->from_date)->format('d/m/Y') }}</td>
                        <td>{{ optional($r->to_date)->format('d/m/Y') }}</td>
                        <td>{{ $r->amount_requested }}</td>
                        <td>@include('vacaciones.partials.state-badge', ['state' => $r->state])</td>
                        <td><small>{{ $r->step_state ?: '—' }}</small></td>
                        @if($isDcPagoVac)
                        <td>
                          @if($opcion)
                            <span class="badge badge-info" title="{{ $opcionLabels[$opcion] ?? '' }}">{{ $opcion }}</span>
                          @else
                            <span class="badge badge-warning" title="Descripción inválida para auto-envío">—</span>
                          @endif
                        </td>
                        <td>
                          @if($dcExp)
                            @if($dcExp->response_ok)
                              <span class="badge badge-success">OK</span>
                            @else
                              <span class="badge badge-danger">ERROR</span>
                            @endif
                            <small class="d-block text-muted">op {{ $dcExp->opcion }}</small>
                          @else
                            <span class="text-muted">—</span>
                          @endif
                        </td>
                        @elseif($isDcAnticipos)
                        <td>
                          @if($sapExp)
                            @if($sapExp->response_ok)
                              <span class="badge badge-success">OK</span>
                            @else
                              <span class="badge badge-danger">ERROR</span>
                            @endif
                          @else
                            <span class="text-muted">—</span>
                          @endif
                        </td>
                        @endif
                        <td><small>{{ optional($r->created_at)->format('d/m/Y H:i') }}</small></td>
                        <td><small>{{ optional($r->resolution_date)->format('d/m/Y H:i') ?: '—' }}</small></td>
                        <td class="text-center td-actions">
                          @php $desc = $r->description ?? ''; @endphp
                          @if($desc)
                            <button type="button" class="btn btn-info btn-link btn-just-icon view-desc" data-description="{{ e($desc) }}" title="Ver descripción">
                              <i class="material-icons">description</i>
                            </button>
                          @else
                            <span class="text-muted">—</span>
                          @endif
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

<div class="modal fade" id="descModal" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Descripción</h5>
        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <div class="modal-body"><pre id="descModalBody" class="mb-0" style="white-space:pre-wrap;font-family:inherit;"></pre></div>
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
      if (typeof $.fn.DataTable === 'undefined') return;

      const COL = @json($col);
      const syncLabel = @json('Sincronizar ' . ($policyLabel ?? 'Humand'));

      $.fn.dataTable.ext.search.push(function (settings, data) {
        const q = ($('#filter-usuario').val() || '').toLowerCase();
        const st = ($('#filter-estado').val() || '').toUpperCase();
        if (q && !(data[COL.usuario].toLowerCase().includes(q) || data[COL.nombre].toLowerCase().includes(q))) return false;
        if (st && data[COL.estado].replace(/<[^>]*>/g, '').toUpperCase().trim() !== st) return false;
        return true;
      });

      const dt = $('#timeoff-table').DataTable({
        pagingType: 'full_numbers',
        lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
        responsive: true,
        order: [[COL.creada, 'desc']],
        language: {
          search: '_INPUT_',
          searchPlaceholder: 'Buscar…',
          lengthMenu: 'Mostrar _MENU_',
          zeroRecords: 'Sin coincidencias',
          info: '_START_–_END_ de _TOTAL_',
          infoEmpty: '0 registros',
          infoFiltered: '(de _MAX_)',
          emptyTable: 'No hay solicitudes. Pulsa «Sincronizar» para traer datos de Humand.',
          paginate: { first: '«', last: '»', next: '›', previous: '‹' }
        },
        columnDefs: [
          { targets: COL.estado, render: (d, t) => (t === 'sort' || t === 'filter') ? String(d).replace(/<[^>]*>/g, '').trim() : d },
          { targets: COL.desc, orderable: false, searchable: false }
        ]
      });

      $('#filter-usuario').on('input', () => dt.draw());
      $('#filter-estado').on('change', () => dt.draw());
      $('#btn-reset-filtros').on('click', () => {
        $('#filter-usuario, #filter-estado').val('');
        dt.search('').columns().search('');
        dt.draw();
      });

      $(document).on('click', '.view-desc', function () {
        let msg = $(this).data('description') || '';
        try { msg = JSON.stringify(JSON.parse(msg), null, 2); } catch (e) {}
        $('#descModalBody').text(msg);
        $('#descModal').modal('show');
      });

      @if($etlEnabled)
      $('#form-run-etl').on('submit', function (e) {
        e.preventDefault();
        const $btn = $('#btn-run-etl');
        $btn.prop('disabled', true).html('<i class="material-icons">hourglass_empty</i> Sincronizando…');
        $.ajax({
          url: this.action, method: 'POST', data: $(this).serialize(),
          headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
          timeout: {{ (int) config('scripts.etl_timeout', 600) * 1000 }}
        })
        .done(resp => { location.reload(); })
        .fail(xhr => {
          let msg = xhr.responseJSON?.message || 'Error al sincronizar.';
          $('#flash').html('<div class="alert alert-danger alert-dismissible fade show">' + msg + '<button type="button" class="close" data-dismiss="alert"><span>&times;</span></button></div>');
        })
        .always(() => $btn.prop('disabled', false).html('<i class="material-icons">sync</i> ' + syncLabel));
      });
      @endif

      @if($isDcPagoVac)
      $('#form-export-dc-sap').on('submit', function (e) {
        e.preventDefault();
        const $btn = $('#btn-export-dc-sap');
        $btn.prop('disabled', true).html('<i class="material-icons">hourglass_empty</i> Enviando…');
        $.ajax({
          url: this.action, method: 'POST', data: $(this).serialize(),
          headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
          timeout: 300000
        })
        .done(resp => { location.reload(); })
        .fail(xhr => {
          let msg = xhr.responseJSON?.message || 'Error al exportar DC.';
          $('#flash').html('<div class="alert alert-danger alert-dismissible fade show">' + msg + '<button type="button" class="close" data-dismiss="alert"><span>&times;</span></button></div>');
        })
        .always(() => $btn.prop('disabled', false).html('<i class="material-icons">send</i> Enviar automáticos a SAP'));
      });

      $(document).on('click', '.btn-dc-send', function () {
        const $row = $(this).closest('tr');
        const requestId = $(this).data('request-id');
        const opcion = $row.find('.dc-opcion-select').val();
        if (!opcion) { alert('Selecciona la opción 1, 2 o 3.'); return; }
        const $btn = $(this);
        $btn.prop('disabled', true);
        $.ajax({
          url: '{{ url('/solicitudes/dc/export') }}/' + requestId,
          method: 'POST',
          data: { opcion: opcion, _token: '{{ csrf_token() }}' },
          timeout: 120000
        })
        .done(resp => { location.reload(); })
        .fail(xhr => {
          alert(xhr.responseJSON?.message || 'Error al enviar a SAP.');
          $btn.prop('disabled', false);
        });
      });
      @endif

      @if($isDcAnticipos)
      $('#form-export-anticipos-sap').on('submit', function (e) {
        e.preventDefault();
        const $btn = $('#btn-export-anticipos-sap');
        $btn.prop('disabled', true).html('<i class="material-icons">hourglass_empty</i> Enviando…');
        $.ajax({
          url: this.action, method: 'POST', data: $(this).serialize(),
          headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
          timeout: 300000
        })
        .done(() => location.reload())
        .fail(xhr => {
          let msg = xhr.responseJSON?.message || 'Error al exportar anticipos.';
          $('#flash').html('<div class="alert alert-danger alert-dismissible fade show">' + msg + '<button type="button" class="close" data-dismiss="alert"><span>&times;</span></button></div>');
        })
        .always(() => $btn.prop('disabled', false).html('<i class="material-icons">send</i> Enviar a SAP'));
      });
      @endif
    });
  </script>
@endpush
