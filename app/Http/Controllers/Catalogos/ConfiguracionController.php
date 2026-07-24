<?php

namespace App\Http\Controllers\Catalogos;

use App\Http\Controllers\Controller;
use App\Http\Requests\ConfiguracionRequest;
use App\Models\Configuracion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * RF-16.1 / RF-18: parámetros globales (ej. tamaño máximo de foto). El umbral
 * de días sin previaje NO vive aquí — es un campo de `tipos_equipo` (RN-12).
 */
class ConfiguracionController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('administrar');

        return Inertia::render('catalogos/configuraciones/index', [
            'configuraciones' => Configuracion::orderBy('clave')->get(),
        ]);
    }

    public function store(ConfiguracionRequest $request): RedirectResponse
    {
        Configuracion::create($request->validated());

        return back()->with('exito', 'Configuración creada.');
    }

    public function update(ConfiguracionRequest $request, Configuracion $configuracion): RedirectResponse
    {
        // La clave no se acepta en la edición: ver ConfiguracionRequest.
        $configuracion->update($request->safe()->except('clave'));

        return back()->with('exito', 'Configuración actualizada.');
    }

    public function destroy(Configuracion $configuracion): RedirectResponse
    {
        Gate::authorize('administrar');

        $configuracion->delete();

        return back()->with('exito', 'Configuración eliminada. Se usará el valor por defecto del código.');
    }
}
