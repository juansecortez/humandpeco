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
            return back()->withErrors(['msgError' => 'Debes proporcionar usuario o correo.']);
        }

        $password  = $request->input('password');
        $username  = strtok($rawLogin, '@'); // Normaliza "usuario@dominio" -> "usuario"

        // Throttling del trait
        if (method_exists($this, 'hasTooManyLoginAttempts') && $this->hasTooManyLoginAttempts($request)) {
            $this->fireLockoutEvent($request);
            return $this->sendLockoutResponse($request);
        }

        // --- Autenticación externa (Hub) / reglas por ambiente ---
        $canAccess = false;

        if (App::environment('local')) {
            $canAccess = true;
        } elseif (App::environment('production')) {
            if ($username === 'admin') {
                $canAccess = true;
            } else {
                try {
                    $resp = Http::post('http://VADAEXTERNO:8080/Hub/api/Autenticador/validate', [
                        'username' => $username,
                        'password' => $password,
                    ]);

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
                    $this->incrementLoginAttempts($request);
                    return back()->withErrors(['msgError' => '[Hub] Error en el servicio de autenticación.']);
                }
            }
        } else {
            $canAccess = true;
        }

        if ($canAccess) {
            // 1) ¿Existe en Organigrama?
            $orgUser = OrganigramaUser::on('organigrama')
                ->where('UsuarioId', $username)
                ->first();

            if ($orgUser) {
                // Inicia sesión con el guard 'organigrama'
                Auth::guard('organigrama')->login($orgUser);

                // Orden correcto: regenerar → guardar guard en sesión → shouldUse
                $request->session()->regenerate();
                $request->session()->put('auth_guard', 'organigrama');
                Auth::shouldUse('organigrama');

                // Flags de sesión
                session()->put('esPeco', true);

                // (Opcional) Cargar SP a sesión
                try {
                    $stages = DB::connection('organigrama')->select('EXEC GetActiveProcessAndStage');
                    if (!empty($stages)) {
                        session(['active_process_stage' => $stages]);
                    }
                } catch (\Throwable $e) {
                    // silencioso
                }

                $this->clearLoginAttempts($request);
                return redirect()->intended($this->redirectPath());
            }

            // 2) Fallback a usuarios locales (guard 'web')
            // Intento por email (si el input parecía un correo) y por name (username normalizado)
            $attempted = false;

            if (strpos($rawLogin, '@') !== false) {
                $attempted = Auth::guard('web')->attempt(
                    ['email' => $rawLogin, 'password' => $password],
                    $request->boolean('remember')
                );
            }

            if (!$attempted) {
                $attempted = Auth::guard('web')->attempt(
                    ['name' => $username, 'password' => $password],
                    $request->boolean('remember')
                );
            }

            if ($attempted) {
                $request->session()->regenerate();
                $request->session()->put('auth_guard', 'web');
                Auth::shouldUse('web');

                session()->put('esPeco', false);
                $this->clearLoginAttempts($request);
                return redirect()->intended($this->redirectPath());
            }

            $this->incrementLoginAttempts($request);
            return back()->withErrors(['msgError' => 'Usuario o contraseña incorrectos.']);
        }

        $this->incrementLoginAttempts($request);
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
