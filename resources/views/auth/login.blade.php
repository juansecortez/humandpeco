@extends('layouts.app', [
  'class' => 'off-canvas-sidebar login-page-body',
  'classPage' => 'login-page',
  'activePage' => 'login',
  'title' => 'HumandPeco',
  'pageBackground' => asset('material/img/login.jpg'),
])

@section('content')
<style>
  html, body.login-page-body {
    height: 100%;
    overflow: hidden;
  }

  body.login-page-body .wrapper-full-page {
    height: 100vh;
    overflow: hidden;
  }

  body.login-page-body .wrapper-full-page .page-header.login-page {
    height: 100vh !important;
    min-height: 100vh !important;
    max-height: 100vh !important;
    padding: 0 !important;
    margin: 0 !important;
    display: block !important;
    align-items: unset !important;
    justify-content: unset !important;
    text-align: left !important;
    overflow: hidden;
    background-size: cover !important;
    background-position: center center !important;
    background-repeat: no-repeat !important;
  }

  /* Overlays del theme encima del contenido — no deben capturar clics */
  .page-header.login-page.header-filter::before,
  .page-header.login-page.header-filter::after {
    pointer-events: none !important;
  }

  .login-shell {
    position: relative;
    z-index: 10;
    width: 100%;
    height: 100%;
    padding: 0 0 3.25rem;
    box-sizing: border-box;
    pointer-events: auto;
  }

  .login-brand {
    position: absolute;
    left: clamp(2rem, 7vw, 9rem);
    top: 50%;
    transform: translateY(-50%);
    max-width: min(520px, 42vw);
    color: #fff;
    pointer-events: none;
  }
  .login-brand .kicker {
    letter-spacing: .35em;
    font-size: .85rem;
    opacity: .85;
    margin-bottom: .75rem;
  }
  .login-brand h1 {
    font-size: clamp(2rem, 4vw, 3.2rem);
    font-weight: 700;
    line-height: 1.15;
    margin: 0;
  }
  .login-brand p {
    margin-top: 1rem;
    opacity: .9;
    font-size: 1.05rem;
  }

  .login-panel {
    position: absolute;
    right: clamp(2rem, 11vw, 15rem);
    top: 50%;
    transform: translateY(-50%);
    z-index: 11;
    width: min(400px, calc(100vw - 2rem));
    margin: 0;
    background: rgba(255, 255, 255, 0.10);
    backdrop-filter: blur(20px) saturate(150%);
    -webkit-backdrop-filter: blur(20px) saturate(150%);
    border: 1px solid rgba(255, 255, 255, 0.25);
    border-radius: 20px;
    padding: 2.25rem 2rem 1.75rem;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.15);
    color: #fff;
  }
  .login-panel .logo-wrap {
    text-align: center;
    margin-bottom: 1.5rem;
  }
  .login-panel .logo-wrap img {
    max-width: 220px;
    height: auto;
  }
  .login-panel label {
    font-size: .78rem;
    letter-spacing: .08em;
    text-transform: uppercase;
    opacity: .75;
    margin-bottom: .35rem;
  }
  .login-panel .form-control {
    background: rgba(255, 255, 255, 0.12);
    border: 1px solid rgba(255, 255, 255, 0.25);
    color: #fff;
    border-radius: 10px;
    min-height: 46px;
  }
  .login-panel .form-control:focus {
    background: rgba(255, 255, 255, 0.18);
    border-color: rgba(255, 255, 255, 0.4);
    color: #fff;
    box-shadow: 0 0 0 2px rgba(255, 255, 255, 0.08);
  }
  .login-panel .form-control::placeholder { color: rgba(255,255,255,.55); }

  .password-wrap {
    position: relative;
  }
  .password-wrap .form-control {
    padding-right: 2.75rem;
  }
  .password-toggle {
    position: absolute;
    right: .35rem;
    top: 50%;
    transform: translateY(-50%);
    border: 0;
    background: transparent;
    color: rgba(255, 255, 255, .75);
    width: 2.25rem;
    height: 2.25rem;
    border-radius: 8px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0;
  }
  .password-toggle:hover,
  .password-toggle:focus {
    color: #fff;
    outline: none;
    background: rgba(255, 255, 255, 0.08);
  }

  .login-options {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-top: .75rem;
    gap: .75rem;
    flex-wrap: wrap;
  }
  .login-remember {
    display: flex;
    align-items: center;
    gap: .45rem;
    margin: 0;
    font-size: .88rem;
    cursor: pointer;
    user-select: none;
    opacity: .9;
  }
  .login-remember input[type="checkbox"] {
    width: 1rem;
    height: 1rem;
    margin: 0;
    cursor: pointer;
    accent-color: #1a3a5c;
  }

  .login-panel .btn-login {
    width: 100%;
    margin-top: 1.25rem;
    min-height: 48px;
    border-radius: 10px;
    font-weight: 700;
    letter-spacing: .06em;
    background: rgba(26, 58, 92, 0.75);
    backdrop-filter: blur(8px);
    border: 1px solid rgba(255, 255, 255, 0.15);
    color: #fff;
    cursor: pointer;
  }
  .login-panel .btn-login:hover {
    background: rgba(36, 74, 114, 0.85);
    color: #fff;
  }
  .login-panel .help-text {
    text-align: center;
    font-size: .82rem;
    opacity: .65;
    margin-top: 1.25rem;
  }
  .login-alert {
    background: rgba(220, 53, 69, .15);
    border: 1px solid rgba(220, 53, 69, .35);
    color: #ffb4b4;
    border-radius: 10px;
    padding: .75rem 1rem;
    margin-bottom: 1rem;
    font-size: .9rem;
  }

  .page-header.login-page .footer {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    z-index: 12;
    width: 100%;
    padding: .65rem 0;
    pointer-events: none;
  }
  .page-header.login-page .footer .copyright {
    color: rgba(255, 255, 255, 0.85);
    font-size: .82rem;
  }

  @media (max-width: 991px) {
    .login-shell {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding-top: 4.5rem;
      padding-bottom: 3.25rem;
    }
    .login-panel {
      position: static;
      transform: none;
      margin: 0 auto;
      width: calc(100% - 2rem);
      max-width: 400px;
      padding: 1.75rem 1.5rem 1.5rem;
    }
    .login-panel .logo-wrap img {
      max-width: 180px;
    }
  }
</style>

<div class="login-shell">
  <div class="login-brand d-none d-lg-block">
    <div class="kicker">HUMAND · PEÑA COLORADA</div>
    <h1>Integración Humand — SAP</h1>
    <p>Solicitudes de vacaciones, saldos y envíos a nómina.</p>
  </div>

  <div class="login-panel">
    <div class="logo-wrap">
      <img src="{{ asset('material/img/logo2.png') }}" alt="Peña Colorada">
    </div>

    @if ($errors->has('msgError'))
      <div class="login-alert">{{ $errors->first('msgError') }}</div>
    @endif

    <form method="POST" action="{{ route('login') }}" autocomplete="on" id="login-form">
      @csrf
      <div class="form-group">
        <label for="username">Usuario</label>
        <input type="text" class="form-control" id="username" name="username"
          value="{{ old('username', old('email')) }}" placeholder="Usuario o correo" required autofocus>
      </div>
      <div class="form-group">
        <label for="password">Contraseña</label>
        <div class="password-wrap">
          <input type="password" class="form-control" id="password" name="password"
            placeholder="Contraseña" required autocomplete="current-password">
          <button type="button" class="password-toggle" id="password-toggle"
            aria-label="Mostrar contraseña" title="Mostrar contraseña">
            <i class="fa fa-eye" aria-hidden="true"></i>
          </button>
        </div>
      </div>
      <div class="login-options">
        <label class="login-remember" for="remember">
          <input type="checkbox" name="remember" id="remember" value="1"
            {{ old('remember') ? 'checked' : '' }}>
          Recordarme
        </label>
      </div>
      <button type="submit" class="btn btn-login">INICIAR SESIÓN</button>
    </form>

    <p class="help-text">¿Problemas para acceder? Contacta a IT.</p>
  </div>
</div>
@endsection

@push('js')
<script>
  $(function () {
    var $password = $('#password');
    var $toggle = $('#password-toggle');
    var $icon = $toggle.find('i');

    $toggle.on('click', function () {
      var visible = $password.attr('type') === 'text';
      $password.attr('type', visible ? 'password' : 'text');
      $icon.toggleClass('fa-eye fa-eye-slash');
      $toggle.attr({
        'aria-label': visible ? 'Mostrar contraseña' : 'Ocultar contraseña',
        'title': visible ? 'Mostrar contraseña' : 'Ocultar contraseña'
      });
    });
  });
</script>
@endpush
