<?php

namespace App\Policies;

use App\Enums\RolUsuario;
use App\Models\Previaje;
use App\Models\User;

/**
 * Permisos del previaje (§4, RF-12, RN-05).
 *
 * El filtrado por flota se aplica aquí y en las consultas, nunca sólo
 * ocultando botones en el frontend (§7, control de acceso roto).
 */
class PreviajePolicy
{
    public function viewAny(User $usuario): bool
    {
        return $usuario->activo;
    }

    /**
     * El mecánico ve su propio historial; el supervisor, todo lo de su(s)
     * flota(s); administrador y super administrador, todo.
     */
    public function view(User $usuario, Previaje $previaje): bool
    {
        if (! $usuario->activo) {
            return false;
        }

        if ($usuario->alMenos(RolUsuario::Administrador)) {
            return true;
        }

        if ($usuario->esMecanico()) {
            return $previaje->mecanico_id === $usuario->id;
        }

        return $usuario->puedeVerFlota($previaje->flota_id);
    }

    public function create(User $usuario): bool
    {
        return $usuario->activo;
    }

    /**
     * RN-05: el previaje lo puede editar el mecánico que lo creó o un rol
     * superior. Un previaje anulado ya no se edita: se conserva como está.
     */
    public function update(User $usuario, Previaje $previaje): bool
    {
        if (! $usuario->activo || $previaje->estaAnulado()) {
            return false;
        }

        if ($usuario->alMenos(RolUsuario::Administrador)) {
            return true;
        }

        if ($usuario->esMecanico()) {
            return $previaje->mecanico_id === $usuario->id;
        }

        return $usuario->puedeVerFlota($previaje->flota_id);
    }

    /**
     * RF-12: anular sustituye al borrado. Es una decisión administrativa, así
     * que queda fuera del alcance del mecánico.
     */
    public function anular(User $usuario, Previaje $previaje): bool
    {
        if (! $usuario->activo || $previaje->estaAnulado()) {
            return false;
        }

        if ($usuario->alMenos(RolUsuario::Administrador)) {
            return true;
        }

        return $usuario->esSupervisor() && $usuario->puedeVerFlota($previaje->flota_id);
    }

    /** RF-12: los previajes nunca se eliminan físicamente. */
    public function delete(User $usuario, Previaje $previaje): bool
    {
        return false;
    }
}
