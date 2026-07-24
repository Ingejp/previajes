<?php

namespace App\Policies;

use App\Enums\RolUsuario;
use App\Models\RegistroLlanta;
use App\Models\User;

/**
 * RF-17.1: registran el cambio de llanta el mecánico y el supervisor; el
 * administrador y el super administrador además pueden corregir registros
 * ajenos, por su nivel de acceso general.
 */
class RegistroLlantaPolicy
{
    public function viewAny(User $usuario): bool
    {
        return $usuario->activo;
    }

    public function view(User $usuario, RegistroLlanta $registro): bool
    {
        return $usuario->activo && $usuario->puedeVerFlota($registro->equipo?->flota_id);
    }

    public function create(User $usuario): bool
    {
        return $usuario->activo;
    }

    public function update(User $usuario, RegistroLlanta $registro): bool
    {
        if (! $usuario->activo) {
            return false;
        }

        if ($usuario->alMenos(RolUsuario::Administrador)) {
            return true;
        }

        // Quien lo registró puede corregir lo suyo mientras siga en su flota.
        return $registro->usuario_id === $usuario->id
            && $usuario->puedeVerFlota($registro->equipo?->flota_id);
    }

    public function delete(User $usuario, RegistroLlanta $registro): bool
    {
        return $usuario->activo && $usuario->alMenos(RolUsuario::Administrador);
    }
}
