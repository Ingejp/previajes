<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** RF-16.1 / RF-18: parámetros globales del sistema (clave/valor). */
class ConfiguracionRequest extends FormRequest
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
            // La clave sólo se fija al crear: cambiarla después rompería el
            // código que la busca por nombre fijo (Configuracion::valor()).
            'clave' => [
                'required',
                'string',
                'max:255',
                'alpha_dash',
                Rule::unique('configuraciones', 'clave')->ignore($this->route('configuracion')),
            ],
            'valor' => ['required', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
