<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/** RF-18: alta y edición de opciones de respuesta dentro de una sección. */
class ChecklistOpcionRequest extends FormRequest
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
            'etiqueta' => ['required', 'string', 'max:255'],
            // RN-04: si no es óptima, la respuesta constituye un hallazgo.
            'es_optima' => ['boolean'],
            'orden' => ['required', 'integer', 'min:0', 'max:999'],
        ];
    }
}
