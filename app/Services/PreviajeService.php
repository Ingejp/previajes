<?php

namespace App\Services;

use App\Enums\EstatusPreviaje;
use App\Models\Equipo;
use App\Models\Previaje;
use App\Models\PreviajeRespuesta;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Punto único de escritura del previaje. Controlador y cualquier otro
 * consumidor pasan por aquí, de modo que el cálculo de estatus (RN-04), la
 * evidencia (RF-11) y las notificaciones (RF-14) no se puedan omitir por error.
 */
class PreviajeService
{
    public function __construct(
        private readonly FotoPreviajeService $fotos,
        private readonly NotificadorPreviaje $notificador,
    ) {}

    /**
     * @param  array<string, mixed>  $datos
     */
    public function crear(array $datos, User $autor): Previaje
    {
        $equipo = Equipo::findOrFail($datos['equipo_id']);

        $previaje = DB::transaction(function () use ($datos, $autor, $equipo) {
            $previaje = Previaje::create([
                'equipo_id' => $equipo->id,
                'mecanico_id' => $autor->id,
                // La flota sale del equipo, nunca de la petición: así no se
                // puede registrar un previaje contra una flota ajena.
                'flota_id' => $equipo->flota_id,
                'kilometraje' => $datos['kilometraje'] ?? null,
                'horometro' => $datos['horometro'] ?? null,
                'estatus' => EstatusPreviaje::SinHallazgos,
                'created_by' => $autor->id,
            ]);

            $this->guardarDetalle($previaje, $datos);

            return $previaje;
        });

        // Fuera de la transacción: encolar jobs con la fila aún sin confirmar
        // haría que el worker no encuentre el previaje.
        $this->fotos->procesarPendientes($previaje);

        // RF-14: alerta informativa, nunca bloqueante.
        $this->notificador->hallazgos($previaje);

        return $previaje->refresh();
    }

    /**
     * RF-12: la edición conserva `created_at` (la fecha original del previaje
     * es inmutable) y genera su propio `updated_at` más el rastro de auditoría.
     *
     * @param  array<string, mixed>  $datos
     */
    public function actualizar(Previaje $previaje, array $datos, User $autor): Previaje
    {
        DB::transaction(function () use ($previaje, $datos) {
            $previaje->update([
                'kilometraje' => $datos['kilometraje'] ?? null,
                'horometro' => $datos['horometro'] ?? null,
            ]);

            $this->fotos->eliminar($previaje, $datos['fotos_eliminadas'] ?? []);
            $this->guardarDetalle($previaje, $datos);
        });

        $this->fotos->procesarPendientes($previaje);

        // RN-05: toda edición avisa al supervisor y al administrador.
        $this->notificador->edicion($previaje, $autor);
        $this->notificador->hallazgos($previaje);

        return $previaje->refresh();
    }

    /**
     * RF-12: los previajes no se borran. Anular preserva el registro completo
     * y su historial de ediciones.
     */
    public function anular(Previaje $previaje, User $autor, string $motivo): Previaje
    {
        $previaje->forceFill([
            'estatus' => EstatusPreviaje::Anulado,
            'anulado_en' => now(),
            'anulado_por' => $autor->id,
            'motivo_anulacion' => $motivo,
        ])->save();

        return $previaje;
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function guardarDetalle(Previaje $previaje, array $datos): void
    {
        foreach ($datos['observaciones_seccion'] ?? [] as $seccionId => $observaciones) {
            $previaje->observacionesSeccion()->updateOrCreate(
                ['checklist_seccion_id' => (int) $seccionId],
                ['observaciones' => $observaciones],
            );
        }

        // Se recalcula el estatus una sola vez al final en vez de en cada
        // respuesta, para no repetir la misma consulta por cada ítem.
        PreviajeRespuesta::withoutEvents(function () use ($previaje, $datos) {
            foreach ($datos['respuestas'] ?? [] as $itemId => $respuesta) {
                $previaje->respuestas()->updateOrCreate(
                    ['checklist_item_id' => (int) $itemId],
                    [
                        'checklist_opcion_id' => $respuesta['checklist_opcion_id'],
                        'cantidad_agregada' => $respuesta['cantidad_agregada'] ?? null,
                        'observaciones' => $respuesta['observaciones'] ?? null,
                    ],
                );
            }
        });

        // Respuestas de ítems que ya no aplican (el catálogo cambió entre la
        // creación y la edición) dejan de tener sentido en este previaje.
        $previaje->respuestas()
            ->whereNotIn('checklist_item_id', array_keys($datos['respuestas'] ?? []))
            ->delete();

        $this->fotos->adjuntar($previaje, $datos);

        $previaje->recalcularEstatus();
    }
}
