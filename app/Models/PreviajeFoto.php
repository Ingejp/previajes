<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * RF-11: evidencia fotográfica. La imagen se comprime en un Job en cola, así
 * que `procesada` distingue la que ya pasó por ese paso de la recién subida.
 */
class PreviajeFoto extends Model
{
    use HasFactory;

    protected $table = 'previaje_fotos';

    protected $fillable = ['previaje_id', 'checklist_item_id', 'ruta_archivo', 'tamano_kb', 'procesada'];

    protected function casts(): array
    {
        return [
            'tamano_kb' => 'integer',
            'procesada' => 'boolean',
        ];
    }

    public function previaje(): BelongsTo
    {
        return $this->belongsTo(Previaje::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(ChecklistItem::class, 'checklist_item_id');
    }

    /**
     * Las fotos son evidencia de auditoría, no contenido público: se sirven
     * siempre por una ruta autorizada, nunca por un enlace directo al disco.
     */
    public function url(): string
    {
        return route('previaje-fotos.show', $this);
    }

    public function existeArchivo(): bool
    {
        return Storage::disk('local')->exists($this->ruta_archivo);
    }
}
