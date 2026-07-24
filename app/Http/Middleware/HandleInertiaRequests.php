<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Props compartidos por todas las pantallas.
     *
     * Los permisos se comparten para decidir qué se muestra en el menú, pero
     * cada acción se vuelve a autorizar en el backend: ocultar un enlace no es
     * un control de acceso (§7).
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $usuario = $request->user();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $usuario ? [
                    'id' => $usuario->id,
                    'name' => $usuario->name,
                    'email' => $usuario->email,
                    'rol' => $usuario->rol->value,
                    'rol_etiqueta' => $usuario->rol->etiqueta(),
                    'flota' => $usuario->flota?->nombre,
                ] : null,
                'permisos' => $usuario ? [
                    'ver_auditoria' => Gate::allows('ver-auditoria'),
                    'ver_dashboard' => Gate::allows('ver-dashboard'),
                    'administrar' => Gate::allows('administrar'),
                ] : [],
            ],
            'flash' => [
                'exito' => fn () => $request->session()->get('exito'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }
}
