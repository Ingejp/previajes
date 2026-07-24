<?php

namespace App\Http\Controllers;

use App\Models\PreviajeFoto;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Sirve la evidencia fotográfica.
 *
 * Las fotos viven en un disco privado y salen sólo por aquí, después de
 * comprobar que el usuario puede ver el previaje al que pertenecen. Publicarlas
 * en `storage/app/public` las dejaría accesibles a cualquiera que adivine la
 * URL (§7, control de acceso roto).
 */
class PreviajeFotoController extends Controller
{
    public function show(PreviajeFoto $foto): StreamedResponse
    {
        Gate::authorize('view', $foto->previaje);

        $disco = Storage::disk(config('previajes.fotos.disco'));

        abort_unless($disco->exists($foto->ruta_archivo), 404);

        return $disco->response(
            $foto->ruta_archivo,
            headers: [
                'Cache-Control' => 'private, max-age=3600',
                'Content-Disposition' => 'inline',
            ],
        );
    }
}
