<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

class RedirectIfAuthenticated
{
    public function handle($request, Closure $next, ...$guards)
    {
        $guards = $guards ?: ['web','organigrama'];

        foreach ($guards as $guard) {
            if (Auth::guard($guard)->check()) {
                // Manda SIEMPRE a 'home' (o 'inicio'), NUNCA a '/'
                if (Route::has('home')) {
                    return redirect()->route('home');
                }
                if (Route::has('inicio')) {
                    return redirect()->route('inicio');
                }
                return redirect('/home');
            }
        }

        return $next($request);
    }
}
