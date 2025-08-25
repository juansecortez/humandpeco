@extends('layouts.app', [
  'class' => 'off-canvas-sidebar',
  'classPage' => 'login-page',
  'activePage' => 'login',
  'title' => __('HumandPeco'),
  'pageBackground' => asset("material").'/img/login.jpg'
])

@section('content')
<style>
  /* Fondo para la página de login */
  body.login-page {
    background-image: url('{{ asset("material/img/login.jpg") }}');
    background-repeat: no-repeat;
    background-position: center center;
    background-size: cover;
    min-height: 100vh;
  }
  /* Si tu preset usa estos contenedores, cúbrelos también */
  .full-page-background,
  .page-header {
    background-size: cover !important;
    background-position: center !important;
    background-repeat: no-repeat !important;
  }

  /* En móviles evita fixed (puede “cortar” la imagen) */
  @media (max-width: 991.98px) {
    .full-page-background,
    .page-header {
      background-attachment: scroll !important;
    }
  }
</style>


<div class="container">

    <div class="row">
      <div class="col-lg-4 col-md-6 col-sm-8 ml-auto mr-auto">
        <form class="form" method="POST" action="{{ route('login') }}">
          @csrf

          <div class="card card-login card-hidden">
            <div class="card-header card-header-warning text-center">
              <h4 class="card-title">{{ __('Login') }}</h4>
          
            </div>
            <div class="card-body ">
              <span class="form-group  bmd-form-group email-error {{ $errors->has('email') ? ' has-danger' : '' }}" >
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text">
                      <i class="material-icons">email</i>
                    </span>
                  </div>
                  <input type="email" class="form-control err-email" id="exampleEmails" name="email" placeholder="{{ __('Email...') }}" value="{{ old('email', 'admin@material.com') }}" required>
                  @include('alerts.feedback', ['field' => 'email'])
                </div>
              </span>
              <span class="form-group bmd-form-group{{ $errors->has('password') ? ' has-danger' : '' }}">
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text">
                      <i class="material-icons">lock_outline</i>
                    </span>
                  </div>
                  <input type="password" class="form-control" id="examplePassword" name="password" placeholder="{{ __('Password...') }}" value="secret" required>
                  @include('alerts.feedback', ['field' => 'password'])
                </div>
              </span>
          
            </div>
            <div class="card-footer justify-content-center" id="login">
              <button type="submit" class="btn btn-rose btn-link btn-lg">{{ __('Lets Go') }}</button>
            </div>
          </div>
        </form>

      </div>
    </div>
  </div>
@endsection

@push('js')
<script>
  $(document).ready(function() {
    md.checkFullPageBackgroundImage();
    setTimeout(function() {
      // after 1000 ms we add the class animated to the login/register card
      $('.card').removeClass('card-hidden');
    }, 700);
  });
</script>
@endpush
