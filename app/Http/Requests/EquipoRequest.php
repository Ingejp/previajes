<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** RF-18: alta y edición de equipos. */
class EquipoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('administrar');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'codigo' => [
                'required',
                'string',
                'max:255',
                // El código es único dentro de su flota, no globalmente: dos
                // flotas de países distintos pueden compartir numeración.
                Rule::unique('equipos', 'codigo')
                    ->where('flota_id', $this->input('flota_id'))
                    ->ignore($this->route('equipo')),
            ],
            'tipo_equipo_id' => ['required', 'integer', 'exists:tipos_equipo,id'],
            'flota_id' => ['required', 'integer', 'exists:flotas,id'],
            'marca' => ['nullable', 'string', 'max:255'],
            'modelo' => ['nullable', 'string', 'max:255'],
            'activo' => ['boolean'],
        ];
    }
}
