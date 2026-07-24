<?php

namespace App\Providers;

use App\Enums\RolUsuario;
use App\Models\ChecklistItem;
use App\Models\ChecklistOpcion;
use App\Models\ChecklistSeccion;
use App\Models\Configuracion;
use App\Models\Flota;
use App\Models\TipoEquipo;
use App\Models\User;
use App\Policies\CatalogoPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * Los catálogos comparten una sola política (RF-18); las demás se
     * descubren solas por convención de nombre.
     *
     * @var array<class-string, class-string>
     */
    private const POLITICAS_CATALOGO = [
        Flota::class => CatalogoPolicy::class,
        TipoEquipo::class => CatalogoPolicy::class,
        ChecklistSeccion::class => CatalogoPolicy::class,
        ChecklistItem::class => CatalogoPolicy::class,
        ChecklistOpcion::class => CatalogoPolicy::class,
        Configuracion::class => CatalogoPolicy::class,
    ];

    public function boot(): void
    {
        foreach (self::POLITICAS_CATALOGO as $modelo => $politica) {
            Gate::policy($modelo, $politica);
        }

        // RF-20: el mecánico no entra a la pantalla de auditoría. El alcance de
        // lo que cada rol ve dentro se filtra en la consulta, no aquí.
        Gate::define('ver-auditoria', fn (User $usuario) => $usuario->activo
            && $usuario->alMenos(RolUsuario::Supervisor));

        // RF-17: el dashboard de flota y consumos es para supervisión, no para
        // el mecánico que sólo llena previajes.
        Gate::define('ver-dashboard', fn (User $usuario) => $usuario->activo
            && $usuario->alMenos(RolUsuario::Supervisor));

        // RF-18: gestión de catálogos (flotas, tipos de equipo, equipos,
        // checklist, configuraciones) — abierta desde supervisor. La gestión
        // de usuarios es la excepción: vive aparte en `UserPolicy`, reservada
        // a administrador, porque ahí se asignan roles y se crean cuentas.
        Gate::define('administrar', fn (User $usuario) => $usuario->activo
            && $usuario->alMenos(RolUsuario::Supervisor));
    }
}
