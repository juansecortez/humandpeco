<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Closure;
use Illuminate\Support\Facades\Auth;

class Authenticate extends Middleware
{
    public function handle($request, Closure $next, ...$guards)
    {
        $guards = $guards ?: [null];

        foreach ($guards as $guard) {
            if (Auth::guard($guard)->check()) {
                // Asegura que el resto del ciclo use el guard que sí está logueado
                if ($guard) {
                    Auth::shouldUse($guard);
                    $request->session()->put('auth_guard', $guard);
                }
                return $next($request);
            }
        }

        return $this->unauthenticated($request, $guards);
    }

    protected function redirectTo($request)
    {
        if (! $request->expectsJson()) {
            return route('login');
        }
    }
}
