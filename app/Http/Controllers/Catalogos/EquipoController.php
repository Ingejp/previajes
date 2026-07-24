<?php

namespace App\Http\Controllers\Catalogos;

use App\Http\Controllers\Controller;
use App\Http\Requests\EquipoRequest;
use App\Models\Equipo;
use App\Models\Flota;
use App\Models\TipoEquipo;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * RF-18: CRUD de equipos.
 *
 * Distinto de `App\Http\Controllers\EquipoController@index`, que es la vista
 * operativa de estatus (RF-16) abierta a cualquier rol; ésta es de alta/edición
 * y queda restringida al administrador.
 */
class EquipoController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('administrar');

        return Inertia::render('catalogos/equipos/index', [
            'equipos' => Equipo::with(['tipoEquipo:id,nombre', 'flota:id,nombre'])
                ->withCount('previajes')
                ->orderBy('codigo')
                ->get(),
            'opciones' => [
                'tiposEquipo' => TipoEquipo::where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
                'flotas' => Flota::where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
            ],
        ]);
    }

    public function store(EquipoRequest $request): RedirectResponse
    {
        Equipo::create($request->validated());

        return back()->with('exito', 'Equipo creado.');
    }

    public function update(EquipoRequest $request, Equipo $equipo): RedirectResponse
    {
        $equipo->update($request->validated());

        return back()->with('exito', 'Equipo actualizado.');
    }

    /** RN-03: un equipo con historial de previajes no se elimina, se desactiva. */
    public function destroy(Equipo $equipo): RedirectResponse
    {
        Gate::authorize('administrar');

        if ($equipo->previajes()->exists()) {
            return back()->with('error', 'No se puede eliminar: el equipo tiene previajes registrados. Desactívelo en su lugar.');
        }

        try {
            $equipo->delete();
        } catch (QueryException) {
            return back()->with('error', 'No se puede eliminar: el equipo está referenciado por otros registros.');
        }

        return back()->with('exito', 'Equipo eliminado.');
    }
}
