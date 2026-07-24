<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** RF-18: alta y edición de secciones del checklist. */
class ChecklistSeccionRequest extends FormRequest
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
            'nombre' => ['required', 'string', 'max:255', Rule::unique('checklist_secciones', 'nombre')->ignore($this->route('seccion'))],
            'orden' => ['required', 'integer', 'min:0', 'max:999'],
            'activo' => ['boolean'],
            // RN-07: a qué tipos de equipo les aplica esta sección.
            'tipos_equipo' => ['array'],
            'tipos_equipo.*' => ['integer', 'exists:tipos_equipo,id'],
        ];
    }
}
