@php
  $guard = session('auth_guard', 'web');
  $authUser = auth()->user();
  $defaultAvatar = asset(config('users.default_avatar', 'material/img/default-avatar.png'));

  if ($guard === 'organigrama') {
      $paired = \App\User::where('name', $authUser->getAuthIdentifier())->first();
      $displayName = $authUser->NombreCompleto ?? $authUser->name ?? '—';
      $displayEmail = $authUser->Correo ?? '—';
      $displayUser = $authUser->UsuarioId ?? '—';
      $displayRole = $paired?->role?->name ?? '—';
      $displayPhoto = method_exists($authUser, 'profilePicture') ? $authUser->profilePicture() : $defaultAvatar;
  } else {
      $displayName = $authUser->name ?? '—';
      $displayEmail = $authUser->email ?? '—';
      $displayUser = $authUser->name ?? '—';
      $displayRole = $authUser->role?->name ?? '—';
      $displayPhoto = $defaultAvatar;
      if ($authUser instanceof \App\User && $authUser->picture) {
          $displayPhoto = $authUser->profilePicture();
      }
  }
@endphp

@extends('layouts.app', ['activePage' => 'profile', 'menuParent' => 'laravel', 'titlePage' => 'Mi perfil'])

@section('content')
<div class="content">
  <div class="container-fluid">
    <div class="row justify-content-center">
      <div class="col-lg-8 col-md-10">
        <div class="card">
          <div class="card-header card-header-rose card-header-icon">
            <div class="card-icon"><i class="material-icons">person</i></div>
            <h4 class="card-title">Mi perfil</h4>
            <p class="card-category">Información de tu cuenta (solo lectura)</p>
          </div>
          <div class="card-body">
            <div class="text-center mb-4">
              <img src="{{ $displayPhoto }}" alt="Avatar"
                style="width:120px;height:120px;object-fit:cover;border-radius:12px;border:1px solid #ddd;"
                onerror="this.src='{{ $defaultAvatar }}'">
            </div>
            <table class="table table-striped">
              <tbody>
                <tr>
                  <th style="width:180px;">Nombre</th>
                  <td>{{ $displayName }}</td>
                </tr>
                <tr>
                  <th>Usuario</th>
                  <td>{{ $displayUser }}</td>
                </tr>
                <tr>
                  <th>Correo</th>
                  <td>{{ $displayEmail }}</td>
                </tr>
                <tr>
                  <th>Rol en la app</th>
                  <td>{{ $displayRole }}</td>
                </tr>
              </tbody>
            </table>
            @if($guard === 'organigrama')
              <p class="text-muted small mb-0">
                La contraseña se valida con tu cuenta corporativa. Para cambios de acceso contacta a IT o al administrador.
              </p>
            @else
              <p class="text-muted small mb-0">
                Los datos de administrador local los gestiona el equipo de IT.
              </p>
            @endif
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
