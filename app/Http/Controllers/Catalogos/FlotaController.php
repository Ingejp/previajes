<?php

namespace App\Http\Controllers\Catalogos;

use App\Http\Controllers\Controller;
use App\Http\Requests\FlotaRequest;
use App\Models\Flota;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/** RF-18: CRUD de flotas. */
class FlotaController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('administrar');

        return Inertia::render('catalogos/flotas/index', [
            'flotas' => Flota::withCount(['equipos', 'usuarios'])->orderBy('nombre')->get(),
        ]);
    }

    public function store(FlotaRequest $request): RedirectResponse
    {
        Flota::create($request->validated());

        return back()->with('exito', 'Flota creada.');
    }

    public function update(FlotaRequest $request, Flota $flota): RedirectResponse
    {
        $flota->update($request->validated());

        return back()->with('exito', 'Flota actualizada.');
    }

    /**
     * RF-02: una flota en uso no se elimina — perdería la trazabilidad de sus
     * equipos y usuarios. Se desactiva desde `update`; aquí sólo se permite
     * borrar la que nunca llegó a usarse.
     */
    public function destroy(Request $request, Flota $flota): RedirectResponse
    {
        Gate::authorize('administrar');

        if ($flota->equipos()->exists() || $flota->usuarios()->exists() || $flota->usuariosAsignados()->exists()) {
            return back()->with('error', 'No se puede eliminar: la flota tiene equipos o usuarios asociados. Desactívela en su lugar.');
        }

        try {
            $flota->delete();
        } catch (QueryException) {
            return back()->with('error', 'No se puede eliminar: la flota está referenciada por otros registros.');
        }

        return back()->with('exito', 'Flota eliminada.');
    }
}
