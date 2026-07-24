<?php

namespace App\Policies;

use App\Enums\RolUsuario;
use App\Models\Equipo;
use App\Models\User;

/** RF-04, RF-16, RF-18: consulta abierta a la flota propia, CRUD desde supervisor. */
class EquipoPolicy
{
    public function viewAny(User $usuario): bool
    {
        return $usuario->activo;
    }

    public function view(User $usuario, Equipo $equipo): bool
    {
        return $usuario->activo && $usuario->puedeVerFlota($equipo->flota_id);
    }

    public function create(User $usuario): bool
    {
        return $usuario->activo && $usuario->alMenos(RolUsuario::Supervisor);
    }

    public function update(User $usuario, Equipo $equipo): bool
    {
        return $this->create($usuario);
    }

    public function delete(User $usuario, Equipo $equipo): bool
    {
        return $this->create($usuario);
    }
}
