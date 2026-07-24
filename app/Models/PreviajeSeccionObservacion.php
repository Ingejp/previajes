<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Observación general y obligatoria de cada sección, tal como en el formulario
 * original ("Observaciones motor", "Observaciones chasis"). Complementa —no
 * sustituye— la observación por ítem de RF-09.2.
 */
class PreviajeSeccionObservacion extends Model
{
    use HasFactory;

    protected $table = 'previaje_seccion_observaciones';

    protected $fillable = ['previaje_id', 'checklist_seccion_id', 'observaciones'];

    public function previaje(): BelongsTo
    {
        return $this->belongsTo(Previaje::class);
    }

    public function seccion(): BelongsTo
    {
        return $this->belongsTo(ChecklistSeccion::class, 'checklist_seccion_id');
    }
}
