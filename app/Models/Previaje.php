<?php

namespace App\Models;

use App\Enums\EstatusPreviaje;
use App\Models\Concerns\PerteneceAFlota;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Registro de previaje (RF-09). La fecha/hora nunca la escribe el usuario: sale
 * del reloj del servidor vía `created_at` (RF-10 / RN-01).
 */
class Previaje extends Model
{
    use HasFactory, LogsActivity, PerteneceAFlota;

    protected $table = 'previajes';

    protected $fillable = [
        'equipo_id',
        'mecanico_id',
        'flota_id',
        'kilometraje',
        'horometro',
        'estatus',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'estatus' => EstatusPreviaje::class,
            'kilometraje' => 'integer',
            'horometro' => 'decimal:2',
            'anulado_en' => 'datetime',
        ];
    }

    /**
     * RF-12: cada edición de un previaje queda en la bitácora con el valor
     * anterior y el nuevo.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['equipo_id', 'mecanico_id', 'flota_id', 'kilometraje', 'horometro', 'estatus', 'anulado_en', 'motivo_anulacion'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('previaje');
    }

    public function equipo(): BelongsTo
    {
        return $this->belongsTo(Equipo::class);
    }

    public function mecanico(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mecanico_id');
    }

    public function flota(): BelongsTo
    {
        return $this->belongsTo(Flota::class);
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function anuladoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'anulado_por');
    }

    public function respuestas(): HasMany
    {
        return $this->hasMany(PreviajeRespuesta::class);
    }

    public function observacionesSeccion(): HasMany
    {
        return $this->hasMany(PreviajeSeccionObservacion::class);
    }

    public function fotos(): HasMany
    {
        return $this->hasMany(PreviajeFoto::class);
    }

    /** Previajes que siguen siendo una lectura válida del estado del equipo. */
    public function scopeVigentes(Builder $query): Builder
    {
        return $query->where($query->qualifyColumn('estatus'), '!=', EstatusPreviaje::Anulado->value);
    }

    public function scopeConHallazgos(Builder $query): Builder
    {
        return $query->where($query->qualifyColumn('estatus'), EstatusPreviaje::ConHallazgos->value);
    }

    public function estaAnulado(): bool
    {
        return $this->estatus === EstatusPreviaje::Anulado;
    }

    public function tieneHallazgos(): bool
    {
        return $this->estatus === EstatusPreviaje::ConHallazgos;
    }

    /**
     * RN-04: recalcula el estatus revisando si alguna respuesta apunta a una
     * opción no óptima. Un previaje anulado conserva su estatus: la anulación
     * es una decisión administrativa, no el resultado del checklist.
     */
    public function recalcularEstatus(): self
    {
        if ($this->estaAnulado()) {
            return $this;
        }

        $tieneHallazgos = $this->respuestas()
            ->whereHas('opcion', fn (Builder $q) => $q->where('es_optima', false))
            ->exists();

        $this->estatus = $tieneHallazgos
            ? EstatusPreviaje::ConHallazgos
            : EstatusPreviaje::SinHallazgos;

        $this->save();

        return $this;
    }
}
