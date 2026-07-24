<?php

namespace App\Notifications;

use App\Models\Previaje;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * RF-12 / RN-05: un previaje ya enviado puede editarse, pero cada edición
 * avisa al supervisor de la flota y al administrador general, y queda en la
 * bitácora de auditoría.
 */
class PreviajeEditado extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Previaje $previaje,
        public readonly User $autor,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $previaje = $this->previaje->loadMissing(['equipo', 'flota']);

        return (new MailMessage)
            ->subject("Previaje modificado — equipo {$previaje->equipo->codigo}")
            ->greeting('Se modificó un previaje ya enviado')
            ->line("Equipo: {$previaje->equipo->codigo} ({$previaje->flota->nombre})")
            ->line("Modificado por: {$this->autor->name}")
            ->line('Fecha original del previaje: '.$previaje->created_at->format('d/m/Y H:i'))
            ->line('Fecha de la modificación: '.$previaje->updated_at->format('d/m/Y H:i'))
            ->action('Ver previaje', route('previajes.show', $previaje))
            ->line('El detalle de qué cambió está en la pantalla de auditoría.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'tipo' => 'previaje_editado',
            'previaje_id' => $this->previaje->id,
            'equipo' => $this->previaje->equipo->codigo,
            'flota_id' => $this->previaje->flota_id,
            'editado_por' => $this->autor->name,
        ];
    }
}
