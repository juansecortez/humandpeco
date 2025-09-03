@extends('layouts.app', [
  'activePage' => $activePage ?? 'vacaciones-status',
  'menuParent' => $menuParent ?? 'vacaciones',
  'titlePage'  => $titlePage  ?? 'Estatus de Vacaciones'
])

@section('content')
<div class="content">
  <div class="container-fluid">
    <div class="card">
      <div class="card-header card-header-rose">
        <h4 class="card-title">{{ $titlePage }}</h4>
        <p class="card-category">Consulta el estado de tus solicitudes</p>
      </div>
      <div class="card-body">
        {{-- TODO: status del usuario actual / historial --}}
        <p class="text-muted mb-0">Contenido de estatus de vacaciones.</p>
      </div>
    </div>
  </div>
</div>
@endsection
