<?php

namespace App\Http\Controllers\Catalogos;

use App\Http\Controllers\Controller;
use App\Http\Requests\ChecklistOpcionRequest;
use App\Models\ChecklistOpcion;
use App\Models\ChecklistSeccion;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

/**
 * RF-07: opciones de respuesta, administradas dentro de la página de su
 * sección (catalogos/secciones/index) — no tienen pantalla propia.
 */
class ChecklistOpcionController extends Controller
{
    public function store(ChecklistOpcionRequest $request, ChecklistSeccion $seccion): RedirectResponse
    {
        $seccion->opciones()->create($request->validated());

        return back()->with('exito', 'Opción creada.');
    }

    public function update(ChecklistOpcionRequest $request, ChecklistSeccion $seccion, ChecklistOpcion $opcion): RedirectResponse
    {
        abort_unless($opcion->seccion_id === $seccion->id, 404);

        $opcion->update($request->validated());

        return back()->with('exito', 'Opción actualizada.');
    }

    /** No tiene `activo`: una opción ya usada en un previaje simplemente no se puede borrar. */
    public function destroy(ChecklistSeccion $seccion, ChecklistOpcion $opcion): RedirectResponse
    {
        Gate::authorize('administrar');
        abort_unless($opcion->seccion_id === $seccion->id, 404);

        try {
            $opcion->delete();
        } catch (QueryException) {
            return back()->with('error', 'No se puede eliminar: la opción está usada en respuestas de previajes.');
        }

        return back()->with('exito', 'Opción eliminada.');
    }
}
