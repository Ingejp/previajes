<?php

namespace App\Http\Controllers\Catalogos;

use App\Http\Controllers\Controller;
use App\Http\Requests\ChecklistItemRequest;
use App\Models\ChecklistItem;
use App\Models\ChecklistSeccion;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

/**
 * RF-06: ítems de checklist, administrados dentro de la página de su sección
 * (catalogos/secciones/index) — no tienen pantalla propia.
 */
class ChecklistItemController extends Controller
{
    public function store(ChecklistItemRequest $request, ChecklistSeccion $seccion): RedirectResponse
    {
        $seccion->items()->create($request->validated());

        return back()->with('exito', 'Ítem creado.');
    }

    public function update(ChecklistItemRequest $request, ChecklistSeccion $seccion, ChecklistItem $item): RedirectResponse
    {
        abort_unless($item->seccion_id === $seccion->id, 404);

        $item->update($request->validated());

        return back()->with('exito', 'Ítem actualizado.');
    }

    /** RF-13: un ítem ya respondido en un previaje no se borra, se desactiva. */
    public function destroy(ChecklistSeccion $seccion, ChecklistItem $item): RedirectResponse
    {
        Gate::authorize('administrar');
        abort_unless($item->seccion_id === $seccion->id, 404);

        try {
            $item->delete();
        } catch (QueryException) {
            return back()->with('error', 'No se puede eliminar: el ítem tiene respuestas registradas en previajes. Desactívelo en su lugar.');
        }

        return back()->with('exito', 'Ítem eliminado.');
    }
}
