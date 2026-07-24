<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * §7, "configuración de seguridad incorrecta": cabeceras que endurecen el
 * navegador contra clickjacking, sniffing de tipo y fuga de referrer.
 *
 * HSTS sólo se emite sobre HTTPS: mandarlo por HTTP no tiene efecto y en
 * desarrollo dejaría el dominio local clavado en HTTPS durante meses.
 */
class CabecerasDeSeguridad
{
    public function handle(Request $request, Closure $next): Response
    {
        $respuesta = $next($request);

        $respuesta->headers->set('X-Content-Type-Options', 'nosniff');
        $respuesta->headers->set('X-Frame-Options', 'DENY');
        $respuesta->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        // La app no usa cámara por API ni micrófono ni geolocalización; la foto
        // se toma con el selector de archivos del sistema (`capture`).
        $respuesta->headers->set('Permissions-Policy', 'geolocation=(), microphone=(), payment=()');

        if ($request->secure()) {
            $respuesta->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $respuesta;
    }
}
