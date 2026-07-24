<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Respuesta a un ítem del checklist dentro de un previaje.
 *
 * Las fotos de evidencia no cuelgan de aquí sino del previaje (`previaje_fotos`
 * lleva `checklist_item_id`); se agrupan por ítem al presentarlas, para poder
 * cargarlas de una sola vez junto con el previaje.
 *
 * `cantidad_agregada` son los galones que se agregaron para nivelar (RF-08) y
 * `observaciones` es el detalle del hallazgo a nivel de ítem (RF-09.2), que es
 * distinto y complementario a la observación general de la sección.
 */
class PreviajeRespuesta extends Model
{
    use HasFactory;

    protected $table = 'previaje_respuestas';

    protected $fillable = [
        'previaje_id',
        'checklist_item_id',
        'checklist_opcion_id',
        'cantidad_agregada',
        'observaciones',
    ];

    protected function casts(): array
    {
        return ['cantidad_agregada' => 'decimal:2'];
    }

    public function previaje(): BelongsTo
    {
        return $this->belongsTo(Previaje::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(ChecklistItem::class, 'checklist_item_id');
    }

    public function opcion(): BelongsTo
    {
        return $this->belongsTo(ChecklistOpcion::class, 'checklist_opcion_id');
    }

    /** RN-04: esta respuesta constituye un hallazgo. */
    public function esHallazgo(): bool
    {
        return $this->opcion !== null && ! $this->opcion->es_optima;
    }
}
