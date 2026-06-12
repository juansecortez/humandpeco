<div class="sidebar" data-color="danger" data-background-color="black"
    data-image="{{ asset('material') }}/img/sidebarpelet.jpg">
    <!--
    Tip 1: You can change the color of the sidebar using: data-color="purple | azure | green | orange | danger"

    Tip 2: you can also add an image using data-image tag
-->
    <div class="logo text-center">

        <!-- Logo grande (sidebar abierto) -->
        <a href="#" class="logo-big">
            <img src="{{ asset('material/img/logo2.png') }}" alt="Logo grande">
        </a>

        <!-- Logo chico (sidebar cerrado) -->
        <a href="#" class="logo-small">
            <img src="{{ asset('material/img/logo3.png') }}" alt="Logo chico">
        </a>

        <a class="simple-text logo-normal d-block">
            {{ __('HUMANDPECO') }}
        </a>

        <style>
            .username-text {
                display: -webkit-box;
                -webkit-line-clamp: 2;
                /* máximo 2 líneas */
                -webkit-box-orient: vertical;
                overflow: hidden;
                text-overflow: ellipsis;
                max-width: 150px;
                /* ajusta al ancho que necesites */
                white-space: normal;
            }

            /* Tamaños (ajusta a gusto) */
            .sidebar .logo .logo-big img {
                max-width: 150px;
                height: auto;
            }

            .sidebar .logo .logo-small img {
                max-width: 50px;
                height: auto;
            }

            /* Estado ABIERTO: mostrar solo grande */
            .sidebar .logo .logo-big {
                display: block !important;
            }

            .sidebar .logo .logo-small {
                display: none !important;
            }

            /* Estado CERRADO (mini): invertir */
            body.sidebar-mini .sidebar .logo .logo-big {
                display: none !important;
            }

            body.sidebar-mini .sidebar .logo .logo-small {
                display: block !important;
            }

            /* (Opcional) Ocultar el texto en modo mini */
            body.sidebar-mini .sidebar .logo .logo-normal {
                display: none !important;
            }
        </style>
    </div>







    <div class="sidebar-wrapper">
        <div class="user">
            <div class="photo">
                <img src="{{ auth()->user()->profilePicture() }}" style="border-radius:8px;object-fit:cover;" onerror="this.src='{{ asset(config('users.default_avatar')) }}'" />
            </div>
            <div class="user-info">
                <a data-toggle="collapse" href="#collapseExample" class="username">
                    <span class="username-text">
                        {{ auth()->user()->name }}
                        <b class="caret"></b>
                    </span>
                </a>
                <div class="collapse" id="collapseExample">
                    <ul class="nav">
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('profile.edit') }}">
                                <span class="sidebar-mini"> MP </span>
                                <span class="sidebar-normal"> My Profile </span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="logout-btn" href="{{ route('logout') }}"
                                onclick="event.preventDefault();document.getElementById('logout-form').submit();">{{ __('Log out') }}</a>
                            <span class="sidebar-mini"> L </span>
                            <span class="sidebar-normal"> Logout </span>
                            </a>
                        </li>
                    </ul>
                </div>

            </div>
        </div>

        <ul class="nav">
            <li class="nav-item{{ $activePage == 'dashboard' ? ' active' : '' }}">
                <a class="nav-link" href="{{ route('home') }}">
                    <i class="material-icons">dashboard</i>
                    <p>{{ __('Dashboard') }}</p>
                </a>
            </li>
            <li class="nav-item {{ $menuParent == 'laravel' || $activePage == 'dashboard' ? ' active' : '' }}">
                <a class="nav-link" data-toggle="collapse" href="#laravelExample"
                    {{ $menuParent == 'laravel' || $activePage == 'dashboard' ? ' aria-expanded="true"' : '' }}>
                    <i class="material-icons">settings</i>
                    <p>{{ __('Administración') }}
                        <b class="caret"></b>
                    </p>
                </a>
                <div class="collapse {{ $menuParent == 'dashboard' || $menuParent == 'laravel' ? ' show' : '' }}"
                    id="laravelExample">
                    <ul class="nav">
                        <li class="nav-item{{ $activePage == 'profile' ? ' active' : '' }}">
                            <a class="nav-link" href="{{ route('profile.edit') }}">
                                <span class="sidebar-mini"> UP </span>
                                <span class="sidebar-normal">{{ __('User profile') }} </span>
                            </a>
                        </li>
                        @can('manage-users', App\User::class)
                            <li class="nav-item{{ $activePage == 'role-management' ? ' active' : '' }}">
                                <a class="nav-link" href="{{ route('role.index') }}">
                                    <span class="sidebar-mini"> RM </span>
                                    <span class="sidebar-normal"> {{ __('Role Management') }} </span>
                                </a>
                            </li>
                        @endcan
                        @can('manage-users', App\User::class)
                            <li class="nav-item{{ $activePage == 'user-management' ? ' active' : '' }}">
                                <a class="nav-link" href="{{ route('user.index') }}">
                                    <span class="sidebar-mini"> UM </span>
                                    <span class="sidebar-normal"> {{ __('User Management') }} </span>
                                </a>
                            </li>
                        @endcan



                    </ul>
                </div>
            </li>


            <li class="nav-item {{ ($menuParent ?? '') == 'solicitudes-fc' ? 'active' : '' }}">
                <a class="nav-link" data-toggle="collapse" href="#SolicitudesFcMenu"
                    {{ ($menuParent ?? '') == 'solicitudes-fc' ? 'aria-expanded="true"' : '' }}>
                    <i class="material-icons">assignment</i>
                    <p> {{ __('Solicitudes FC') }}
                        <b class="caret"></b>
                    </p>
                </a>
                <div class="collapse{{ ($menuParent ?? '') == 'solicitudes-fc' ? ' show' : '' }}" id="SolicitudesFcMenu">
                    <ul class="nav">
                        @foreach(config('time_off_policies.fc', []) as $slug => $policy)
                        <li class="nav-item{{ ($activePage ?? '') == ($policy['active_page'] ?? '') ? ' active' : '' }}">
                            <a class="nav-link" href="{{ route('solicitudes.admin', ['group' => 'fc', 'policy' => $slug]) }}">
                                <span class="sidebar-mini">{{ strtoupper(substr($policy['label'], 0, 2)) }}</span>
                                <span class="sidebar-normal"> {{ __($policy['label']) }} </span>
                            </a>
                        </li>
                        @endforeach
                        <li class="nav-item{{ ($activePage ?? '') == 'solicitudes-fc-status' ? ' active' : '' }}">
                            <a class="nav-link" href="{{ route('vacaciones.status') }}">
                                <span class="sidebar-mini"> ES </span>
                                <span class="sidebar-normal"> {{ __('Estatus SAP') }} </span>
                            </a>
                        </li>
                    </ul>
                </div>
            </li>

            <li class="nav-item {{ ($menuParent ?? '') == 'solicitudes-dc' ? 'active' : '' }}">
                <a class="nav-link" data-toggle="collapse" href="#SolicitudesDcMenu"
                    {{ ($menuParent ?? '') == 'solicitudes-dc' ? 'aria-expanded="true"' : '' }}>
                    <i class="material-icons">assignment_ind</i>
                    <p> {{ __('Solicitudes DC') }}
                        <b class="caret"></b>
                    </p>
                </a>
                <div class="collapse{{ ($menuParent ?? '') == 'solicitudes-dc' ? ' show' : '' }}" id="SolicitudesDcMenu">
                    <ul class="nav">
                        @foreach(config('time_off_policies.dc', []) as $slug => $policy)
                        <li class="nav-item{{ ($activePage ?? '') == ($policy['active_page'] ?? '') ? ' active' : '' }}">
                            <a class="nav-link" href="{{ route('solicitudes.admin', ['group' => 'dc', 'policy' => $slug]) }}">
                                <span class="sidebar-mini">{{ strtoupper(substr($policy['label'], 0, 2)) }}</span>
                                <span class="sidebar-normal"> {{ __($policy['label']) }} </span>
                            </a>
                        </li>
                        @endforeach
                        <li class="nav-item{{ ($activePage ?? '') == 'solicitudes-dc-vacaciones-status' ? ' active' : '' }}">
                            <a class="nav-link" href="{{ route('vacaciones.dcVacacionesStatus') }}">
                                <span class="sidebar-mini">EV</span>
                                <span class="sidebar-normal"> {{ __('Estatus Vacaciones DC') }} </span>
                            </a>
                        </li>
                        <li class="nav-item{{ ($activePage ?? '') == 'solicitudes-dc-anticipos-status' ? ' active' : '' }}">
                            <a class="nav-link" href="{{ route('vacaciones.dcAnticiposStatus') }}">
                                <span class="sidebar-mini">EA</span>
                                <span class="sidebar-normal"> {{ __('Estatus Anticipos DC') }} </span>
                            </a>
                        </li>
                    </ul>
                </div>
            </li>

            <li class="nav-item {{ ($menuParent ?? '') == 'saldos' ? 'active' : '' }}">
                <a class="nav-link" href="{{ route('saldos.index') }}">
                    <i class="material-icons">account_balance_wallet</i>
                    <p> {{ __('Saldos de vacaciones') }} </p>
                </a>
            </li>

            <!-- (sidebar cerrado)
      <li class="nav-item {{ $menuParent == 'pages' ? 'active' : '' }}">
        <a class="nav-link" data-toggle="collapse" href="#pagesExamples" {{ $menuParent == 'Pages' ? 'aria-expanded="true"' : '' }}>
          <i class="material-icons">image</i>
          <p> {{ __('Pages') }}
            <b class="caret"></b>
          </p>
        </a>
        <div class="collapse{{ $menuParent == 'pages' ? ' show' : '' }}" id="pagesExamples">
          <ul class="nav">
            <li class="nav-item">
              <a class="nav-link" href="{{ route('page.pricing') }}">
                <span class="sidebar-mini"> P </span>
                <span class="sidebar-normal"> {{ __('Pricing') }} </span>
              </a>
            </li>
            <li class="nav-item">
              <a class="nav-link" href="{{ route('page.rtl-support') }}">
                <span class="sidebar-mini"> RS </span>
                <span class="sidebar-normal"> {{ __('RTL Support') }} </span>
              </a>
            </li>
            <li class="nav-item{{ $activePage == 'timeline' ? ' active' : '' }} ">
              <a class="nav-link" href="{{ route('page.timeline') }}">
                <span class="sidebar-mini"> T </span>
                <span class="sidebar-normal"> {{ __('Timeline') }} </span>
              </a>
            </li>
            <li class="nav-item ">
              <a class="nav-link" href="{{ route('page.lock') }}">
                <span class="sidebar-mini"> LSP </span>
                <span class="sidebar-normal"> {{ __('Lock Screen Page') }} </span>
              </a>
            </li>
            <li class="nav-item ">
              <a class="nav-link" href="{{ route('profile.edit') }}">
                <span class="sidebar-mini"> UP </span>
                <span class="sidebar-normal"> User Profile </span>
              </a>
            </li>
            <li class="nav-item ">
              <a class="nav-link" href="{{ route('page.error') }}">
                <span class="sidebar-mini"> E </span>
                <span class="sidebar-normal"> Error Page </span>
              </a>
            </li>
          </ul>
        </div>
      </li>
      <li class="nav-item {{ $menuParent == 'compoments' ? 'active' : '' }}">
        <a class="nav-link" data-toggle="collapse" href="#componentsExamples" {{ $menuParent == 'components' ? 'aria-expanded="true"' : '' }}>
          <i class="material-icons">apps</i>
          <p> Components
            <b class="caret"></b>
          </p>
        </a>
        <div class="collapse {{ $menuParent == 'components' ? ' show' : '' }}" id="componentsExamples">
          <ul class="nav">
            <li class="nav-item ">
              <a class="nav-link" data-toggle="collapse" href="#componentsCollapse">
                <span class="sidebar-mini"> MLT </span>
                <span class="sidebar-normal"> Multi Level Collapse
                  <b class="caret"></b>
                </span>
              </a>
              <div class="collapse" id="componentsCollapse">
                <ul class="nav">
                  <li class="nav-item ">
                    <a class="nav-link" href="#0">
                      <span class="sidebar-mini"> E </span>
                      <span class="sidebar-normal"> Example </span>
                    </a>
                  </li>
                </ul>
              </div>
            </li>
            <li class="nav-item{{ $activePage == 'buttons' ? ' active' : '' }} ">
              <a class="nav-link" href="{{ route('page.buttons') }}">
                <span class="sidebar-mini"> B </span>
                <span class="sidebar-normal"> {{ __('Buttons') }} </span>
              </a>
            </li>
            <li class="nav-item{{ $activePage == 'grid' ? ' active' : '' }} ">
              <a class="nav-link" href="{{ route('page.grid') }}">
                <span class="sidebar-mini"> GS </span>
                <span class="sidebar-normal"> {{ __('Grid System') }} </span>
              </a>
            </li>
            <li class="nav-item{{ $activePage == 'panels' ? ' active' : '' }}">
              <a class="nav-link" href="{{ route('page.panels') }}">
                <span class="sidebar-mini"> P </span>
                <span class="sidebar-normal"> {{ __('Panels') }} </span>
              </a>
            </li>
            <li class="nav-item{{ $activePage == 'sweet-alert' ? ' active' : '' }} ">
              <a class="nav-link" href="{{ route('page.sweet-alert') }}">
                <span class="sidebar-mini"> SA </span>
                <span class="sidebar-normal"> {{ __('Sweet Alert') }} </span>
              </a>
            </li>
            <li class="nav-item{{ $activePage == 'notifications' ? ' active' : '' }} ">
              <a class="nav-link" href="{{ route('page.notifications') }}">
                <span class="sidebar-mini"> N </span>
                <span class="sidebar-normal"> {{ __('Notifications') }} </span>
              </a>
            </li>
            <li class="nav-item{{ $activePage == 'icons' ? ' active' : '' }} ">
              <a class="nav-link" href="{{ route('page.icons') }}">
                <span class="sidebar-mini"> I </span>
                <span class="sidebar-normal"> {{ __('Icons') }} </span>
              </a>
            </li>
            <li class="nav-item{{ $activePage == 'typography' ? ' active' : '' }} ">
              <a class="nav-link" href="{{ route('page.typography') }}">
                <span class="sidebar-mini"> T </span>
                <span class="sidebar-normal"> {{ __('Typography') }} </span>
              </a>
            </li>
          </ul>
        </div>
      </li>
      <li class="nav-item {{ $menuParent == 'forms' ? ' active' : '' }}">
        <a class="nav-link" data-toggle="collapse" href="#formsExamples" {{ $menuParent == 'forms' ? 'aria-expanded="true"' : '' }}>
          <i class="material-icons">content_paste</i>
          <p> Forms
            <b class="caret"></b>
          </p>
        </a>
        <div class="collapse {{ $menuParent == 'forms' ? 'show' : '' }}" id="formsExamples">
          <ul class="nav">
            <li class="nav-item{{ $activePage == 'form_regular' ? ' active' : '' }} ">
              <a class="nav-link" href="{{ route('page.regular_forms') }}">
                <span class="sidebar-mini"> RF </span>
                <span class="sidebar-normal"> {{ __('Regular Forms') }} </span>
              </a>
            </li>
            <li class="nav-item{{ $activePage == 'form_extended' ? ' active' : '' }} ">
              <a class="nav-link" href="{{ route('page.extended_forms') }}">
                <span class="sidebar-mini"> EF </span>
                <span class="sidebar-normal"> {{ __('Extended Forms') }} </span>
              </a>
            </li>
            <li class="nav-item{{ $activePage == 'form_validation' ? ' active' : '' }} ">
              <a class="nav-link" href="{{ route('page.validation_forms') }}">
                <span class="sidebar-mini"> VF </span>
                <span class="sidebar-normal"> {{ __('Validation Forms') }} </span>
              </a>
            </li>
            <li class="nav-item{{ $activePage == 'form_wizard' ? ' active' : '' }} ">
              <a class="nav-link" href="{{ route('page.wizard_forms') }}">
                <span class="sidebar-mini"> W </span>
                <span class="sidebar-normal"> {{ __('Wizard') }} </span>
              </a>
            </li>
          </ul>
        </div>
      </li>
      <li class="nav-item {{ $menuParent == 'tables' ? 'active' : '' }}">
        <a class="nav-link" data-toggle="collapse" href="#tablesExamples" {{ $menuParent == 'tables' ? 'aria-expanded="true"' : '' }}>
          <i class="material-icons">grid_on</i>
          <p> {{ __('Tables') }}
            <b class="caret"></b>
          </p>
        </a>
        <div class="collapse {{ $menuParent == 'tables' ? 'show' : '' }}" id="tablesExamples">
          <ul class="nav">
            <li class="nav-item{{ $activePage == 'regular' ? ' active' : '' }}  ">
              <a class="nav-link" href="{{ route('page.regular_tables') }}">
                <span class="sidebar-mini"> RT </span>
                <span class="sidebar-normal"> {{ __('Regular Tables') }} </span>
              </a>
            </li>
            <li class="nav-item{{ $activePage == 'extended' ? ' active' : '' }}  ">
              <a class="nav-link" href="{{ route('page.extended_tables') }}">
                <span class="sidebar-mini"> ET </span>
                <span class="sidebar-normal"> {{ __('Extended Tables') }} </span>
              </a>
            </li>
            <li class="nav-item{{ $activePage == 'datatables' ? ' active' : '' }} ">
              <a class="nav-link" href="{{ route('page.datatable_tables') }}">
                <span class="sidebar-mini"> DT </span>
                <span class="sidebar-normal"> {{ __('DataTables.net') }} </span>
              </a>
            </li>
          </ul>
        </div>
      </li>
     
      <li class="nav-item{{ $activePage == 'widgets' ? ' active' : '' }} ">
        <a class="nav-link" href="{{ route('page.widgets') }}">
          <i class="material-icons">widgets</i>
          <p> Widgets </p>
        </a>
      </li> -->

        </ul>
    </div>
</div>
