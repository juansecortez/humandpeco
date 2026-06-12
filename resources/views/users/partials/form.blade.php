@php
  $isEdit = isset($user);
  $selectedRole = old('role_id', $user->role_id ?? '');
  $defaultAvatar = asset(config('users.default_avatar', 'material/img/default-avatar.png'));
@endphp

<div class="row mb-4">
  <div class="col-md-12 text-center">
    <img src="{{ $defaultAvatar }}" alt="Avatar"
      style="width:96px;height:96px;object-fit:cover;border-radius:12px;border:1px solid #e0e0e0;">
    <p class="text-muted small mt-2 mb-0">Avatar predeterminado para todos los usuarios</p>
  </div>
</div>

<div class="row">
  <label class="col-sm-3 col-form-label">Usuario (UsuarioId)</label>
  <div class="col-sm-7">
    <div class="form-group{{ $errors->has('name') ? ' has-danger' : '' }}">
      <input class="form-control{{ $errors->has('name') ? ' is-invalid' : '' }}" name="name" type="text"
        placeholder="Ej. ssuarez o Admin" value="{{ old('name', $user->name ?? '') }}" required>
      <small class="form-text text-muted">Debe coincidir con el UsuarioId del organigrama (sin @dominio).</small>
      @include('alerts.feedback', ['field' => 'name'])
    </div>
  </div>
</div>

<div class="row">
  <label class="col-sm-3 col-form-label">Correo</label>
  <div class="col-sm-7">
    <div class="form-group{{ $errors->has('email') ? ' has-danger' : '' }}">
      <input class="form-control{{ $errors->has('email') ? ' is-invalid' : '' }}" name="email" type="email"
        placeholder="correo@pcolorada.com" value="{{ old('email', $user->email ?? '') }}" required>
      @include('alerts.feedback', ['field' => 'email'])
    </div>
  </div>
</div>

<div class="row">
  <label class="col-sm-3 col-form-label">Rol</label>
  <div class="col-sm-7">
    <div class="form-group{{ $errors->has('role_id') ? ' has-danger' : '' }}">
      <select class="form-control" name="role_id" required>
        <option value="">Seleccione un rol…</option>
        @foreach($roles as $role)
          <option value="{{ $role->id }}" {{ (string) $selectedRole === (string) $role->id ? 'selected' : '' }}>
            {{ $role->name }}
          </option>
        @endforeach
      </select>
      @include('alerts.feedback', ['field' => 'role_id'])
    </div>
  </div>
</div>

<div id="password-fields" style="display:none;">
  <div class="row">
    <label class="col-sm-3 col-form-label">Contraseña</label>
    <div class="col-sm-7">
      <div class="form-group{{ $errors->has('password') ? ' has-danger' : '' }}">
        <input class="form-control{{ $errors->has('password') ? ' is-invalid' : '' }}" type="password"
          name="password" id="input-password" placeholder="{{ $isEdit ? 'Dejar vacío para no cambiar' : 'Solo Admin local' }}">
        @include('alerts.feedback', ['field' => 'password'])
      </div>
    </div>
  </div>
  <div class="row">
    <label class="col-sm-3 col-form-label">Confirmar</label>
    <div class="col-sm-7">
      <div class="form-group">
        <input class="form-control" type="password" name="password_confirmation" placeholder="Confirmar contraseña">
      </div>
    </div>
  </div>
</div>

<p class="text-muted small ml-3">
  <strong>Vacaciones / Nóminas:</strong> la contraseña la valida el Hub corporativo al iniciar sesión; aquí solo se asigna el rol.
  <strong>Admin:</strong> requiere contraseña local al crear.
</p>

@push('js')
<script>
  $(function () {
    function togglePassword() {
      const role = $('select[name=role_id]').val();
      const show = role === '1';
      $('#password-fields').toggle(show);
      $('#input-password').prop('required', show && !{{ $isEdit ? 'true' : 'false' }});
    }
    $('select[name=role_id]').on('change', togglePassword);
    togglePassword();
  });
</script>
@endpush
