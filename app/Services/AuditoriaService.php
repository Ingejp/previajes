<?php

namespace App\Services;

use App\Enums\RolUsuario;
use App\Models\Acceso;
use App\Models\Previaje;
use App\Models\RegistroLlanta;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Spatie\Activitylog\Models\Activity;

/**
 * Consulta de la pantalla de auditoría (RF-20).
 *
 * Todo el recorte por rol se hace aquí, en la consulta, nunca ocultando filas
 * en el frontend (§12). Las reglas de RN-09 son:
 *
 *  - Supervisor: sólo lo que ocurre en su(s) flota(s).
 *  - Administrador: todas las flotas, pero jamás la actividad de un super
 *    administrador.
 *  - Super administrador: todo, sin restricción.
 *  - Mecánico: no entra (lo corta el Gate `ver-auditoria`).
 */
class AuditoriaService
{
    /**
     * Cambios de datos sobre previajes y catálogos (`activity_log`).
     *
     * @param  array<string, mixed>  $filtros
     * @return Builder<Activity>
     */
    public function cambios(User $usuario, array $filtros = []): Builder
    {
        $consulta = Activity::query()->with('causer:id,name,rol');

        if ($usuario->esSupervisor()) {
            // El supervisor no administra catálogos, así que su auditoría se
            // limita a los registros operativos de su(s) flota(s).
            $flotas = $usuario->flotasAccesibles();

            $consulta->where(function (Builder $q) use ($flotas) {
                $q->where(fn (Builder $p) => $p
                    ->where('subject_type', Previaje::class)
                    ->whereIn('subject_id', Previaje::whereIn('flota_id', $flotas)->select('id')))
                    ->orWhere(fn (Builder $r) => $r
                        ->where('subject_type', RegistroLlanta::class)
                        ->whereIn('subject_id', RegistroLlanta::whereHas(
                            'equipo',
                            fn ($e) => $e->whereIn('flota_id', $flotas),
                        )->select('id')));
            });
        }

        // RN-09: la actividad del super administrador es invisible para todos
        // los demás roles, incluido el administrador.
        //
        // El `orWhereNull` no es cosmético: en SQL `causer_id NOT IN (...)` da
        // NULL —no verdadero— cuando la columna es NULL, así que sin él toda la
        // actividad generada por el sistema (seeders, jobs, comandos) se caería
        // del registro justo para quien tiene que auditarla.
        if (! $usuario->esSuperAdministrador()) {
            $superAdmins = $this->idsSuperAdministradores();

            $consulta
                ->where(fn (Builder $q) => $q
                    ->whereNotIn('causer_id', $superAdmins)
                    ->orWhereNull('causer_id'))
                // RF-20 sólo habla de la actividad "realizada por" el super
                // administrador, pero un cambio que lo tiene como SUJETO
                // (su alta, su correo, su rol) delataría igual su existencia y
                // sus datos. Se oculta también, que es la lectura que de
                // verdad protege la regla.
                ->where(fn (Builder $q) => $q
                    ->where('subject_type', '!=', User::class)
                    ->orWhereNotIn('subject_id', $superAdmins)
                    ->orWhereNull('subject_id'));
        }

        return $this->aplicarFiltrosComunes($consulta, $filtros, 'causer_id')
            ->when($filtros['tipo_evento'] ?? null, fn (Builder $q, $tipo) => $q->where('event', $tipo))
            ->when($filtros['log'] ?? null, fn (Builder $q, $log) => $q->where('log_name', $log));
    }

    /**
     * Accesos al sistema: ingresos, salidas e intentos fallidos.
     *
     * @param  array<string, mixed>  $filtros
     * @return Builder<Acceso>
     */
    public function accesos(User $usuario, array $filtros = []): Builder
    {
        $consulta = Acceso::query()->with('usuario:id,name,rol');

        if ($usuario->esSupervisor()) {
            $consulta->whereIn(
                'usuario_id',
                User::whereIn('flota_id', $usuario->flotasAccesibles())->select('id'),
            );
        }

        if (! $usuario->esSuperAdministrador()) {
            // El `orWhereNull` mantiene visibles los intentos fallidos contra
            // correos inexistentes, que no tienen usuario asociado y son
            // justamente lo que interesa vigilar.
            $consulta->where(fn (Builder $q) => $q
                ->whereNotIn('usuario_id', $this->idsSuperAdministradores())
                ->orWhereNull('usuario_id'));
        }

        return $this->aplicarFiltrosComunes($consulta, $filtros, 'usuario_id')
            ->when(
                ($filtros['solo_fallidos'] ?? false),
                fn (Builder $q) => $q->where('exitoso', false),
            );
    }

    /**
     * Usuarios que se pueden elegir en el filtro, ya recortados por rol.
     *
     * @return Collection<int, User>
     */
    public function usuariosFiltrables(User $usuario): Collection
    {
        return User::query()
            ->when($usuario->esSupervisor(), fn ($q) => $q->whereIn('flota_id', $usuario->flotasAccesibles()))
            ->when(! $usuario->esSuperAdministrador(), fn ($q) => $q->where('rol', '!=', RolUsuario::SuperAdministrador->value))
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function aplicarFiltrosComunes(Builder $consulta, array $filtros, string $columnaUsuario): Builder
    {
        return $consulta
            ->when($filtros['usuario_id'] ?? null, fn (Builder $q, $id) => $q->where($columnaUsuario, $id))
            ->when($filtros['desde'] ?? null, fn (Builder $q, $f) => $q->whereDate('created_at', '>=', $f))
            ->when($filtros['hasta'] ?? null, fn (Builder $q, $f) => $q->whereDate('created_at', '<=', $f))
            ->latest();
    }

    /** @return array<int, int> */
    private function idsSuperAdministradores(): array
    {
        return User::where('rol', RolUsuario::SuperAdministrador->value)->pluck('id')->all();
    }
}
