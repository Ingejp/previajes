<?php

namespace App\Http\Controllers;

use App\Enums\EstatusPreviaje;
use App\Models\Equipo;
use App\Models\Flota;
use App\Models\TipoEquipo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * RF-16: vista general de equipos con su última lectura, los días
 * transcurridos desde el último previaje y el indicador de atraso.
 *
 * RF-16.1 / RN-12: el atraso se mide contra el umbral del TIPO de equipo, no
 * contra un número global — un cabezal y un genset no se inspeccionan con la
 * misma frecuencia.
 */
class EquipoController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Equipo::class);

        $usuario = $request->user();

        $equipos = Equipo::query()
            ->deFlotasVisibles($usuario)
            ->when($request->integer('flota_id'), fn ($q, $id) => $q->where('flota_id', $id))
            ->when($request->integer('tipo_equipo_id'), fn ($q, $id) => $q->where('tipo_equipo_id', $id))
            ->with(['tipoEquipo:id,nombre,dias_alerta_sin_previaje', 'flota:id,nombre', 'ultimoPreviaje'])
            ->orderBy('codigo')
            ->get()
            ->map(fn (Equipo $equipo) => $this->resumir($equipo));

        // El umbral depende del tipo de equipo, así que el atraso se resuelve
        // sobre el resultado ya calculado en vez de intentarlo en SQL.
        if ($request->boolean('solo_atrasados')) {
            $equipos = $equipos->where('atrasado', true);
        }

        return Inertia::render('equipos/index', [
            'equipos' => $equipos->values(),
            'filtros' => $request->only(['flota_id', 'tipo_equipo_id', 'solo_atrasados']),
            'opciones' => [
                'flotas' => Flota::whereIn('id', $usuario->flotasAccesibles())->orderBy('nombre')->get(['id', 'nombre']),
                'tiposEquipo' => TipoEquipo::orderBy('nombre')->get(['id', 'nombre']),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function resumir(Equipo $equipo): array
    {
        $ultimo = $equipo->ultimoPreviaje;
        $umbral = $equipo->tipoEquipo->dias_alerta_sin_previaje;
        $dias = $ultimo === null
            ? null
            : (int) $ultimo->created_at->startOfDay()->diffInDays(now()->startOfDay());

        return [
            'id' => $equipo->id,
            'codigo' => $equipo->codigo,
            'tipo' => $equipo->tipoEquipo->nombre,
            'flota' => $equipo->flota->nombre,
            'marca' => $equipo->marca,
            'modelo' => $equipo->modelo,
            'activo' => $equipo->activo,
            // RF-16: kilometraje y horómetro en columnas separadas.
            'ultimo_kilometraje' => $ultimo?->kilometraje,
            'ultimo_horometro' => $ultimo?->horometro,
            'ultimo_previaje_id' => $ultimo?->id,
            'ultimo_previaje_en' => $ultimo?->created_at->toIso8601String(),
            'dias_sin_previaje' => $dias,
            'umbral_dias' => $umbral,
            // Un equipo que nunca se ha inspeccionado cuenta como atrasado.
            'atrasado' => $dias === null || $dias > $umbral,
            'tiene_hallazgos' => $ultimo?->estatus === EstatusPreviaje::ConHallazgos,
        ];
    }
}
