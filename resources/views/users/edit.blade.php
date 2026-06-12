@extends('layouts.app', ['activePage' => 'user-management', 'menuParent' => 'laravel', 'titlePage' => 'Usuarios'])

@section('content')
<div class="content">
  <div class="container-fluid">
    <div class="row">
      <div class="col-md-12">
        <form method="post" action="{{ route('user.update', $user) }}" autocomplete="off" class="form-horizontal">
          @csrf
          @method('put')
          <div class="card">
            <div class="card-header card-header-rose card-header-icon">
              <div class="card-icon"><i class="material-icons">edit</i></div>
              <h4 class="card-title">Editar usuario</h4>
            </div>
            <div class="card-body">
              <div class="text-right mb-3">
                <a href="{{ route('user.index') }}" class="btn btn-sm btn-default">Volver al listado</a>
              </div>
              @include('users.partials.form', ['roles' => $roles, 'user' => $user])
            </div>
            <div class="card-footer ml-auto mr-auto">
              <button type="submit" class="btn btn-rose">Guardar cambios</button>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
@endsection
