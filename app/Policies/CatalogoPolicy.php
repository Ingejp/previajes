<?php

namespace App\Policies;

use App\Enums\RolUsuario;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Política compartida por los catálogos administrables (RF-18): flotas, tipos
 * de equipo, secciones, ítems, opciones y configuraciones.
 *
 * Todos siguen la misma regla — se consultan desde cualquier rol activo porque
 * alimentan filtros y formularios, y los modifica supervisor en adelante. La
 * gestión de usuarios queda aparte, en `UserPolicy`, reservada a Administrador.
 */
class CatalogoPolicy
{
    public function viewAny(User $usuario): bool
    {
        return $usuario->activo;
    }

    public function view(User $usuario, Model $modelo): bool
    {
        return $usuario->activo;
    }

    public function create(User $usuario): bool
    {
        return $usuario->activo && $usuario->alMenos(RolUsuario::Supervisor);
    }

    public function update(User $usuario, Model $modelo): bool
    {
        return $this->create($usuario);
    }

    public function delete(User $usuario, Model $modelo): bool
    {
        return $this->create($usuario);
    }
}
