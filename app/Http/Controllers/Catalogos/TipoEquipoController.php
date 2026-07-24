<?php

namespace App\Http\Controllers\Catalogos;

use App\Http\Controllers\Controller;
use App\Http\Requests\TipoEquipoRequest;
use App\Models\ChecklistSeccion;
use App\Models\TipoEquipo;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/** RF-18: CRUD de tipos de equipo, incluida su asociación con secciones (RN-07). */
class TipoEquipoController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('administrar');

        return Inertia::render('catalogos/tipos-equipo/index', [
            'tiposEquipo' => TipoEquipo::withCount('equipos')
                ->with('secciones:id,nombre')
                ->orderBy('nombre')
                ->get(),
            'secciones' => ChecklistSeccion::orderBy('orden')->get(['id', 'nombre']),
        ]);
    }

    public function store(TipoEquipoRequest $request): RedirectResponse
    {
        $datos = $request->validated();
        $tipo = TipoEquipo::create(collect($datos)->except('secciones')->all());
        $tipo->secciones()->sync($datos['secciones'] ?? []);

        return back()->with('exito', 'Tipo de equipo creado.');
    }

    public function update(TipoEquipoRequest $request, TipoEquipo $tipoEquipo): RedirectResponse
    {
        $datos = $request->validated();
        $tipoEquipo->update(collect($datos)->except('secciones')->all());
        $tipoEquipo->secciones()->sync($datos['secciones'] ?? []);

        return back()->with('exito', 'Tipo de equipo actualizado.');
    }

    /** RF-03: un tipo con equipos ya dados de alta no se elimina, se desactiva. */
    public function destroy(TipoEquipo $tipoEquipo): RedirectResponse
    {
        Gate::authorize('administrar');

        if ($tipoEquipo->equipos()->exists()) {
            return back()->with('error', 'No se puede eliminar: hay equipos con este tipo. Desactívelo en su lugar.');
        }

        try {
            $tipoEquipo->delete();
        } catch (QueryException) {
            return back()->with('error', 'No se puede eliminar: el tipo de equipo está referenciado por otros registros.');
        }

        return back()->with('exito', 'Tipo de equipo eliminado.');
    }
}
