<?php

namespace App\Models;

use App\Enums\RolUsuario;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Activitylog\Models\Concerns\LogsActivity;

/**
 * Usuario del sistema (§9 `usuarios`). Se conserva el nombre de tabla `users`
 * por convención de Laravel y compatibilidad con el andamiaje de autenticación
 * (RNF-06); las columnas de negocio sí siguen la nomenclatura del documento.
 */
class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, LogsActivity, Notifiable;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'rol',
        'flota_id',
        'activo',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'rol' => RolUsuario::class,
            'activo' => 'boolean',
        ];
    }

    // RF-19: los cambios sobre usuarios son parte de la auditoría administrativa.
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'email', 'rol', 'flota_id', 'activo'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('usuario');
    }

    /** Flota principal: la que se usa por defecto al crear un previaje. */
    public function flota(): BelongsTo
    {
        return $this->belongsTo(Flota::class);
    }

    /** Flotas adicionales asignadas, para el supervisor que cubre varias (RF-20). */
    public function flotas(): BelongsToMany
    {
        return $this->belongsToMany(Flota::class, 'flota_user');
    }

    public function previajes(): HasMany
    {
        return $this->hasMany(Previaje::class, 'mecanico_id');
    }

    public function registrosLlantas(): HasMany
    {
        return $this->hasMany(RegistroLlanta::class, 'usuario_id');
    }

    public function accesos(): HasMany
    {
        return $this->hasMany(Acceso::class, 'usuario_id');
    }

    public function esMecanico(): bool
    {
        return $this->rol === RolUsuario::Mecanico;
    }

    public function esSupervisor(): bool
    {
        return $this->rol === RolUsuario::Supervisor;
    }

    public function esAdministrador(): bool
    {
        return $this->rol === RolUsuario::Administrador;
    }

    public function esSuperAdministrador(): bool
    {
        return $this->rol === RolUsuario::SuperAdministrador;
    }

    public function alMenos(RolUsuario $minimo): bool
    {
        return $this->rol->alMenos($minimo);
    }

    /**
     * IDs de las flotas que este usuario puede ver. El administrador y el super
     * administrador operan sobre todas; los demás quedan acotados a su flota
     * principal más las asignadas por pivote (RF-01, RF-02, RN-09).
     *
     * @return Collection<int, int>
     */
    public function flotasAccesibles(): Collection
    {
        if ($this->alMenos(RolUsuario::Administrador)) {
            return Flota::query()->pluck('id');
        }

        return $this->flotas()
            ->pluck('flotas.id')
            ->push($this->flota_id)
            ->filter()
            ->unique()
            ->values();
    }

    public function puedeVerFlota(?int $flotaId): bool
    {
        if ($this->alMenos(RolUsuario::Administrador)) {
            return true;
        }

        return $flotaId !== null && $this->flotasAccesibles()->contains($flotaId);
    }
}
