<?php

namespace App\Notifications;

use App\Models\Previaje;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * RF-14: alerta informativa cuando un previaje queda con hallazgos.
 *
 * Es explícitamente NO bloqueante: el equipo puede salir igual. Esto sólo
 * avisa y deja constancia (RN-04, decisión de negocio confirmada).
 */
class PreviajeConHallazgos extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Previaje $previaje) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $previaje = $this->previaje->loadMissing(['equipo', 'mecanico', 'flota']);
        $hallazgos = $this->hallazgos();

        $mensaje = (new MailMessage)
            ->subject("Previaje con hallazgos — equipo {$previaje->equipo->codigo}")
            ->greeting('Previaje con hallazgos')
            ->line("Equipo: {$previaje->equipo->codigo} ({$previaje->flota->nombre})")
            ->line("Mecánico: {$previaje->mecanico->name}")
            ->line('Fecha: '.$previaje->created_at->format('d/m/Y H:i'));

        foreach ($hallazgos as $hallazgo) {
            $mensaje->line("• {$hallazgo}");
        }

        return $mensaje
            ->action('Ver previaje', route('previajes.show', $previaje))
            ->line('Este aviso es informativo: el previaje no bloquea la salida del equipo.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'tipo' => 'previaje_con_hallazgos',
            'previaje_id' => $this->previaje->id,
            'equipo' => $this->previaje->equipo->codigo,
            'flota_id' => $this->previaje->flota_id,
            'hallazgos' => $this->hallazgos(),
        ];
    }

    /** @return array<int, string> */
    private function hallazgos(): array
    {
        return $this->previaje
            ->loadMissing(['respuestas.item', 'respuestas.opcion'])
            ->respuestas
            ->filter->esHallazgo()
            ->map(fn ($r) => "{$r->item->descripcion}: {$r->opcion->etiqueta}")
            ->values()
            ->all();
    }
}
