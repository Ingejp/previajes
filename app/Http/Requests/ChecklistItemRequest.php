<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/** RF-18: alta y edición de ítems de checklist dentro de una sección. */
class ChecklistItemRequest extends FormRequest
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
            'descripcion' => ['required', 'string', 'max:255'],
            // RF-08: habilita el campo de galones agregados cuando hay hallazgo.
            'es_fluido' => ['boolean'],
            'orden' => ['required', 'integer', 'min:0', 'max:999'],
            'activo' => ['boolean'],
        ];
    }
}
