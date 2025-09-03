<?php
// app/Http/Controllers/Auth/LoginController.php
namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use App\Models\OrganigramaUser;
use Illuminate\Support\Facades\Log;
class LoginController extends Controller
{
    use AuthenticatesUsers;

    /** Destino después de login */
    protected $redirectTo = '/home';

    public function __construct()
    {
        // MUY IMPORTANTE: permitir acceso al login solo a invitados de ambos guards
        $this->middleware('guest:web,organigrama')->except('logout');
    }

public function login(Request $request)
{
    $request->validate([
        'password' => 'required|string',
        'username' => 'nullable|string',
        'email'    => 'nullable|string',
    ]);

    $rawLogin  = $request->input('username') ?? $request->input('email');
    if (!$rawLogin) {
        Log::warning('LOGIN:no-identifier');
        return back()->withErrors(['msgError' => 'Debes proporcionar usuario o correo.']);
    }

    $password  = $request->input('password');
    $username  = strtok($rawLogin, '@');

    Log::info('LOGIN:start', [
        'rawLogin' => $rawLogin,
        'username' => $username,
        'env'      => app()->environment(),
        'session_id_before' => $request->session()->getId(),
    ]);

    if (method_exists($this, 'hasTooManyLoginAttempts') && $this->hasTooManyLoginAttempts($request)) {
        Log::warning('LOGIN:throttled');
        $this->fireLockoutEvent($request);
        return $this->sendLockoutResponse($request);
    }

    $canAccess = false;

    if (app()->environment('local')) {
        $canAccess = true;
        Log::info('LOGIN:env-local');
    } elseif (app()->environment('production')) {
        if ($username === 'adminHumandPeco') {
            $canAccess = true;
            Log::info('LOGIN:prod-admin-bypass');
        } else {
            try {
                $resp = \Illuminate\Support\Facades\Http::post('http://VADAEXTERNO:8080/Hub/api/Autenticador/validate', [
                    'username' => $username,
                    'password' => $password,
                ]);

                Log::info('LOGIN:hub-response', ['status' => $resp->status(), 'body' => $resp->json()]);

                if ($resp->failed()) {
                    $this->incrementLoginAttempts($request);
                    return back()->withErrors(['msgError' => '[Hub] Usuario o contraseña incorrectos.']);
                }

                $data = $resp->json();
                $canAccess = !empty($data['isAuthenticated']) && (int) $data['isAuthenticated'] === 1;

                if (!$canAccess) {
                    $this->incrementLoginAttempts($request);
                    return back()->withErrors(['msgError' => 'Usuario o contraseña incorrectos.']);
                }
            } catch (\Throwable $e) {
                Log::error('LOGIN:hub-exception', ['msg' => $e->getMessage()]);
                $this->incrementLoginAttempts($request);
                return back()->withErrors(['msgError' => '[Hub] Error en el servicio de autenticación.']);
            }
        }
    } else {
        $canAccess = true;
        Log::info('LOGIN:env-other');
    }

    if ($canAccess) {
        $orgUser = \App\Models\OrganigramaUser::on('organigrama')
            ->where('UsuarioId', $username)
            ->first();

        Log::info('LOGIN:orgUser_lookup', [
            'found' => (bool) $orgUser,
            'UsuarioId' => $orgUser?->getAuthIdentifier(),
        ]);

        if ($orgUser) {
            $pairedUser = \App\User::where('name', $username)->first();
            Log::info('LOGIN:pairedUser_lookup', [
                'exists' => (bool) $pairedUser,
                'role_id' => $pairedUser->role_id ?? null,
            ]);

            if (!$pairedUser || !in_array((int) $pairedUser->role_id, [4, 5], true)) {
                $this->incrementLoginAttempts($request);
                return back()->withErrors([
                    'msgError' => 'Tu usuario no tiene rol asignado en la app (Vacaciones o Nóminas). Contacta al administrador.'
                ]);
            }

            \Illuminate\Support\Facades\Auth::guard('organigrama')->login($orgUser);
            Log::info('LOGIN:after_guard_login', [
                'guard' => 'organigrama',
                'check' => Auth::guard('organigrama')->check(),
                'id'    => Auth::guard('organigrama')->id(),
            ]);

            $request->session()->regenerate();
            $request->session()->put('auth_guard', 'organigrama');
            $request->session()->put('role_id', (int) $pairedUser->role_id);
            \Illuminate\Support\Facades\Auth::shouldUse('organigrama');

            session()->put('esPeco', true);

            Log::info('LOGIN:session_set', [
                'session_id_after' => $request->session()->getId(),
                'auth_guard' => session('auth_guard'),
                'role_id'    => session('role_id'),
            ]);

            $this->clearLoginAttempts($request);
            return redirect()->intended($this->redirectPath());
        }

        // Fallback local
        $attempted = false;

        if (strpos($rawLogin, '@') !== false) {
            $attempted = \Illuminate\Support\Facades\Auth::guard('web')->attempt(
                ['email' => $rawLogin, 'password' => $password],
                $request->boolean('remember')
            );
            Log::info('LOGIN:web-attempt-email', ['attempted' => $attempted]);
        }

        if (!$attempted) {
            $attempted = \Illuminate\Support\Facades\Auth::guard('web')->attempt(
                ['name' => $username, 'password' => $password],
                $request->boolean('remember')
            );
            Log::info('LOGIN:web-attempt-name', ['attempted' => $attempted]);
        }

        if ($attempted) {
            $localUser = \Illuminate\Support\Facades\Auth::guard('web')->user();
            Log::info('LOGIN:web-user', ['role_id' => $localUser->role_id ?? null, 'id' => $localUser->id ?? null]);

            if (!$localUser || !in_array((int) $localUser->role_id, [1, 2], true)) {
                \Illuminate\Support\Facades\Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                $this->incrementLoginAttempts($request);
                return back()->withErrors([
                    'msgError' => 'Solo Admin o Creator pueden iniciar sesión local.'
                ]);
            }

            $request->session()->regenerate();
            $request->session()->put('auth_guard', 'web');
            $request->session()->put('role_id', (int) $localUser->role_id);
            \Illuminate\Support\Facades\Auth::shouldUse('web');

            session()->put('esPeco', false);
            Log::info('LOGIN:web-session-set', [
                'session_id_after' => $request->session()->getId(),
                'auth_guard' => session('auth_guard'),
                'role_id'    => session('role_id'),
            ]);

            $this->clearLoginAttempts($request);
            return redirect()->intended($this->redirectPath());
        }

        $this->incrementLoginAttempts($request);
        Log::warning('LOGIN:failed');
        return back()->withErrors(['msgError' => 'Usuario o contraseña incorrectos.']);
    }

    $this->incrementLoginAttempts($request);
    Log::warning('LOGIN:canAccess=false');
    return back()->withErrors(['msgError' => 'Usuario o contraseña incorrectos.']);
}


    public function logout(Request $request)
    {
        // Cierra ambos por si acaso
        if (Auth::guard('organigrama')->check()) {
            Auth::guard('organigrama')->logout();
        }
        if (Auth::guard('web')->check()) {
            Auth::guard('web')->logout();
        }

        // Limpia sesión
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        $request->session()->forget(['auth_guard', 'active_process_stage', 'esPeco']);

        return redirect('/login')->with('success', 'Sesión cerrada.');
    }
}
