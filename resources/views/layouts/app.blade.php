{{-- Layout limpio con persistencia del sidebar --}}
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1" />
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
  <title>HUMAND - PECO</title>

  <!-- Fuentes / Iconos (opcional) -->
  <link rel="stylesheet" type="text/css" href="https://fonts.googleapis.com/css?family=Roboto:300,400,500,700|Roboto+Slab:400,700|Material+Icons" />
  <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/font-awesome/latest/css/font-awesome.min.css">

  <!-- CSS principal (usa tu build) -->
  <link href="{{ asset('css/material-dashboard.css') }}" rel="stylesheet" />
  <meta name="csrf-token" content="{{ csrf_token() }}">
  @stack('css')
  <style>
    /* Oculta barra de ofertas/preview si existiera */
    #ofBar { display:none; }
    /* Centrado rápido para el bloque de logo si lo necesitas */
    .logo.text-center a { display:block; }
  </style>
</head>
<body class="{{ $class ?? '' }}">
  <form id="logout-form" action="{{ route('logout') }}" method="POST" style="display:none;">
    @csrf
  </form>

  {{-- Contenido según auth --}}
  @if (auth()->check() && !in_array(request()->route()->getName(), ['welcome', 'page.pricing', 'page.lock', 'page.error']))
    @include('layouts.page_templates.auth')
  @else
    @include('layouts.page_templates.guest')
  @endif

  {{-- === Fixed Plugin (configurador) === --}}
  @if (auth()->check())
  <div class="fixed-plugin">
    <div class="dropdown show-dropdown">
      <a href="#" data-toggle="dropdown">
        <i class="fa fa-cog fa-2x"></i>
      </a>
      <ul class="dropdown-menu">
        <li class="header-title">Sidebar Filters</li>
        <li class="adjustments-line">
          <a href="javascript:void(0)" class="switch-trigger active-color">
            <div class="badge-colors ml-auto mr-auto">
              
              <span class="badge filter badge-azure"  data-color="azure"></span>
              <span class="badge filter badge-green"  data-color="green"></span>
              <span class="badge filter badge-warning" data-color="orange"></span>
              <span class="badge filter badge-danger" data-color="danger"></span>
              <span class="badge filter badge-rose"   data-color="rose"></span>
            </div>
            <div class="clearfix"></div>
          </a>
        </li>

        <li class="header-title">Sidebar Background</li>
        <li class="adjustments-line">
          <a href="javascript:void(0)" class="switch-trigger background-color">
            <div class="ml-auto mr-auto">
              <span class="badge filter badge-black" data-background-color="black"></span>
              <span class="badge filter badge-white" data-background-color="white"></span>
              <span class="badge filter badge-red"   data-background-color="red"></span>
            </div>
            <div class="clearfix"></div>
          </a>
        </li>

        <li class="adjustments-line">
          <a href="javascript:void(0)" class="switch-trigger">
            <p>Sidebar Mini</p>
            <label class="ml-auto">
              <div class="togglebutton switch-sidebar-mini">
                <label>
                  <input type="checkbox">
                  <span class="toggle"></span>
                </label>
              </div>
            </label>
            <div class="clearfix"></div>
          </a>
        </li>

        <li class="adjustments-line">
          <a href="javascript:void(0)" class="switch-trigger">
            <p>Sidebar Images</p>
            <label class="switch-mini ml-auto">
              <div class="togglebutton switch-sidebar-image">
                <label>
                  <input type="checkbox" checked>
                  <span class="toggle"></span>
                </label>
              </div>
            </label>
            <div class="clearfix"></div>
          </a>
        </li>

        <li class="header-title">Images</li>
        <li class="active">
          <a class="img-holder switch-trigger" href="javascript:void(0)">
            <img src="{{ asset('material/img/sidebarpelet.jpg') }}" alt="">
          </a>
        </li>
        <li >
          <a class="img-holder switch-trigger" href="javascript:void(0)">
            <img src="{{ asset('material/img/sidebar-1.jpg') }}" alt="">
          </a>
        </li>
        <li>
          <a class="img-holder switch-trigger" href="javascript:void(0)">
            <img src="{{ asset('material/img/sidebar-2.jpg') }}" alt="">
          </a>
        </li>
        <li>
          <a class="img-holder switch-trigger" href="javascript:void(0)">
            <img src="{{ asset('material/img/sidebar-3.jpg') }}" alt="">
          </a>
        </li>
        <li>
          <a class="img-holder switch-trigger" href="javascript:void(0)">
            <img src="{{ asset('material/img/sidebar-4.jpg') }}" alt="">
          </a>
        </li>
      </ul>
    </div>
  </div>
  @endif

  <!-- Core JS -->
  <script src="{{ asset('material/js/core/jquery.min.js') }}"></script>
  <script src="{{ asset('material/js/core/popper.min.js') }}"></script>
  <script src="{{ asset('material/js/core/bootstrap-material-design.min.js') }}"></script>
  <script src="{{ asset('material/js/plugins/perfect-scrollbar.jquery.min.js') }}"></script>
  <!-- Material Dashboard core (necesario para clases/atributos del template) -->
  <script src="{{ asset('material/js/material-dashboard.js?v=2.1.0') }}"></script>

  {{-- === Persistencia del Sidebar (localStorage) === --}}
  <script>
    $(function () {
      var $body = $('body');
      var $sidebar = $('.sidebar');
      var $sidebarBg = $('.sidebar .sidebar-background'); // existe en el template
      var STORAGE_KEYS = {
        color: 'sidebar-color',
        bgColor: 'sidebar-bg',
        image: 'sidebar-image',
        imageEnabled: 'sidebar-image-enabled',
        mini: 'sidebar-mini'
      };

      // ---- Aplicar valores guardados al cargar ----
      var savedColor = localStorage.getItem(STORAGE_KEYS.color);
      if (savedColor) $sidebar.attr('data-color', savedColor);

      var savedBg = localStorage.getItem(STORAGE_KEYS.bgColor);
      if (savedBg) $sidebar.attr('data-background-color', savedBg);

      var savedImage = localStorage.getItem(STORAGE_KEYS.image);
      if (savedImage && $sidebarBg.length) {
        $sidebarBg.css('background-image', 'url(' + savedImage + ')');
      }

      var savedImageEnabled = localStorage.getItem(STORAGE_KEYS.imageEnabled);
      if (savedImageEnabled !== null) {
        var enabled = savedImageEnabled === '1';
        $('.switch-sidebar-image input').prop('checked', enabled);
        if ($sidebarBg.length) {
          $sidebarBg.toggle(enabled);
        }
      }

      var savedMini = localStorage.getItem(STORAGE_KEYS.mini);
      if (savedMini === '1') {
        $body.addClass('sidebar-mini');
        $('.switch-sidebar-mini input').prop('checked', true);
      } else {
        $body.removeClass('sidebar-mini');
        $('.switch-sidebar-mini input').prop('checked', false);
      }

      // ---- Eventos para guardar cambios ----
      // Color principal
      $('.fixed-plugin .badge[data-color]').on('click', function () {
        var color = $(this).data('color');
        $sidebar.attr('data-color', color);
        localStorage.setItem(STORAGE_KEYS.color, color);
      });

      // Color de fondo
      $('.fixed-plugin .badge[data-background-color]').on('click', function () {
        var bg = $(this).data('background-color');
        $sidebar.attr('data-background-color', bg);
        localStorage.setItem(STORAGE_KEYS.bgColor, bg);
      });

      // Imagen seleccionada
      $('.fixed-plugin .img-holder').on('click', function () {
        var newImg = $(this).find('img').attr('src');
        if ($sidebarBg.length) {
          $sidebarBg.css('background-image', 'url(' + newImg + ')');
        }
        localStorage.setItem(STORAGE_KEYS.image, newImg);
      });

      // Mostrar/Ocultar imagen
      $('.switch-sidebar-image input').on('change', function () {
        var enabled = $(this).is(':checked');
        if ($sidebarBg.length) {
          $sidebarBg.toggle(enabled);
        }
        localStorage.setItem(STORAGE_KEYS.imageEnabled, enabled ? '1' : '0');
      });

      // Sidebar mini
      $('.switch-sidebar-mini input').on('change', function () {
        var mini = $(this).is(':checked');
        $body.toggleClass('sidebar-mini', mini);
        localStorage.setItem(STORAGE_KEYS.mini, mini ? '1' : '0');
      });
    });
  </script>

  @stack('js')
</body>
</html>
