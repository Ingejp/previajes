<?php

namespace App\Services;

use App\Enums\RolUsuario;
use App\Models\Previaje;
use App\Models\User;
use App\Notifications\PreviajeConHallazgos;
use App\Notifications\PreviajeEditado;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * RF-12, RF-14 y RN-05: tanto la alerta de hallazgos como la de edición van a
 * los mismos destinatarios — el supervisor de esa flota y el administrador
 * general, ambos, confirmado con negocio.
 */
class NotificadorPreviaje
{
    public function hallazgos(Previaje $previaje): void
    {
        if (! $previaje->tieneHallazgos()) {
            return;
        }

        Notification::send($this->destinatarios($previaje), new PreviajeConHallazgos($previaje));
    }

    public function edicion(Previaje $previaje, User $autor): void
    {
        // A quien acaba de editar no hace falta avisarle de su propia edición.
        $destinatarios = $this->destinatarios($previaje)
            ->reject(fn (User $usuario) => $usuario->id === $autor->id);

        Notification::send($destinatarios, new PreviajeEditado($previaje, $autor));
    }

    /**
     * Supervisores de la flota del previaje —por flota principal o por
     * asignación múltiple— más todos los administradores.
     *
     * @return Collection<int, User>
     */
    private function destinatarios(Previaje $previaje): Collection
    {
        return User::query()
            ->where('activo', true)
            ->where(function ($consulta) use ($previaje) {
                $consulta
                    ->where(fn ($q) => $q
                        ->where('rol', RolUsuario::Supervisor->value)
                        ->where(fn ($f) => $f
                            ->where('flota_id', $previaje->flota_id)
                            ->orWhereHas('flotas', fn ($p) => $p->whereKey($previaje->flota_id))))
                    ->orWhere('rol', RolUsuario::Administrador->value);
            })
            ->get();
    }
}
