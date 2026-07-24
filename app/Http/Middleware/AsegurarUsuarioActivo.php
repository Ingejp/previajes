<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Un usuario dado de baja (RF-18) pierde el acceso de inmediato, incluso si ya
 * tenía la sesión abierta. Sin esto, desactivarlo sólo evitaría nuevos logins.
 */
class AsegurarUsuarioActivo
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check() && ! Auth::user()->activo) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => 'Su cuenta fue desactivada. Contacte al administrador.',
            ]);
        }

        return $next($request);
    }
}
