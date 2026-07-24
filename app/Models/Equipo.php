<?php

namespace App\Models;

use App\Models\Concerns\PerteneceAFlota;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Activitylog\Models\Concerns\LogsActivity;

/** RF-04: cabezales, chasis, gensets, reach stackers, top loaders y lo que venga. */
class Equipo extends Model
{
    use HasFactory, LogsActivity, PerteneceAFlota;

    protected $table = 'equipos';

    protected $fillable = ['codigo', 'tipo_equipo_id', 'marca', 'modelo', 'flota_id', 'activo'];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['codigo', 'tipo_equipo_id', 'marca', 'modelo', 'flota_id', 'activo'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('equipo');
    }

    public function tipoEquipo(): BelongsTo
    {
        return $this->belongsTo(TipoEquipo::class);
    }

    public function flota(): BelongsTo
    {
        return $this->belongsTo(Flota::class);
    }

    public function previajes(): HasMany
    {
        return $this->hasMany(Previaje::class);
    }

    public function registrosLlantas(): HasMany
    {
        return $this->hasMany(RegistroLlanta::class);
    }

    /**
     * RN-03: el previaje más reciente del equipo. Los anulados no cuentan,
     * porque dejaron de ser una lectura válida del estado de la unidad.
     */
    public function ultimoPreviaje(): HasOne
    {
        return $this->hasOne(Previaje::class)->ofMany(
            ['created_at' => 'max'],
            fn (Builder $query) => $query->vigentes(),
        );
    }
}
