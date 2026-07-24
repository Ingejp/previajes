<?php

namespace App\Http\Controllers\Catalogos;

use App\Http\Controllers\Controller;
use App\Models\ChecklistSeccion;
use App\Models\Configuracion;
use App\Models\Equipo;
use App\Models\Flota;
use App\Models\TipoEquipo;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/** RF-18: puerta de entrada a la gestión de catálogos. */
class CatalogoController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('administrar');

        return Inertia::render('catalogos/index', [
            'resumen' => [
                'flotas' => Flota::count(),
                'tiposEquipo' => TipoEquipo::count(),
                'equipos' => Equipo::count(),
                'usuarios' => User::count(),
                'secciones' => ChecklistSeccion::count(),
                'configuraciones' => Configuracion::count(),
            ],
        ]);
    }
}
