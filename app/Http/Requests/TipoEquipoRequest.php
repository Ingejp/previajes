<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** RF-18: alta y edición de tipos de equipo, con su umbral y sus secciones (RN-07). */
class TipoEquipoRequest extends FormRequest
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
            'nombre' => ['required', 'string', 'max:255', Rule::unique('tipos_equipo', 'nombre')->ignore($this->route('tipo_equipo'))],
            'dias_alerta_sin_previaje' => ['required', 'integer', 'min:1', 'max:365'],
            'activo' => ['boolean'],
            // RN-07: qué secciones del checklist le aplican a este tipo de equipo.
            'secciones' => ['array'],
            'secciones.*' => ['integer', 'exists:checklist_secciones,id'],
        ];
    }
}
