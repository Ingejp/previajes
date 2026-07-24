<?php

namespace App\Http\Controllers;

use App\Models\Equipo;
use App\Models\RegistroLlanta;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * RF-17.1: registro INTERINO de cambio de llantas.
 *
 * Va aparte del previaje a propósito: el checklist registra el hallazgo
 * detectado, esto registra la acción de mantenimiento realizada. Lo alimentan
 * mecánico y supervisor (y por encima, administrador y super administrador).
 */
class RegistroLlantaController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', RegistroLlanta::class);

        $usuario = $request->user();

        $registros = RegistroLlanta::query()
            ->whereHas('equipo', fn ($q) => $q->deFlotasVisibles($usuario))
            ->when($request->integer('equipo_id'), fn ($q, $id) => $q->where('equipo_id', $id))
            ->with(['equipo:id,codigo,flota_id', 'equipo.flota:id,nombre', 'usuario:id,name'])
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('llantas/index', [
            'registros' => $registros,
            'filtros' => $request->only('equipo_id'),
            'equipos' => Equipo::query()
                ->deFlotasVisibles($usuario)
                ->where('activo', true)
                ->orderBy('codigo')
                ->get(['id', 'codigo']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', RegistroLlanta::class);

        $datos = $request->validate([
            'equipo_id' => ['required', 'integer', Rule::exists('equipos', 'id')->where('activo', true)],
            'cantidad' => ['required', 'integer', 'min:1', 'max:100'],
            'posicion' => ['nullable', 'string', 'max:255'],
            'observaciones' => ['nullable', 'string', 'max:2000'],
        ]);

        $equipo = Equipo::findOrFail($datos['equipo_id']);
        abort_unless($request->user()->puedeVerFlota($equipo->flota_id), 403);

        RegistroLlanta::create([
            ...$datos,
            'usuario_id' => $request->user()->id,
            // La fecha la pone el sistema, igual que en el previaje (RF-17.1).
        ]);

        return back()->with('exito', 'Cambio de llantas registrado.');
    }
}
