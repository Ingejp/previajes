<?php

namespace App\Policies;

use App\Enums\RolUsuario;
use App\Models\User;

/**
 * RF-18: alta y gestión de usuarios.
 *
 * RN-09 lleva implícito que el super administrador es opaco para los demás:
 * un administrador no puede crear, editar ni eliminar usuarios con ese rol, ni
 * ascender a nadie hasta él. De lo contrario podría fabricarse una cuenta cuya
 * actividad él mismo no puede ver.
 */
class UserPolicy
{
    public function viewAny(User $usuario): bool
    {
        return $usuario->activo && $usuario->alMenos(RolUsuario::Administrador);
    }

    public function view(User $usuario, User $objetivo): bool
    {
        if ($usuario->id === $objetivo->id) {
            return true;
        }

        return $this->viewAny($usuario) && $this->puedeOperarSobre($usuario, $objetivo);
    }

    public function create(User $usuario): bool
    {
        return $usuario->activo && $usuario->alMenos(RolUsuario::Administrador);
    }

    public function update(User $usuario, User $objetivo): bool
    {
        return $this->create($usuario) && $this->puedeOperarSobre($usuario, $objetivo);
    }

    public function delete(User $usuario, User $objetivo): bool
    {
        // Nadie se elimina a sí mismo: dejaría el sistema sin ese acceso.
        return $usuario->id !== $objetivo->id
            && $this->create($usuario)
            && $this->puedeOperarSobre($usuario, $objetivo);
    }

    /** Sólo el super administrador puede tocar a otro super administrador. */
    public function asignarRol(User $usuario, RolUsuario $rol): bool
    {
        if (! $this->create($usuario)) {
            return false;
        }

        return $rol !== RolUsuario::SuperAdministrador || $usuario->esSuperAdministrador();
    }

    private function puedeOperarSobre(User $usuario, User $objetivo): bool
    {
        return ! $objetivo->esSuperAdministrador() || $usuario->esSuperAdministrador();
    }
}
