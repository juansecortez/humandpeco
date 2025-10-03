@extends('layouts.app', [
  'activePage' => $activePage ?? 'dashboard',
  'menuParent' => $menuParent ?? null,
  'titlePage'  => $titlePage  ?? config('powerbi.title', 'Power BI')
])

@section('content')
@php
  $rawUrl      = config('powerbi.embed_url');
  $title       = config('powerbi.title', 'Power BI');
  $mode        = strtolower(config('powerbi.height_mode', 'ratio')); // ratio|vh|px
  $ratio       = floatval(config('powerbi.aspect_ratio', 56.25));
  $vh          = intval(config('powerbi.vh', 85));
  $px          = intval(config('powerbi.px', 900));
  $hideFilters = (bool) config('powerbi.hide_filters', false);

  // Si hay que ocultar filtros y la URL es "view?", añadimos el flag
  $embedUrl = $rawUrl;
  if ($rawUrl && $hideFilters) {
      // agrega filtersPaneEnabled=false si no está presente
      $embedUrl .= (str_contains($rawUrl, '?') ? '&' : '?') . 'filtersPaneEnabled=false';
  }

  // Determina clases para el contenedor según modo
  $wrapClass = match ($mode) {
      'vh'  => 'pbi-wrap-vh',
      'px'  => 'pbi-wrap-px',
      default => 'pbi-wrap-ratio',
  };
@endphp

<div class="content">
  <div class="container-fluid">

    <div class="card">
      <div class="card-header card-header-rose card-header-icon">
        <div class="card-icon"><i class="material-icons">insights</i></div>
        <h4 class="card-title">{{ $title }}</h4>
        <p class="card-category">Visualización embebida desde Power BI.</p>
      </div>

      <div class="card-body" style="padding-top: 10px; padding-bottom: 10px;">
        @if (!$embedUrl)
          <div class="alert alert-warning mb-0">
            <strong>Falta configurar la URL de Power BI.</strong><br>
            Define <code>POWERBI_EMBED_URL</code> en <code>.env</code>.
          </div>
        @else
          <div class="d-flex justify-content-end mb-2">
            <a href="{{ $embedUrl }}" target="_blank" rel="noopener" class="btn btn-outline-primary btn-sm">
              <i class="material-icons" style="vertical-align:middle;">open_in_new</i>
              <span>Abrir en pestaña nueva</span>
            </a>
          </div>

          {{-- Contenedor responsive / alto controlado por .env --}}
          <div class="{{ $wrapClass }}"
               style="
                 @if($mode==='ratio')
                   padding-top: {{ $ratio }}%;
                 @elseif($mode==='vh')
                   height: {{ $vh }}vh;
                 @elseif($mode==='px')
                   height: {{ $px }}px;
                 @endif
               ">
            <iframe
              title="{{ $title }}"
              src="{{ $embedUrl }}"
              frameborder="0"
              allowfullscreen
              style="position:absolute;top:0;left:0;width:100%;height:100%;"
            ></iframe>
          </div>

          <p class="text-muted mt-2 mb-0" style="font-size:.9rem;">
            <strong>Tip:</strong> Puedes ajustar la altura desde <code>.env</code> con
            <code>POWERBI_HEIGHT_MODE</code> = <em>ratio</em>|<em>vh</em>|<em>px</em>.
          </p>
        @endif
      </div>
    </div>

  </div>
</div>
@endsection

@push('css')
<style>
  /* Modo ratio (por defecto): usa padding-top para fijar el alto relativo */
  .pbi-wrap-ratio {
    position: relative;
    width: 100%;
    background: #f7f8f9;
    border: 1px solid rgba(0,0,0,.05);
    border-radius: 8px;
    overflow: hidden;
  }
  .pbi-wrap-ratio > iframe { position:absolute; top:0; left:0; width:100%; height:100%; }

  /* Modo vh: altura en % del viewport */
  .pbi-wrap-vh {
    position: relative;
    width: 100%;
    background: #f7f8f9;
    border: 1px solid rgba(0,0,0,.05);
    border-radius: 8px;
    overflow: hidden;
  }
  .pbi-wrap-vh > iframe { position:absolute; top:0; left:0; width:100%; height:100%; }

  /* Modo px: altura fija en px */
  .pbi-wrap-px {
    position: relative;
    width: 100%;
    background: #f7f8f9;
    border: 1px solid rgba(0,0,0,.05);
    border-radius: 8px;
    overflow: hidden;
  }
  .pbi-wrap-px > iframe { position:absolute; top:0; left:0; width:100%; height:100%; }
</style>
@endpush
