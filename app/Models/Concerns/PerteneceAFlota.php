<?php

namespace App\Models\Concerns;

use App\Enums\RolUsuario;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Acota una consulta a las flotas que el usuario tiene permitido ver.
 *
 * El filtro se aplica siempre en el backend, nunca ocultando botones en el
 * frontend (§7, control de acceso roto).
 */
trait PerteneceAFlota
{
    public function scopeDeFlotasVisibles(Builder $query, User $usuario, string $columna = 'flota_id'): Builder
    {
        if ($usuario->alMenos(RolUsuario::Administrador)) {
            return $query;
        }

        return $query->whereIn($query->qualifyColumn($columna), $usuario->flotasAccesibles());
    }
}
