@extends('layouts.app', ['activePage' => 'user-management', 'menuParent' => 'laravel', 'titlePage' => 'Usuarios'])

@section('content')
<div class="content">
  <div class="container-fluid">
    <div class="row">
      <div class="col-md-12">
        <div class="card">
          <div class="card-header card-header-rose card-header-icon">
            <div class="card-icon"><i class="material-icons">group</i></div>
            <h4 class="card-title">Usuarios</h4>
            <p class="card-category">Asignación de roles de acceso a HumandPeco</p>
          </div>
          <div class="card-body">
            @if (session('status'))
              <div class="alert alert-success">{{ session('status') }}</div>
            @endif
            @can('create', App\User::class)
              <div class="text-right mb-3">
                <a href="{{ route('user.create') }}" class="btn btn-rose btn-sm">
                  <i class="material-icons" style="font-size:18px;vertical-align:middle;">person_add</i> Agregar usuario
                </a>
              </div>
            @endcan
            <div class="table-responsive">
              <table id="datatables" class="table table-striped table-hover">
                <thead class="text-primary">
                  <tr>
                    <th>Usuario</th>
                    <th>Correo</th>
                    <th>Rol</th>
                    <th>Alta</th>
                    @can('manage-users', App\User::class)
                      <th class="text-right">Acciones</th>
                    @endcan
                  </tr>
                </thead>
                <tbody>
                  @foreach($users as $user)
                    <tr>
                      <td>
                        <div class="d-flex align-items-center">
                          <img src="{{ $user->profilePicture() }}" alt=""
                            style="width:40px;height:40px;object-fit:cover;border-radius:8px;margin-right:10px;">
                          <strong>{{ $user->name }}</strong>
                        </div>
                      </td>
                      <td>{{ $user->email }}</td>
                      <td><span class="badge badge-info">{{ $user->role->name ?? '—' }}</span></td>
                      <td><small>{{ $user->created_at->format('d/m/Y') }}</small></td>
                      @can('manage-users', App\User::class)
                        <td class="td-actions text-right">
                          @if ($user->id != auth()->id())
                            @can('update', $user)
                              <a class="btn btn-success btn-link btn-just-icon" href="{{ route('user.edit', $user) }}" title="Editar">
                                <i class="material-icons">edit</i>
                              </a>
                            @endcan
                            @can('delete', $user)
                              <form action="{{ route('user.destroy', $user) }}" method="post" class="d-inline">
                                @csrf
                                @method('delete')
                                <button type="submit" class="btn btn-danger btn-link btn-just-icon"
                                  onclick="return confirm('¿Eliminar este usuario?')" title="Eliminar">
                                  <i class="material-icons">close</i>
                                </button>
                              </form>
                            @endcan
                          @else
                            <a class="btn btn-info btn-link btn-just-icon" href="{{ route('profile.edit') }}" title="Mi perfil">
                              <i class="material-icons">person</i>
                            </a>
                          @endif
                        </td>
                      @endcan
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
@endsection

@push('js')
<script>
  $(function () {
    $('#datatables').DataTable({
      pagingType: 'full_numbers',
      lengthMenu: [[10, 25, 50, -1], [10, 25, 50, 'Todos']],
      responsive: true,
      language: {
        search: '_INPUT_', searchPlaceholder: 'Buscar…',
        lengthMenu: 'Mostrar _MENU_', info: '_START_–_END_ de _TOTAL_',
        zeroRecords: 'Sin usuarios', paginate: { first: '«', last: '»', next: '›', previous: '‹' }
      },
      columnDefs: [{ orderable: false, targets: -1 }]
    });
  });
</script>
@endpush
