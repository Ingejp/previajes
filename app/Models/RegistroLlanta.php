<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Activitylog\Models\Concerns\LogsActivity;

/**
 * RF-17.1: registro INTERINO de cambio de llantas.
 *
 * Deliberadamente simple y separado del checklist: el previaje registra el
 * hallazgo detectado, esto registra la acción de mantenimiento realizada.
 * Cuando exista el módulo de inventario/mantenimiento, estos datos se migran
 * al flujo definitivo (orden de trabajo, costo, proveedor).
 */
class RegistroLlanta extends Model
{
    use HasFactory, LogsActivity;

    protected $table = 'registros_llantas';

    protected $fillable = ['equipo_id', 'usuario_id', 'cantidad', 'posicion', 'observaciones'];

    protected function casts(): array
    {
        return ['cantidad' => 'integer'];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['equipo_id', 'usuario_id', 'cantidad', 'posicion', 'observaciones'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('registro_llanta');
    }

    public function equipo(): BelongsTo
    {
        return $this->belongsTo(Equipo::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
