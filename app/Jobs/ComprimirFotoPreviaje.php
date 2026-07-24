<?php

namespace App\Jobs;

use App\Models\Configuracion;
use App\Models\PreviajeFoto;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\Exceptions\DecoderException;
use Intervention\Image\Interfaces\ImageInterface;
use Intervention\Image\Laravel\Facades\Image;

/**
 * RF-11: comprime y redimensiona la evidencia fotográfica.
 *
 * Corre en cola (RNF-02, §12) para que el mecánico no espere el
 * procesamiento de imágenes al enviar el formulario, algo especialmente
 * importante con la conexión intermitente del patio (RNF-03).
 *
 * El objetivo de peso y el lado máximo se leen de `configuraciones`, así que
 * el administrador los ajusta sin tocar código (RF-16.1).
 */
class ComprimirFotoPreviaje implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(public readonly PreviajeFoto $foto) {}

    public function handle(): void
    {
        $disco = Storage::disk(config('previajes.fotos.disco'));

        if (! $disco->exists($this->foto->ruta_archivo)) {
            Log::warning('Foto de previaje ausente al comprimir', [
                'foto_id' => $this->foto->id,
                'ruta' => $this->foto->ruta_archivo,
            ]);

            return;
        }

        $ladoMaximo = Configuracion::entero(Configuracion::ANCHO_MAXIMO_FOTO_PX, 1600);
        $pesoObjetivoKb = Configuracion::entero(Configuracion::TAMANO_MAXIMO_FOTO_KB, 400);

        try {
            $imagen = Image::decodeBinary($disco->get($this->foto->ruta_archivo));
        } catch (DecoderException $excepcion) {
            // Un archivo que la validación dejó pasar pero que el decodificador
            // no entiende no se arregla reintentando: se descarta el registro y
            // el archivo para no dejar evidencia rota apuntando a la nada.
            Log::warning('Foto de previaje ilegible; se descarta', [
                'foto_id' => $this->foto->id,
                'error' => $excepcion->getMessage(),
            ]);

            $disco->delete($this->foto->ruta_archivo);
            $this->foto->delete();

            return;
        }

        // `scaleDown` no agranda una foto que ya venga pequeña, y conserva la
        // proporción original.
        $imagen->scaleDown(width: $ladoMaximo, height: $ladoMaximo);

        [$codificada, $calidad] = $this->codificarBajoLimite($imagen, $pesoObjetivoKb);

        // La evidencia siempre queda en JPEG: la ruta cambia de extensión, así
        // que se escribe la nueva y se borra la original.
        $rutaFinal = preg_replace('/\.[^.]+$/', '', $this->foto->ruta_archivo).'.jpg';

        $disco->put($rutaFinal, $codificada);

        if ($rutaFinal !== $this->foto->ruta_archivo) {
            $disco->delete($this->foto->ruta_archivo);
        }

        $this->foto->update([
            'ruta_archivo' => $rutaFinal,
            'tamano_kb' => (int) ceil(strlen($codificada) / 1024),
            'procesada' => true,
        ]);

        Log::info('Foto de previaje comprimida', [
            'foto_id' => $this->foto->id,
            'kb' => (int) ceil(strlen($codificada) / 1024),
            'calidad' => $calidad,
        ]);
    }

    /**
     * Baja la calidad por pasos hasta entrar en el peso objetivo. Se detiene en
     * 40 para no destruir la legibilidad de la evidencia, que es justamente
     * para lo que sirve la foto.
     *
     * @return array{0: string, 1: int}
     */
    private function codificarBajoLimite(ImageInterface $imagen, int $pesoObjetivoKb): array
    {
        $limiteBytes = $pesoObjetivoKb * 1024;

        foreach ([85, 75, 65, 55, 45, 40] as $calidad) {
            // `strip: true` descarta los metadatos EXIF, que además de pesar
            // suelen traer la geolocalización del teléfono del mecánico.
            $codificada = (string) $imagen->encode(
                new JpegEncoder(quality: $calidad, strip: true),
            );

            if (strlen($codificada) <= $limiteBytes) {
                return [$codificada, $calidad];
            }
        }

        return [$codificada, 40];
    }

    public function failed(\Throwable $excepcion): void
    {
        Log::error('Falló la compresión de una foto de previaje', [
            'foto_id' => $this->foto->id,
            'error' => $excepcion->getMessage(),
        ]);
    }
}
