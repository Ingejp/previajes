<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Activitylog\Models\Concerns\LogsActivity;

/**
 * RF-03: catálogo administrable de tipos de equipo.
 * RF-16.1 / RN-12: `dias_alerta_sin_previaje` es el umbral por tipo de equipo,
 * no un valor global — un cabezal y un genset no se inspeccionan con la misma
 * frecuencia.
 */
class TipoEquipo extends Model
{
    use HasFactory, LogsActivity;

    protected $table = 'tipos_equipo';

    protected $fillable = ['nombre', 'dias_alerta_sin_previaje', 'activo'];

    protected function casts(): array
    {
        return [
            'dias_alerta_sin_previaje' => 'integer',
            'activo' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['nombre', 'dias_alerta_sin_previaje', 'activo'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('tipo_equipo');
    }

    public function equipos(): HasMany
    {
        return $this->hasMany(Equipo::class);
    }

    /** RN-07: qué secciones del checklist aplican a este tipo de equipo. */
    public function secciones(): BelongsToMany
    {
        return $this->belongsToMany(
            ChecklistSeccion::class,
            'tipo_equipo_seccion',
            'tipo_equipo_id',
            'checklist_seccion_id',
        );
    }
}
