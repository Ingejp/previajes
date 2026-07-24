<?php

namespace App\Providers;

use App\Listeners\RegistrarAcceso;
use Illuminate\Auth\Middleware\RedirectIfAuthenticated;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // RF-20 / OWASP: toda entrada y salida del sistema queda registrada.
        Event::subscribe(RegistrarAcceso::class);

        // Falla ruidosamente en desarrollo si una vista usa una relación que
        // no se cargó, en vez de disparar consultas N+1 en silencio.
        Model::preventLazyLoading(! app()->isProduction());

        // §7, fallas criptográficas: HTTPS obligatorio fuera de desarrollo.
        if (app()->isProduction()) {
            URL::forceScheme('https');
        }

        // El middleware 'guest' redirige a quien ya inició sesión y visita
        // /login; por defecto Laravel manda sin condición a la ruta
        // "dashboard" si existe, y un mecánico no tiene permiso para verla
        // (le tocaría un 403). Se repite aquí la misma regla que en el "/"
        // y en el login (RF-17).
        RedirectIfAuthenticated::redirectUsing(
            fn (Request $request) => route(Gate::allows('ver-dashboard') ? 'dashboard' : 'previajes.index'),
        );
    }
}
