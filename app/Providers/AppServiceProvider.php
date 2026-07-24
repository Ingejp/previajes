<?php

namespace App\Providers;

use App\Listeners\RegistrarAcceso;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
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
    }
}
