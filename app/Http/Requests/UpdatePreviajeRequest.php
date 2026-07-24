<?php

namespace App\Http\Requests;

use App\Models\Previaje;
use Illuminate\Validation\Rule;

/**
 * RF-12 / RN-05: el previaje sí se puede editar después de enviado. La edición
 * dispara alerta y queda en auditoría; de eso se encargan el servicio y el
 * observer, aquí sólo se valida.
 */
class UpdatePreviajeRequest extends PreviajeRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->previaje());
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            // El equipo no se cambia al editar: sería otro previaje distinto,
            // y arruinaría la trazabilidad de kilometraje del equipo original.
            'equipo_id' => ['required', 'integer', Rule::in([$this->previaje()->equipo_id])],

            'fotos_eliminadas' => ['nullable', 'array'],
            'fotos_eliminadas.*' => [
                'integer',
                Rule::exists('previaje_fotos', 'id')->where('previaje_id', $this->previaje()->id),
            ],
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'equipo_id.in' => 'No se puede cambiar el equipo de un previaje ya registrado.',
        ]);
    }

    protected function previajeExistente(): ?Previaje
    {
        return $this->previaje();
    }

    private function previaje(): Previaje
    {
        return $this->route('previaje')->loadMissing('fotos');
    }
}
