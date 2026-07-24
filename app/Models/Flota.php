<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/** RF-02: Honduras, Guatemala u otra; el módulo es multi-flota desde el diseño. */
class Flota extends Model
{
    use HasFactory, LogsActivity;

    protected $table = 'flotas';

    protected $fillable = ['nombre', 'pais', 'activo'];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['nombre', 'pais', 'activo'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('flota');
    }

    public function equipos(): HasMany
    {
        return $this->hasMany(Equipo::class);
    }

    public function previajes(): HasMany
    {
        return $this->hasMany(Previaje::class);
    }

    /** Usuarios cuya flota principal es ésta. */
    public function usuarios(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /** Usuarios asignados por pivote (supervisores que cubren varias flotas). */
    public function usuariosAsignados(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'flota_user');
    }
}
