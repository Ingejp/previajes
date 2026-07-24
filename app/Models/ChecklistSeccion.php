<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Activitylog\Models\Concerns\LogsActivity;

/**
 * RF-05: MOTOR, CHASIS, CABINA Y ACCESORIOS y las que se agreguen a futuro
 * (ej. "SISTEMA HIDRÁULICO" para un reach stacker), sin tocar código.
 */
class ChecklistSeccion extends Model
{
    use HasFactory, LogsActivity;

    protected $table = 'checklist_secciones';

    protected $fillable = ['nombre', 'orden', 'activo'];

    protected function casts(): array
    {
        return [
            'orden' => 'integer',
            'activo' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['nombre', 'orden', 'activo'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('checklist_seccion');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ChecklistItem::class, 'seccion_id')->orderBy('orden');
    }

    /** RF-07: cada sección define su propio juego de opciones de respuesta. */
    public function opciones(): HasMany
    {
        return $this->hasMany(ChecklistOpcion::class, 'seccion_id')->orderBy('orden');
    }

    public function tiposEquipo(): BelongsToMany
    {
        return $this->belongsToMany(
            TipoEquipo::class,
            'tipo_equipo_seccion',
            'checklist_seccion_id',
            'tipo_equipo_id',
        );
    }
}
