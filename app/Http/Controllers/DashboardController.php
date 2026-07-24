<?php

namespace App\Http\Controllers;

use App\Enums\EstatusPreviaje;
use App\Models\Equipo;
use App\Models\Flota;
use App\Models\Previaje;
use App\Models\PreviajeRespuesta;
use App\Models\RegistroLlanta;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * RF-17: panel de estatus de flota y consumos.
 *
 * Todas las consultas se acotan a las flotas visibles del usuario; el
 * supervisor ve su operación y el administrador la de todas (RF-02, RN-09).
 */
class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('ver-dashboard');

        $usuario = $request->user();
        $flotas = $usuario->flotasAccesibles();

        $desde = $request->date('desde') ?? now()->startOfMonth();
        $hasta = $request->date('hasta') ?? now()->endOfDay();

        return Inertia::render('dashboard', [
            'periodo' => ['desde' => $desde->toDateString(), 'hasta' => $hasta->toDateString()],
            'inspeccionHoy' => $this->inspeccionDeHoy($flotas),
            'hallazgosAbiertos' => $this->hallazgosAbiertos($flotas),
            'sinPreviajeReciente' => $this->sinPreviajeReciente($flotas),
            'consumoFluidos' => $this->consumoDeFluidos($flotas, $desde, $hasta),
            'consumoLlantas' => $this->consumoDeLlantas($flotas, $desde, $hasta),
        ]);
    }

    /**
     * Equipos inspeccionados hoy frente a los pendientes, por flota.
     *
     * @param  Collection<int, int>  $flotas
     * @return array<int, array<string, mixed>>
     */
    private function inspeccionDeHoy($flotas): array
    {
        $inspeccionados = Previaje::query()
            ->whereIn('flota_id', $flotas)
            ->vigentes()
            ->whereDate('created_at', today())
            ->distinct()
            ->pluck('equipo_id', 'equipo_id');

        return Flota::whereIn('id', $flotas)
            ->withCount(['equipos' => fn ($q) => $q->where('activo', true)])
            ->orderBy('nombre')
            ->get()
            ->map(function (Flota $flota) use ($inspeccionados) {
                $hechos = Equipo::where('flota_id', $flota->id)
                    ->where('activo', true)
                    ->whereIn('id', $inspeccionados)
                    ->count();

                return [
                    'flota_id' => $flota->id,
                    'flota' => $flota->nombre,
                    'total' => $flota->equipos_count,
                    'inspeccionados' => $hechos,
                    'pendientes' => max(0, $flota->equipos_count - $hechos),
                ];
            })
            ->all();
    }

    /**
     * Equipos cuyo último previaje quedó con hallazgos (RN-04).
     *
     * @param  Collection<int, int>  $flotas
     * @return array<int, array<string, mixed>>
     */
    private function hallazgosAbiertos($flotas): array
    {
        return Equipo::query()
            ->whereIn('flota_id', $flotas)
            ->where('activo', true)
            ->with(['tipoEquipo:id,nombre', 'flota:id,nombre', 'ultimoPreviaje'])
            ->get()
            ->filter(fn (Equipo $e) => $e->ultimoPreviaje?->estatus === EstatusPreviaje::ConHallazgos)
            ->map(fn (Equipo $e) => [
                'equipo_id' => $e->id,
                'codigo' => $e->codigo,
                'tipo' => $e->tipoEquipo->nombre,
                'flota' => $e->flota->nombre,
                'previaje_id' => $e->ultimoPreviaje->id,
                'fecha' => $e->ultimoPreviaje->created_at->toIso8601String(),
            ])
            ->values()
            ->all();
    }

    /**
     * RF-16.1 / RN-12: el atraso se mide contra el umbral del tipo de equipo.
     *
     * @param  Collection<int, int>  $flotas
     * @return array<int, array<string, mixed>>
     */
    private function sinPreviajeReciente($flotas): array
    {
        return Equipo::query()
            ->whereIn('flota_id', $flotas)
            ->where('activo', true)
            ->with(['tipoEquipo:id,nombre,dias_alerta_sin_previaje', 'flota:id,nombre', 'ultimoPreviaje'])
            ->get()
            ->map(function (Equipo $equipo) {
                $ultimo = $equipo->ultimoPreviaje;

                return [
                    'equipo_id' => $equipo->id,
                    'codigo' => $equipo->codigo,
                    'tipo' => $equipo->tipoEquipo->nombre,
                    'flota' => $equipo->flota->nombre,
                    'umbral_dias' => $equipo->tipoEquipo->dias_alerta_sin_previaje,
                    'dias_sin_previaje' => $ultimo === null
                        ? null
                        : (int) $ultimo->created_at->startOfDay()->diffInDays(now()->startOfDay()),
                    'ultimo_previaje_en' => $ultimo?->created_at->toIso8601String(),
                ];
            })
            // Nunca inspeccionado cuenta como atrasado, y va primero.
            ->filter(fn (array $e) => $e['dias_sin_previaje'] === null || $e['dias_sin_previaje'] > $e['umbral_dias'])
            ->sortByDesc(fn (array $e) => $e['dias_sin_previaje'] ?? PHP_INT_MAX)
            ->take(20)
            ->values()
            ->all();
    }

    /**
     * RF-17: galones agregados por período, desglosados por tipo de fluido y
     * por equipo. Es el insumo para detectar fugas o consumo anormal.
     *
     * @param  Collection<int, int>  $flotas
     * @return array<string, mixed>
     */
    private function consumoDeFluidos($flotas, Carbon $desde, Carbon $hasta): array
    {
        $respuestas = PreviajeRespuesta::query()
            ->whereNotNull('cantidad_agregada')
            ->where('cantidad_agregada', '>', 0)
            ->whereHas('previaje', fn ($q) => $q
                ->whereIn('flota_id', $flotas)
                ->vigentes()
                ->whereBetween('created_at', [$desde->copy()->startOfDay(), $hasta->copy()->endOfDay()]))
            ->with(['item:id,descripcion', 'previaje:id,equipo_id,flota_id', 'previaje.equipo:id,codigo', 'previaje.flota:id,nombre'])
            ->get();

        return [
            'total' => round((float) $respuestas->sum('cantidad_agregada'), 2),
            'porFluido' => $respuestas
                ->groupBy(fn ($r) => $r->item->descripcion)
                ->map(fn ($grupo, $fluido) => [
                    'fluido' => $fluido,
                    'galones' => round((float) $grupo->sum('cantidad_agregada'), 2),
                    'eventos' => $grupo->count(),
                ])
                ->sortByDesc('galones')
                ->values()
                ->all(),
            // Se agrupa por el id del equipo, no por su código: el código no
            // identifica el registro al que hay que llevar al usuario si dos
            // equipos de flotas distintas compartieran numeración.
            'porEquipo' => $respuestas
                ->groupBy(fn ($r) => $r->previaje->equipo_id)
                ->map(fn ($grupo) => [
                    'equipo_id' => $grupo->first()->previaje->equipo_id,
                    'equipo' => $grupo->first()->previaje->equipo->codigo,
                    'flota' => $grupo->first()->previaje->flota->nombre,
                    'galones' => round((float) $grupo->sum('cantidad_agregada'), 2),
                ])
                ->sortByDesc('galones')
                ->take(10)
                ->values()
                ->all(),
            'porFlota' => $respuestas
                ->groupBy(fn ($r) => $r->previaje->flota_id)
                ->map(fn ($grupo) => [
                    'flota_id' => $grupo->first()->previaje->flota_id,
                    'flota' => $grupo->first()->previaje->flota->nombre,
                    'galones' => round((float) $grupo->sum('cantidad_agregada'), 2),
                ])
                ->sortByDesc('galones')
                ->values()
                ->all(),
        ];
    }

    /**
     * RF-17.1: conteo de llantas cambiadas, desde el registro interino.
     *
     * @param  Collection<int, int>  $flotas
     * @return array<string, mixed>
     */
    private function consumoDeLlantas($flotas, Carbon $desde, Carbon $hasta): array
    {
        $registros = RegistroLlanta::query()
            ->whereHas('equipo', fn ($q) => $q->whereIn('flota_id', $flotas))
            ->whereBetween('created_at', [$desde->copy()->startOfDay(), $hasta->copy()->endOfDay()])
            ->with(['equipo:id,codigo,flota_id', 'equipo.flota:id,nombre'])
            ->get();

        return [
            'total' => (int) $registros->sum('cantidad'),
            'porEquipo' => $registros
                ->groupBy('equipo_id')
                ->map(fn ($grupo) => [
                    'equipo_id' => $grupo->first()->equipo_id,
                    'equipo' => $grupo->first()->equipo->codigo,
                    'flota' => $grupo->first()->equipo->flota->nombre,
                    'llantas' => (int) $grupo->sum('cantidad'),
                ])
                ->sortByDesc('llantas')
                ->take(10)
                ->values()
                ->all(),
        ];
    }
}
