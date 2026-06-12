@if(($pendingDc ?? collect())->isNotEmpty())
<div class="card card-plain mb-4">
  <div class="card-header card-header-warning card-header-icon">
    <div class="card-icon"><i class="material-icons">pending_actions</i></div>
    <h4 class="card-title">Pendientes de envío a SAP</h4>
    <p class="card-category">
      Solicitudes aprobadas sin opción válida (1, 2 o 3) en la descripción.
      El empleado debe poner solo el número; aquí el equipo elige la opción y envía.
    </p>
  </div>
  <div class="card-body">
    <div class="table-responsive">
      <table class="table table-sm table-hover" id="pending-dc-table">
        <thead class="text-primary">
          <tr>
            <th>ID</th>
            <th>Empleado</th>
            <th>Política</th>
            <th>Desde</th>
            <th>Descripción</th>
            <th>Opción SAP</th>
            <th class="text-center">Enviar</th>
          </tr>
        </thead>
        <tbody>
          @foreach($pendingDc as $r)
            <tr data-request-id="{{ $r->request_id }}">
              <td><small>#{{ $r->request_id }}</small></td>
              <td>
                <strong>{{ $r->issuer_full_name ?: '—' }}</strong>
                <span class="table-email d-block">{{ $r->issuer_employee_internal_id }}</span>
              </td>
              <td>{{ $r->policy_name }}</td>
              <td>{{ optional($r->from_date)->format('d/m/Y') }}</td>
              <td><small class="text-muted">{{ \Illuminate\Support\Str::limit($r->description ?: '(vacía)', 80) }}</small></td>
              <td>
                <select class="form-control form-control-sm dc-opcion-select" style="min-width:220px;">
                  <option value="">Seleccionar…</option>
                  @foreach(($opcionLabels ?? []) as $val => $label)
                    <option value="{{ $val }}">{{ $val }} — {{ $label }}</option>
                  @endforeach
                </select>
              </td>
              <td class="text-center">
                <button type="button" class="btn btn-rose btn-sm btn-dc-send" data-request-id="{{ $r->request_id }}">
                  <i class="material-icons" style="font-size:16px;vertical-align:middle;">send</i> SAP
                </button>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
</div>
@endif
