<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Artisan;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
| Aquí registras las rutas web de tu aplicación.
*/

/**
 * Utilidades de depuración y caché SOLO en local
 */
if (app()->environment('local')) {

    // Limpia caches varias (NO route:cache porque hay closures)
    Route::get('/refresh', function () {
        Artisan::call('cache:clear');
        Artisan::call('config:clear');
        Artisan::call('view:clear');
        Artisan::call('route:clear');
        Artisan::call('config:cache');
        return response()->json(['message' => 'Cache, config y views refrescadas correctamente']);
    })->name('refresh');

    // Verifica que el guard 'organigrama' exista en runtime
    Route::get('/debug-auth-guards', function () {
        return response()->json([
            'guards'        => array_keys(config('auth.guards') ?? []),
            'providers'     => array_keys(config('auth.providers') ?? []),
            'default_guard' => config('auth.defaults.guard'),
        ]);
    });

    // Smoke test del guard
    Route::get('/guard-test', function () {
        Auth::guard('organigrama'); // resolver sin usarlo
        return 'OK organigrama';
    });

    // (opcional) quién está autenticado
    Route::get('/whoami', function () {
        return response()->json([
            'web' => [
                'check' => Auth::guard('web')->check(),
                'user'  => optional(Auth::guard('web')->user())->only(['id','name','usuarioid','nombre']),
            ],
            'organigrama' => [
                'check' => Auth::guard('organigrama')->check(),
                'user'  => optional(Auth::guard('organigrama')->user())->only(['usuarioid','nombre']),
            ],
        ]);
    });
}

/**
 * Redirección raíz al login
 */
Route::get('/', function () {
    return redirect('login');
});

/**
 * Auth scaffolding (login, registro, reset, etc.)
 */
Auth::routes();

/**
 * Grupo protegido por auth con MULTI-guards (web O organigrama)
 */
Route::group(['middleware' => 'auth:web,organigrama'], function () {

    // Inicio/Dashboard
 
    Route::get('home', 'HomeController@index')->name('home');
    Route::get('dashboard', 'HomeController@index')->name('home.dashboard');

    // Pages de ejemplo
    Route::get('pricing', 'ExamplePagesController@pricing')->name('page.pricing');
    Route::get('lock', 'ExamplePagesController@lock')->name('page.lock');
    Route::get('error', ['as' => 'page.error', 'uses' => 'ExamplePagesController@error']);

    // Resources
    Route::resource('category', 'CategoryController', ['except' => ['show']]);
    Route::resource('tag', 'TagController', ['except' => ['show']]);
    Route::resource('item', 'ItemController', ['except' => ['show']]);
    Route::resource('role', 'RoleController', ['except' => ['show', 'destroy', 'create']]);
    Route::resource('user', 'UserController', ['except' => ['show']]);

    // Perfil
    Route::get('profile', ['as' => 'profile.edit', 'uses' => 'ProfileController@edit']);
    Route::put('profile', ['as' => 'profile.update', 'uses' => 'ProfileController@update']);
    Route::put('profile/password', ['as' => 'profile.password', 'uses' => 'ProfileController@password']);

    // Otros ejemplos
    Route::get('rtl-support', ['as' => 'page.rtl-support', 'uses' => 'ExamplePagesController@rtlSupport']);
    Route::get('timeline', ['as' => 'page.timeline', 'uses' => 'ExamplePagesController@timeline']);
    Route::get('widgets', ['as' => 'page.widgets', 'uses' => 'ExamplePagesController@widgets']);
    Route::get('charts', ['as' => 'page.charts', 'uses' => 'ExamplePagesController@charts']);
    Route::get('calendar', ['as' => 'page.calendar', 'uses' => 'ExamplePagesController@calendar']);

    Route::get('buttons', ['as' => 'page.buttons', 'uses' => 'ComponentPagesController@buttons']);
    Route::get('grid-system', ['as' => 'page.grid', 'uses' => 'ComponentPagesController@grid']);
    Route::get('panels', ['as' => 'page.panels', 'uses' => 'ComponentPagesController@panels']);
    Route::get('sweet-alert', ['as' => 'page.sweet-alert', 'uses' => 'ComponentPagesController@sweetAlert']);
    Route::get('notifications', ['as' => 'page.notifications', 'uses' => 'ComponentPagesController@notifications']);
    Route::get('icons', ['as' => 'page.icons', 'uses' => 'ComponentPagesController@icons']);
    Route::get('typography', ['as' => 'page.typography', 'uses' => 'ComponentPagesController@typography']);

    Route::get('regular-tables', ['as' => 'page.regular_tables', 'uses' => 'TablePagesController@regularTables']);
    Route::get('extended-tables', ['as' => 'page.extended_tables', 'uses' => 'TablePagesController@extendedTables']);
    Route::get('datatable-tables', ['as' => 'page.datatable_tables', 'uses' => 'TablePagesController@datatableTables']);

    Route::get('regular-form', ['as' => 'page.regular_forms', 'uses' => 'FormPagesController@regularForms']);
    Route::get('extended-form', ['as' => 'page.extended_forms', 'uses' => 'FormPagesController@extendedForms']);
    Route::get('validation-form', ['as' => 'page.validation_forms', 'uses' => 'FormPagesController@validationForms']);
    Route::get('wizard-form', ['as' => 'page.wizard_forms', 'uses' => 'FormPagesController@wizardForms']);

    Route::get('google-maps', ['as' => 'page.google_maps', 'uses' => 'MapPagesController@googleMaps']);
    Route::get('fullscreen-maps', ['as' => 'page.fullscreen_maps', 'uses' => 'MapPagesController@fullscreenMaps']);
    Route::get('vector-maps', ['as' => 'page.vector_maps', 'uses' => 'MapPagesController@vectorMaps']);
});
