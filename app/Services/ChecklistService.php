<?php

namespace App\Services;

use App\Models\ChecklistSeccion;
use App\Models\Equipo;
use App\Models\Previaje;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Resuelve qué checklist aplica a un equipo (RN-07).
 *
 * Es la única fuente de verdad: la usa tanto el formulario para renderizarse
 * como el Form Request para validar. Si divergieran, el frontend podría pedir
 * campos que el backend no exige — o al revés.
 */
class ChecklistService
{
    /**
     * Secciones activas del tipo de equipo, con sus ítems y opciones activos,
     * en el orden configurado.
     *
     * @return Collection<int, ChecklistSeccion>
     */
    public function paraEquipo(Equipo $equipo): Collection
    {
        return ChecklistSeccion::query()
            ->where('activo', true)
            ->whereHas('tiposEquipo', fn (Builder $q) => $q->whereKey($equipo->tipo_equipo_id))
            ->with([
                'items' => fn ($q) => $q->where('activo', true)->orderBy('orden'),
                'opciones' => fn ($q) => $q->orderBy('orden'),
            ])
            ->orderBy('orden')
            ->get();
    }

    /**
     * IDs de los ítems que el previaje debe responder sí o sí (RF-13: no se
     * envía el formulario con un ítem activo sin responder).
     *
     * @return Collection<int, int>
     */
    public function itemsRequeridos(Equipo $equipo): Collection
    {
        return $this->paraEquipo($equipo)
            ->flatMap(fn (ChecklistSeccion $seccion) => $seccion->items->modelKeys())
            ->values();
    }

    /**
     * Últimas lecturas válidas de kilometraje y horómetro del equipo, para
     * validar que no retrocedan (RN-02).
     *
     * Al editar se excluye el propio previaje y todo lo posterior: la
     * referencia correcta es el estado del equipo justo antes de esa lectura,
     * no la más reciente de todas.
     *
     * @return array{kilometraje: int|null, horometro: float|null}
     */
    public function ultimasLecturas(Equipo $equipo, ?Previaje $excluyendo = null): array
    {
        $consulta = Previaje::query()
            ->where('equipo_id', $equipo->id)
            ->vigentes();

        if ($excluyendo?->exists) {
            $consulta->where('created_at', '<', $excluyendo->created_at)
                ->whereKeyNot($excluyendo->getKey());
        }

        return [
            'kilometraje' => $consulta->clone()->max('kilometraje'),
            'horometro' => $consulta->clone()->max('horometro'),
        ];
    }
}
