<?php

namespace App\Http\Requests;

use App\Enums\RolUsuario;
use App\Models\User;
use App\Policies\UserPolicy;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\Password;

/**
 * RF-18: alta y edición de usuarios.
 *
 * RN-09 / UserPolicy::asignarRol: nadie salvo el super administrador puede
 * crear u otorgar el rol de super administrador — de lo contrario cualquier
 * administrador podría fabricarse una cuenta cuya actividad él mismo no puede
 * auditar.
 */
class UsuarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        $objetivo = $this->route('usuario');

        if ($objetivo instanceof User) {
            return Gate::allows('update', $objetivo);
        }

        return Gate::allows('create', User::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $usuario = $this->route('usuario');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($usuario)],
            // Obligatoria al crear; en edición, sólo si se quiere cambiar.
            'password' => [$usuario ? 'nullable' : 'required', Password::defaults()],
            'rol' => ['required', new Enum(RolUsuario::class)],
            // El super administrador opera a nivel global (RF-01); los demás
            // roles necesitan una flota principal.
            'flota_id' => [Rule::requiredIf($this->input('rol') !== RolUsuario::SuperAdministrador->value), 'nullable', 'integer', 'exists:flotas,id'],
            // RF-20: un supervisor puede cubrir varias flotas además de la principal.
            'flotas' => ['array'],
            'flotas.*' => ['integer', 'exists:flotas,id'],
            'activo' => ['boolean'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $rol = RolUsuario::tryFrom((string) $this->input('rol'));

                // Se instancia la Policy directamente en vez de pasar por
                // Gate::allows(): `RolUsuario` es un enum sin Policy propia
                // registrada, así que la resolución automática de Gate no
                // sabría qué clase usar para este argumento.
                if ($rol && ! app(UserPolicy::class)->asignarRol($this->user(), $rol)) {
                    $validator->errors()->add('rol', 'Sólo un super administrador puede asignar ese rol.');
                }
            },
        ];
    }
}
