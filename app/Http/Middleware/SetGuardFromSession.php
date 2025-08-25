<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;

class SetGuardFromSession
{
    public function handle($request, Closure $next)
    {
        // Si guard elegido está guardado en sesión, úsalo para este request
        if ($g = $request->session()->get('auth_guard')) {
            Auth::shouldUse($g);
        }

        return $next($request);
    }
}
