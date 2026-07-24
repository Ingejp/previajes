<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Activitylog\Models\Concerns\LogsActivity;

/**
 * RF-07: "Nivel Óptimo" / "Nivel bajo" / "Nivelación GLNS" / "SI" / "NO"…
 * `es_optima` es lo que permite calcular el estatus del previaje sin
 * hardcodear qué etiqueta significa hallazgo (RN-04).
 */
class ChecklistOpcion extends Model
{
    use HasFactory, LogsActivity;

    protected $table = 'checklist_opciones';

    protected $fillable = ['seccion_id', 'etiqueta', 'es_optima', 'orden'];

    protected function casts(): array
    {
        return [
            'es_optima' => 'boolean',
            'orden' => 'integer',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['seccion_id', 'etiqueta', 'es_optima', 'orden'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('checklist_opcion');
    }

    public function seccion(): BelongsTo
    {
        return $this->belongsTo(ChecklistSeccion::class, 'seccion_id');
    }
}
