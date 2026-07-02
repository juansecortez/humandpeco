@php
  use Illuminate\Support\Str;

  $personas = $personas ?? collect();
  $typeLabels = ['fc' => 'FC', 'dc' => 'DC', 'supervisor' => 'Supervisor'];
  $statusMeta = [
    'applied'   => ['badge-success', 'APLICADO'],
    'simulated' => ['badge-info', 'SIMULADO'],
    'unchanged' => ['badge-secondary', 'SIN CAMBIO'],
    'skipped'   => ['badge-warning', 'OMITIDO'],
    'error'     => ['badge-danger', 'ERROR'],
  ];
@endphp

@extends('layouts.app', [
  'activePage' => $activePage ?? 'saldos-sync',
  'menuParent' => $menuParent ?? 'saldos',
  'titlePage'  => $titlePage ?? 'Sincronización de saldos',
])

@section('content')
<div class="content">
  <div class="container-fluid">

    <div class="row">
      <div class="col-lg-3 col-md-6 col-sm-6">
        <div class="card card-stats">
          <div class="card-header card-header-info card-header-icon">
            <div class="card-icon"><i class="material-icons">groups</i></div>
            <p class="card-category">Organigrama</p>
            <h3 class="card-title">{{ number_format($totalCount ?? 0) }}</h3>
          </div>
          <div class="card-footer"><div class="stats"><i class="material-icons">people</i> Personas activas</div></div>
        </div>
      </div>
      <div class="col-lg-3 col-md-6 col-sm-6">
        <div class="card card-stats">
          <div class="card-header card-header-success card-header-icon">
            <div class="card-icon"><i class="material-icons">check_circle</i></div>
            <p class="card-category">Con ajuste aplicado</p>
            <h3 class="card-title">{{ number_format($withApplied ?? 0) }}</h3>
          </div>
          <div class="card-footer"><div class="stats"><i class="material-icons">done</i> Al menos un SET en Humand</div></div>
        </div>
      </div>
      <div class="col-lg-3 col-md-6 col-sm-6">
        <div class="card card-stats">
          <div class="card-header card-header-warning card-header-icon">
            <div class="card-icon"><i class="material-icons">history</i></div>
            <p class="card-category">Sincronizados</p>
            <h3 class="card-title">{{ number_format($withSync ?? 0) }}</h3>
          </div>
          <div class="card-footer"><div class="stats"><i class="material-icons">sync</i> Con al menos un intento</div></div>
        </div>
      </div>
      <div class="col-lg-3 col-md-6 col-sm-6">
        <div class="card card-stats">
          <div class="card-header card-header-rose card-header-icon">
            <div class="card-icon"><i class="material-icons">person_off</i></div>
            <p class="card-category">Sin sincronizar</p>
            <h3 class="card-title">{{ number_format($withoutSync ?? 0) }}</h3>
          </div>
          <div class="card-footer"><div class="stats"><i class="material-icons">hourglass_empty</i> Aún sin log</div></div>
        </div>
      </div>
    </div>

    <div class="row">
      <div class="col-md-12">
        <div class="card">
          <div class="card-header card-header-rose card-header-icon">
            <div class="card-icon"><i class="material-icons">account_balance_wallet</i></div>
            <h4 class="card-title">{{ $titlePage }}</h4>
            <p class="card-category">Personas del organigrama · botón Log para ver saldos ejecutados por persona</p>
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
                <label for="filter-persona">Buscar persona</label>
                <div class="input-group">
                  <div class="input-group-prepend"><span class="input-group-text"><i class="material-icons">search</i></span></div>
                  <input type="text" id="filter-persona" class="form-control" placeholder="Nombre, correo o código">
                </div>
              </div>
              <div class="col-lg-2 col-md-3 mb-3 mb-md-0">
                <label for="filter-tipo">Tipo</label>
                <select id="filter-tipo" class="form-control">
                  <option value="">Todos</option>
                  <option value="fc">FC</option>
                  <option value="dc">DC</option>
                  <option value="supervisor">Supervisor</option>
                </select>
              </div>
              <div class="col-lg-2 col-md-3 mb-3 mb-md-0">
                <label for="filter-sync">Estado sync</label>
                <select id="filter-sync" class="form-control">
                  <option value="">Todos</option>
                  <option value="applied">Con ajuste aplicado</option>
                  <option value="synced">Sincronizado (sin aplicar)</option>
                  <option value="none">Sin sincronizar</option>
                </select>
              </div>
              <div class="col-lg-5 col-md-12 text-lg-right">
                <label class="d-none d-lg-block">&nbsp;</label>
                <button type="button" class="btn btn-rose" id="btn-sync-all">
                  <i class="material-icons">groups</i> Sincronizar todos
                </button>
                <button type="button" class="btn btn-info" id="btn-sync-one">
                  <i class="material-icons">person_search</i> Sincronizar una persona
                </button>
              </div>
            </div>

            <div class="material-datatables">
              <div class="table-responsive">
                <table id="personas-table" class="table table-striped table-no-bordered table-hover" cellspacing="0" width="100%">
                  <thead class="text-primary">
                    <tr>
                      <th>Persona</th>
                      <th>Código</th>
                      <th>Tipo</th>
                      <th>Última sync</th>
                      <th class="text-center">Ajustes</th>
                      <th>Estado</th>
                      <th class="text-center">Acciones</th>
                      <th class="d-none">sync_key</th>
                    </tr>
                  </thead>
                  <tbody>
                    @foreach($personas as $p)
                      @php
                        $isHighlight = ($highlight ?? '') !== '' && $highlight === $p['codigo_col'];
                        if ($p['applied_count'] > 0) {
                          $estadoClass = 'badge-success'; $estadoLab = 'CON AJUSTE';
                          $syncKey = 'applied';
                        } elseif ($p['has_sync']) {
                          $estadoClass = 'badge-info'; $estadoLab = 'SIN CAMBIO / SIM';
                          $syncKey = 'synced';
                        } else {
                          $estadoClass = 'badge-secondary'; $estadoLab = 'PENDIENTE';
                          $syncKey = 'none';
                        }
                      @endphp
                      <tr class="{{ $isHighlight ? 'table-warning' : '' }}" data-codigo="{{ $p['codigo_col'] }}">
                        <td>
                          <strong>{{ $p['nombre'] ?: '—' }}</strong>
                          @if($p['correo'])
                            <span class="table-email">{{ $p['correo'] }}</span>
                          @endif
                        </td>
                        <td>{{ $p['codigo_col'] }}</td>
                        <td><span class="badge badge-secondary">{{ $typeLabels[$p['person_type']] ?? strtoupper($p['person_type']) }}</span></td>
                        <td>
                          @if($p['last_sync_at'])
                            <small>{{ \Illuminate\Support\Carbon::parse($p['last_sync_at'])->format('d/m/Y H:i') }}</small>
                          @else
                            <span class="text-muted">—</span>
                          @endif
                        </td>
                        <td class="text-center">
                          @if($p['applied_count'] > 0)
                            <span class="badge badge-success">{{ $p['applied_count'] }} aplicados</span>
                          @elseif($p['has_sync'])
                            <span class="badge badge-info">{{ $p['total_items'] }} registro(s)</span>
                          @else
                            <span class="text-muted">0</span>
                          @endif
                        </td>
                        <td><span class="badge {{ $estadoClass }}">{{ $estadoLab }}</span></td>
                        <td class="text-center td-actions">
                          <button type="button" class="btn btn-info btn-sm btn-log"
                            data-codigo="{{ $p['codigo_col'] }}"
                            data-nombre="{{ e($p['nombre'] ?? '') }}"
                            title="Ver log de saldos">
                            <i class="material-icons" style="font-size:18px;vertical-align:middle;">history</i> Log
                          </button>
                          <button type="button" class="btn btn-rose btn-sm btn-sync-person"
                            data-codigo="{{ $p['codigo_col'] }}"
                            title="Sincronizar esta persona">
                            <i class="material-icons" style="font-size:18px;vertical-align:middle;">sync</i>
                          </button>
                        </td>
                        <td class="d-none">{{ $syncKey }}</td>
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

<div class="modal fade" id="syncModal" tabindex="-1" role="dialog">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="syncModalTitle">Ejecutar sincronización de saldos</h5>
        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <div class="modal-body">
        <p class="text-muted">Lee Organigrama, baja saldos de SAP y los compara con Humand. En modo simulación no se escribe nada en Humand.</p>
        <p class="text-muted small mb-0">La sincronización masiva corre en segundo plano en el servidor (no depende del navegador).</p>
        <div class="form-group" id="sync-codigo-group" style="display:none;">
          <label for="sync-codigo">CodigoCol de la persona</label>
          <input type="text" id="sync-codigo" class="form-control" placeholder="Ej. 8758">
        </div>
        <div class="form-check">
          <label class="form-check-label">
            <input class="form-check-input" type="checkbox" id="sync-apply">
            <span class="form-check-sign"><span class="check"></span></span>
            Aplicar ajustes en Humand (si lo dejas desmarcado, solo simula)
          </label>
        </div>
        <p id="sync-progress" class="text-info small mt-3 mb-0" style="display:none;"></p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-default" data-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-rose" id="btn-run-sync"><i class="material-icons">play_arrow</i> Ejecutar</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="logModal" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-xl">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="logModalTitle">Log de saldos</h5>
        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <div class="modal-body">
        <div id="log-loading" class="text-center py-4">
          <i class="material-icons spin" style="font-size:36px;">hourglass_empty</i>
          <p class="text-muted mb-0">Cargando historial…</p>
        </div>
        <div id="log-empty" class="text-center py-4" style="display:none;">
          <i class="material-icons text-muted" style="font-size:48px;">inbox</i>
          <p class="text-muted mb-0">Esta persona aún no tiene sincronizaciones registradas.</p>
        </div>
        <div id="log-content" style="display:none;">
          <p class="text-muted mb-3" id="log-subtitle"></p>
          <div class="table-responsive">
            <table class="table table-sm table-striped">
              <thead class="text-primary">
                <tr>
                  <th>Fecha</th>
                  <th>Run</th>
                  <th>Modo</th>
                  <th>Política</th>
                  <th class="text-right">SAP</th>
                  <th class="text-right">Humand</th>
                  <th class="text-right">Ajuste</th>
                  <th>Ciclo</th>
                  <th>Resultado</th>
                  <th>Mensaje</th>
                </tr>
              </thead>
              <tbody id="log-table-body"></tbody>
            </table>
          </div>
        </div>
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
  @include('vacaciones.partials.solicitudes-styles')
  <style>
    @keyframes spin { to { transform: rotate(360deg); } }
    .spin { animation: spin 1.2s linear infinite; display: inline-block; }
  </style>
@endpush

@push('js')
  <script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap4.min.js"></script>
  <script>
    $(function () {
      const statusMeta = @json($statusMeta);
      const typeLabels = @json($typeLabels);
      const highlight = @json($highlight ?? '');
      const activeRunId = @json($activeRun->id ?? null);

      function updateProgress(label) {
        $('#sync-progress').show().text(label);
      }

      function pollRunStatus(runId, onDone) {
        $.get('{{ url('/saldos/run') }}/' + runId + '/status')
          .done(resp => {
            const label = resp.message || ('Procesando ' + resp.offset + ' de ' + resp.total + ' personas…');
            updateProgress(label);

            if (!resp.done) {
              setTimeout(() => pollRunStatus(runId, onDone), 2500);
              return;
            }

            onDone(resp);
          })
          .fail(xhr => {
            const msg = xhr.responseJSON?.message || 'Error al consultar el progreso.';
            onDone({ ok: false, message: msg, status: 'failed' });
          });
      }

      if (activeRunId) {
        updateProgress('Hay una sincronización masiva en curso…');
        pollRunStatus(activeRunId, resp => {
          if (resp.status === 'failed' || resp.ok === false) {
            $('#flash').html('<div class="alert alert-danger alert-dismissible fade show">' + (resp.message || 'La sincronización falló.') + '<button type="button" class="close" data-dismiss="alert"><span>&times;</span></button></div>');
          } else {
            $('#flash').html('<div class="alert alert-success alert-dismissible fade show">' + (resp.message || 'Sincronización completada.') + '<button type="button" class="close" data-dismiss="alert"><span>&times;</span></button></div>');
          }
          $('#sync-progress').hide();
        });
      }

      const dt = $('#personas-table').DataTable({
        pagingType: 'full_numbers',
        lengthMenu: [[25, 50, 100, 250], [25, 50, 100, 250]],
        responsive: true,
        order: [[0, 'asc']],
        language: {
          search: '_INPUT_', searchPlaceholder: 'Buscar…', lengthMenu: 'Mostrar _MENU_',
          zeroRecords: 'Sin coincidencias', info: '_START_–_END_ de _TOTAL_',
          infoEmpty: '0 registros', infoFiltered: '(de _MAX_)', emptyTable: 'Sin personas en organigrama.',
          paginate: { first: '«', last: '»', next: '›', previous: '‹' }
        },
        columnDefs: [
          { targets: 7, visible: false, searchable: true },
          { targets: 6, orderable: false, searchable: false }
        ]
      });

      if (highlight) {
        $('#filter-persona').val(highlight);
        dt.draw();
      }

      $.fn.dataTable.ext.search.push(function (settings, data) {
        const q = ($('#filter-persona').val() || '').toLowerCase();
        const tipo = ($('#filter-tipo').val() || '').toLowerCase();
        const sync = ($('#filter-sync').val() || '');
        if (q && !(data[0].toLowerCase().includes(q) || data[1].toLowerCase().includes(q))) return false;
        if (tipo && !data[2].toLowerCase().includes(tipo)) return false;
        if (sync && data[7] !== sync) return false;
        return true;
      });

      $('#filter-persona').on('input', () => dt.draw());
      $('#filter-tipo, #filter-sync').on('change', () => dt.draw());

      function fmtNum(v) {
        if (v === null || v === undefined || v === '') return '—';
        return parseFloat(v).toString();
      }

      function openLog(codigo, nombre) {
        $('#logModalTitle').text('Log de saldos — ' + (nombre || codigo));
        $('#log-loading').show();
        $('#log-empty, #log-content').hide();
        $('#logModal').modal('show');

        $.get('{{ url('/saldos/log') }}/' + encodeURIComponent(codigo))
          .done(data => {
            $('#log-loading').hide();
            const tipo = typeLabels[data.tipo] || (data.tipo || '').toUpperCase();
            $('#log-subtitle').text(
              (data.nombre ? data.nombre + ' · ' : '') +
              'Código ' + data.codigo +
              (data.correo ? ' · ' + data.correo : '') +
              (tipo ? ' · ' + tipo : '')
            );

            if (!data.items || data.items.length === 0) {
              $('#log-empty').show();
              return;
            }

            const rows = data.items.map(it => {
              const [cls, lab] = statusMeta[it.status] || ['badge-secondary', it.status.toUpperCase()];
              const modo = it.dry_run ? '<span class="badge badge-info">SIM</span>' : '<span class="badge badge-success">APLIC</span>';
              const ajuste = ['applied','simulated'].includes(it.status) && it.target_value !== null
                ? '<strong>' + fmtNum(it.target_value) + '</strong>' : '—';
              return '<tr>' +
                '<td><small>' + (it.created_at || '—') + '</small></td>' +
                '<td><small>#' + it.run_id + '</small></td>' +
                '<td>' + modo + '</td>' +
                '<td>' + (it.policy_label || '—') + '</td>' +
                '<td class="text-right">' + fmtNum(it.sap_value) + '</td>' +
                '<td class="text-right">' + fmtNum(it.humand_before) + '</td>' +
                '<td class="text-right">' + ajuste + '</td>' +
                '<td><small>' + (it.cycle_title || '—') + '</small></td>' +
                '<td><span class="badge ' + cls + '">' + lab + '</span></td>' +
                '<td><small>' + $('<div/>').text(it.message || '').html() + '</small></td>' +
              '</tr>';
            }).join('');

            $('#log-table-body').html(rows);
            $('#log-content').show();
          })
          .fail(() => {
            $('#log-loading').hide();
            $('#log-empty').show().find('p').text('Error al cargar el historial.');
          });
      }

      $(document).on('click', '.btn-log', function () {
        openLog($(this).data('codigo'), $(this).data('nombre'));
      });

      $('#btn-sync-all').on('click', function () {
        $('#syncModalTitle').text('Sincronizar todos');
        $('#sync-codigo-group').hide();
        $('#sync-codigo').val('');
        $('#sync-apply').prop('checked', false);
        $('#syncModal').modal('show');
      });

      $('#btn-sync-one, .btn-sync-person').on('click', function () {
        const codigo = $(this).data('codigo') || '';
        $('#syncModalTitle').text(codigo ? 'Sincronizar persona ' + codigo : 'Sincronizar una persona');
        $('#sync-codigo-group').show();
        $('#sync-codigo').val(codigo);
        $('#sync-apply').prop('checked', false);
        $('#syncModal').modal('show');
        if (!codigo) setTimeout(() => $('#sync-codigo').focus(), 300);
      });

      $('#btn-run-sync').on('click', function () {
        const $btn = $(this);
        const apply = $('#sync-apply').is(':checked');
        const codigo = $('#sync-codigo').val().trim();
        const onePerson = $('#sync-codigo-group').is(':visible');
        const isBulkAll = !onePerson;

        if (onePerson && !codigo) {
          alert('Ingresa el CodigoCol de la persona.');
          $('#sync-codigo').focus();
          return;
        }
        if (apply && !confirm('Vas a APLICAR ajustes reales en Humand. ¿Continuar?')) return;

        $btn.prop('disabled', true).html('<i class="material-icons">hourglass_empty</i> Procesando…');
        $('#sync-progress').hide().text('');

        function postSync() {
          return $.ajax({
            url: '{{ route('saldos.run') }}',
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            data: {
              apply: apply ? 1 : 0,
              codigo: onePerson ? codigo : '',
              _token: '{{ csrf_token() }}'
            },
            timeout: isBulkAll ? 60000 : 900000
          });
        }

        function finishSuccess(resp) {
          if (resp.redirect) {
            window.location = resp.redirect;
            return;
          }
          window.location = '{{ route('saldos.index') }}';
        }

        function finishError(xhr) {
          let msg = xhr.responseJSON?.message || 'Error al sincronizar.';
          $('#syncModal').modal('hide');
          $('#flash').html('<div class="alert alert-danger alert-dismissible fade show">' + msg + '<button type="button" class="close" data-dismiss="alert"><span>&times;</span></button></div>');
          $btn.prop('disabled', false).html('<i class="material-icons">play_arrow</i> Ejecutar');
        }

        if (isBulkAll) {
          postSync()
            .done(resp => {
              $('#syncModal').modal('hide');
              updateProgress(resp.message || 'Sincronización iniciada…');
              pollRunStatus(resp.run_id, statusResp => {
                if (statusResp.status === 'failed' || statusResp.ok === false) {
                  finishError({ responseJSON: { message: statusResp.message } });
                  return;
                }
                finishSuccess(statusResp);
              });
            })
            .fail(finishError);

          return;
        }

        postSync()
          .done(finishSuccess)
          .fail(finishError)
          .always(() => $btn.prop('disabled', false).html('<i class="material-icons">play_arrow</i> Ejecutar'));
      });
    });
  </script>
@endpush
