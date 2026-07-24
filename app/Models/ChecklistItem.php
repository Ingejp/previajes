<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Activitylog\Models\Concerns\LogsActivity;

/**
 * RF-06: ítems administrables dentro de cada sección.
 * `es_fluido` marca los que, al ir en nivel bajo, habilitan el campo de
 * galones agregados (RF-08 / RN-06).
 */
class ChecklistItem extends Model
{
    use HasFactory, LogsActivity;

    protected $table = 'checklist_items';

    protected $fillable = ['seccion_id', 'descripcion', 'es_fluido', 'orden', 'activo'];

    protected function casts(): array
    {
        return [
            'es_fluido' => 'boolean',
            'orden' => 'integer',
            'activo' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['seccion_id', 'descripcion', 'es_fluido', 'orden', 'activo'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('checklist_item');
    }

    public function seccion(): BelongsTo
    {
        return $this->belongsTo(ChecklistSeccion::class, 'seccion_id');
    }

    public function respuestas(): HasMany
    {
        return $this->hasMany(PreviajeRespuesta::class);
    }
}
