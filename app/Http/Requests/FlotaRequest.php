<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** RF-18: alta y edición de flotas. */
class FlotaRequest extends FormRequest
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
            'nombre' => ['required', 'string', 'max:255', Rule::unique('flotas', 'nombre')->ignore($this->route('flota'))],
            'pais' => ['required', 'string', 'max:255'],
            'activo' => ['boolean'],
        ];
    }
}
