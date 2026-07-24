<?php

namespace App\Http\Controllers\Catalogos;

use App\Http\Controllers\Controller;
use App\Http\Requests\ChecklistSeccionRequest;
use App\Models\ChecklistSeccion;
use App\Models\TipoEquipo;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * RF-18: CRUD de secciones del checklist, con sus ítems y opciones anidados
 * (RF-05, RF-06, RF-07) y su asociación a tipos de equipo (RN-07).
 */
class ChecklistSeccionController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('administrar');

        return Inertia::render('catalogos/secciones/index', [
            'secciones' => ChecklistSeccion::with([
                'items' => fn ($q) => $q->orderBy('orden'),
                'opciones' => fn ($q) => $q->orderBy('orden'),
                'tiposEquipo:id,nombre',
            ])->orderBy('orden')->get(),
            'tiposEquipo' => TipoEquipo::orderBy('nombre')->get(['id', 'nombre']),
        ]);
    }

    public function store(ChecklistSeccionRequest $request): RedirectResponse
    {
        $datos = $request->validated();
        $seccion = ChecklistSeccion::create(collect($datos)->except('tipos_equipo')->all());
        $seccion->tiposEquipo()->sync($datos['tipos_equipo'] ?? []);

        return back()->with('exito', 'Sección creada.');
    }

    public function update(ChecklistSeccionRequest $request, ChecklistSeccion $seccion): RedirectResponse
    {
        $datos = $request->validated();
        $seccion->update(collect($datos)->except('tipos_equipo')->all());
        $seccion->tiposEquipo()->sync($datos['tipos_equipo'] ?? []);

        return back()->with('exito', 'Sección actualizada.');
    }

    /**
     * Borra la sección junto con sus ítems y opciones (cascada en BD). Si
     * alguno ya fue usado en un previaje, la base de datos rechaza el borrado
     * (RF-13/RF-15 dependen de ese historial) y aquí sólo se traduce el error.
     */
    public function destroy(ChecklistSeccion $seccion): RedirectResponse
    {
        Gate::authorize('administrar');

        try {
            $seccion->delete();
        } catch (QueryException) {
            return back()->with('error', 'No se puede eliminar: la sección tiene ítems u opciones usados en previajes. Desactívela en su lugar.');
        }

        return back()->with('exito', 'Sección eliminada.');
    }
}
