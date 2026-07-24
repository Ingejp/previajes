<?php

namespace App\Services;

use App\Jobs\ComprimirFotoPreviaje;
use App\Models\Previaje;
use App\Models\PreviajeFoto;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * RF-11: evidencia fotográfica.
 *
 * Las fotos van a un disco privado, no a `public`: son evidencia de auditoría
 * y se sirven sólo a través de una ruta que verifica permisos. La compresión
 * corre en cola para no bloquear la respuesta del formulario (RNF-02).
 */
class FotoPreviajeService
{
    /**
     * @param  array<string, mixed>  $datos
     */
    public function adjuntar(Previaje $previaje, array $datos): void
    {
        foreach ($datos['fotos'] ?? [] as $itemId => $archivos) {
            foreach ($this->comoArreglo($archivos) as $archivo) {
                $this->guardar($previaje, $archivo, (int) $itemId);
            }
        }

        // Fotos del previaje en general, sin ítem asociado.
        foreach ($this->comoArreglo($datos['fotos_generales'] ?? []) as $archivo) {
            $this->guardar($previaje, $archivo, null);
        }
    }

    /**
     * @param  array<int, int|string>  $ids
     */
    public function eliminar(Previaje $previaje, array $ids): void
    {
        if ($ids === []) {
            return;
        }

        $fotos = $previaje->fotos()->whereIn('id', $ids)->get();

        foreach ($fotos as $foto) {
            Storage::disk($this->disco())->delete($foto->ruta_archivo);
            $foto->delete();
        }
    }

    /** Encola la compresión de todo lo que aún no ha pasado por ella. */
    public function procesarPendientes(Previaje $previaje): void
    {
        $previaje->fotos()
            ->where('procesada', false)
            ->get()
            ->each(fn (PreviajeFoto $foto) => ComprimirFotoPreviaje::dispatch($foto));
    }

    private function guardar(Previaje $previaje, UploadedFile $archivo, ?int $itemId): PreviajeFoto
    {
        $ruta = $archivo->store("{$this->directorio()}/{$previaje->id}", $this->disco());

        return $previaje->fotos()->create([
            'checklist_item_id' => $itemId,
            'ruta_archivo' => $ruta,
            'tamano_kb' => (int) ceil($archivo->getSize() / 1024),
            'procesada' => false,
        ]);
    }

    /**
     * @return array<int, UploadedFile>
     */
    private function comoArreglo(mixed $archivos): array
    {
        if ($archivos instanceof UploadedFile) {
            return [$archivos];
        }

        return array_filter(
            is_array($archivos) ? $archivos : [],
            fn ($archivo) => $archivo instanceof UploadedFile,
        );
    }

    private function disco(): string
    {
        return config('previajes.fotos.disco');
    }

    private function directorio(): string
    {
        return config('previajes.fotos.directorio');
    }
}
