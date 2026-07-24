<?php

namespace App\Http\Requests;

use App\Models\ChecklistItem;
use App\Models\ChecklistOpcion;
use App\Models\ChecklistSeccion;
use App\Models\Equipo;
use App\Models\Previaje;
use App\Services\ChecklistService;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

/**
 * Validación del previaje (RF-13). Toda regla vive en el backend: el frontend
 * replica la lógica para dar feedback inmediato, pero nunca es la autoridad.
 *
 * Las reglas condicionales de RN-06, RN-10 y RN-11 dependen de si la opción
 * elegida es óptima, dato que sólo se conoce consultando el catálogo — por eso
 * se resuelven en `after()` y no en el arreglo de reglas.
 */
abstract class PreviajeRequest extends FormRequest
{
    private ?Equipo $equipo = null;

    /** @var Collection<int, ChecklistSeccion>|null */
    private ?Collection $checklist = null;

    /** El previaje que se está editando, si aplica. */
    abstract protected function previajeExistente(): ?Previaje;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $maxSubidaKb = config('previajes.fotos.max_subida_kb');
        $mimes = config('previajes.fotos.mimes_permitidos');

        return [
            'equipo_id' => ['required', 'integer', Rule::exists('equipos', 'id')->where('activo', true)],

            // RF-09.1: ambos campos se capturan siempre; quedan nullable sólo
            // para el equipo que no tenga instalado uno de los dos instrumentos.
            'kilometraje' => ['nullable', 'integer', 'min:0', 'max:99999999'],
            'horometro' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],

            'observaciones_seccion' => ['required', 'array'],
            'observaciones_seccion.*' => ['required', 'string', 'max:2000'],

            'respuestas' => ['required', 'array'],
            'respuestas.*.checklist_opcion_id' => ['required', 'integer', 'exists:checklist_opciones,id'],
            'respuestas.*.cantidad_agregada' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'respuestas.*.observaciones' => ['nullable', 'string', 'max:2000'],

            // §7, integridad de datos: se valida el MIME declarado y además la
            // regla `image`, que abre el archivo y comprueba que de verdad sea
            // una imagen. Sin ella bastaría con renombrar un ejecutable a .jpg.
            'fotos' => ['nullable', 'array'],
            'fotos.*' => ['nullable', 'array', 'max:'.config('previajes.fotos.max_por_item')],
            'fotos.*.*' => ['file', 'image', 'mimetypes:'.implode(',', $mimes), "max:{$maxSubidaKb}"],
            'fotos_generales' => ['nullable', 'array', 'max:10'],
            'fotos_generales.*' => ['file', 'image', 'mimetypes:'.implode(',', $mimes), "max:{$maxSubidaKb}"],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'observaciones_seccion.*.required' => 'La observación de la sección es obligatoria.',
            'respuestas.*.checklist_opcion_id.required' => 'Debe seleccionar una respuesta para este ítem.',
            'fotos.*.*.mimetypes' => 'El archivo debe ser una imagen (JPG, PNG, WebP o HEIC).',
            'fotos.*.*.max' => 'La imagen supera el tamaño máximo permitido.',
        ];
    }

    public function after(): array
    {
        return [
            fn (Validator $validator) => $this->validarChecklist($validator),
            fn (Validator $validator) => $this->validarLecturas($validator),
        ];
    }

    /**
     * Comprueba, contra el catálogo, que el checklist enviado corresponda al
     * tipo de equipo y cumpla las reglas condicionales.
     */
    private function validarChecklist(Validator $validator): void
    {
        $equipo = $this->equipo();

        if ($equipo === null) {
            return;
        }

        if (! $this->user()->puedeVerFlota($equipo->flota_id)) {
            $validator->errors()->add('equipo_id', 'No tiene acceso a los equipos de esta flota.');

            return;
        }

        $secciones = $this->checklist();
        $items = $secciones->flatMap->items->keyBy('id');
        $respuestas = collect($this->input('respuestas', []));

        // RF-13: ningún ítem activo puede quedar sin responder.
        foreach ($items as $itemId => $item) {
            if (! $respuestas->has($itemId)) {
                $validator->errors()->add(
                    "respuestas.{$itemId}.checklist_opcion_id",
                    "Falta responder: {$item->descripcion}",
                );
            }
        }

        // RF-09: la observación general de cada sección aplicable es obligatoria.
        $observaciones = collect($this->input('observaciones_seccion', []));
        foreach ($secciones as $seccion) {
            if (blank($observaciones->get($seccion->id))) {
                $validator->errors()->add(
                    "observaciones_seccion.{$seccion->id}",
                    "Las observaciones de {$seccion->nombre} son obligatorias.",
                );
            }
        }

        $opciones = ChecklistOpcion::query()
            ->whereIn('id', $respuestas->pluck('checklist_opcion_id')->filter())
            ->get()
            ->keyBy('id');

        foreach ($respuestas as $itemId => $respuesta) {
            $item = $items->get((int) $itemId);

            // Un ítem que no pertenece al checklist de este equipo no se acepta:
            // sin esto se podrían inyectar respuestas de otro tipo de equipo.
            if ($item === null) {
                $validator->errors()->add(
                    "respuestas.{$itemId}",
                    'El ítem no corresponde al checklist de este equipo.',
                );

                continue;
            }

            $opcion = $opciones->get((int) ($respuesta['checklist_opcion_id'] ?? 0));

            if ($opcion === null) {
                continue; // ya lo reportó la regla `exists`
            }

            // La opción debe ser una de las de su propia sección (RF-07).
            if ($opcion->seccion_id !== $item->seccion_id) {
                $validator->errors()->add(
                    "respuestas.{$itemId}.checklist_opcion_id",
                    'La respuesta seleccionada no es válida para este ítem.',
                );

                continue;
            }

            if ($opcion->es_optima) {
                continue;
            }

            $this->validarHallazgo($validator, $item, $respuesta, (int) $itemId);
        }
    }

    /**
     * Reglas que sólo aplican cuando el ítem quedó fuera del estado óptimo:
     * galones agregados si es fluido (RN-06), observación del ítem (RN-10) y
     * al menos una foto de evidencia (RN-11).
     *
     * @param  array<string, mixed>  $respuesta
     */
    private function validarHallazgo(Validator $validator, ChecklistItem $item, array $respuesta, int $itemId): void
    {
        if ($item->es_fluido && blank($respuesta['cantidad_agregada'] ?? null)) {
            $validator->errors()->add(
                "respuestas.{$itemId}.cantidad_agregada",
                'Indique cuántos galones se agregaron para nivelar.',
            );
        }

        if (blank($respuesta['observaciones'] ?? null)) {
            $validator->errors()->add(
                "respuestas.{$itemId}.observaciones",
                'Describa el hallazgo encontrado.',
            );
        }

        if ($this->cantidadFotos($itemId) < 1) {
            $validator->errors()->add(
                "fotos.{$itemId}",
                'Adjunte al menos una foto como evidencia del hallazgo.',
            );
        }
    }

    /**
     * Fotos disponibles para un ítem: las que vienen en esta petición más las
     * ya guardadas que no se estén eliminando. En una edición, la evidencia
     * cargada antes sigue contando (RF-11).
     */
    private function cantidadFotos(int $itemId): int
    {
        $nuevas = count($this->file("fotos.{$itemId}", []));

        $previaje = $this->previajeExistente();

        if ($previaje === null) {
            return $nuevas;
        }

        $eliminadas = collect($this->input('fotos_eliminadas', []))->map(fn ($id) => (int) $id);

        $existentes = $previaje->fotos
            ->where('checklist_item_id', $itemId)
            ->reject(fn ($foto) => $eliminadas->contains($foto->id))
            ->count();

        return $nuevas + $existentes;
    }

    /** RN-02: ni el kilometraje ni el horómetro pueden retroceder. */
    private function validarLecturas(Validator $validator): void
    {
        $equipo = $this->equipo();

        if ($equipo === null) {
            return;
        }

        $ultimas = app(ChecklistService::class)->ultimasLecturas($equipo, $this->previajeExistente());

        $kilometraje = $this->input('kilometraje');
        if (filled($kilometraje) && $ultimas['kilometraje'] !== null && $kilometraje < $ultimas['kilometraje']) {
            $validator->errors()->add(
                'kilometraje',
                "El kilometraje no puede ser menor al último registrado ({$ultimas['kilometraje']} km).",
            );
        }

        $horometro = $this->input('horometro');
        if (filled($horometro) && $ultimas['horometro'] !== null && $horometro < $ultimas['horometro']) {
            $validator->errors()->add(
                'horometro',
                "El horómetro no puede ser menor al último registrado ({$ultimas['horometro']} h).",
            );
        }
    }

    protected function equipo(): ?Equipo
    {
        if ($this->equipo !== null) {
            return $this->equipo;
        }

        $id = $this->input('equipo_id');

        return $this->equipo = $id ? Equipo::find($id) : null;
    }

    /** @return Collection<int, ChecklistSeccion> */
    private function checklist(): Collection
    {
        return $this->checklist ??= app(ChecklistService::class)->paraEquipo($this->equipo());
    }
}
