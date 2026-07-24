<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Activitylog\Models\Concerns\LogsActivity;

/**
 * Parámetros GLOBALES del sistema (RF-16.1). Ojo: el umbral de días sin
 * previaje NO vive aquí — es un campo de `tipos_equipo`, porque varía por tipo
 * de equipo (RN-12).
 */
class Configuracion extends Model
{
    use HasFactory, LogsActivity;

    /** Tamaño máximo, en KB, al que se comprime cada foto de evidencia (RF-11). */
    public const TAMANO_MAXIMO_FOTO_KB = 'tamano_maximo_foto_kb';

    /** Lado mayor, en píxeles, al que se redimensiona la foto antes de comprimir. */
    public const ANCHO_MAXIMO_FOTO_PX = 'ancho_maximo_foto_px';

    protected $table = 'configuraciones';

    protected $fillable = ['clave', 'valor', 'descripcion'];

    protected static function booted(): void
    {
        static::saved(fn (self $config) => Cache::forget(self::cacheKey($config->clave)));
        static::deleted(fn (self $config) => Cache::forget(self::cacheKey($config->clave)));
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['clave', 'valor', 'descripcion'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('configuracion');
    }

    public static function valor(string $clave, string $porDefecto = ''): string
    {
        return Cache::rememberForever(
            self::cacheKey($clave),
            fn () => static::query()->where('clave', $clave)->value('valor') ?? $porDefecto,
        );
    }

    public static function entero(string $clave, int $porDefecto = 0): int
    {
        $valor = static::valor($clave, (string) $porDefecto);

        return is_numeric($valor) ? (int) $valor : $porDefecto;
    }

    private static function cacheKey(string $clave): string
    {
        return "configuracion:{$clave}";
    }
}
