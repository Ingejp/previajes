<?php

namespace App\Http\Controllers\Catalogos;

use App\Enums\RolUsuario;
use App\Http\Controllers\Controller;
use App\Http\Requests\UsuarioRequest;
use App\Models\Flota;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

/**
 * RF-18: alta y gestión de usuarios.
 *
 * No hay `destroy`: un usuario que ya creó previajes o registros no se borra
 * (rompería su historial); se desactiva, lo que además cierra su sesión de
 * inmediato vía `AsegurarUsuarioActivo` (RF-18, §7).
 */
class UsuarioController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', User::class);

        $usuario = request()->user();

        return Inertia::render('catalogos/usuarios/index', [
            // RN-09: un administrador ni siquiera ve la fila del super
            // administrador en el listado, no sólo sus acciones.
            'usuarios' => User::query()
                ->when(! $usuario->esSuperAdministrador(), fn ($q) => $q->where('rol', '!=', 'super_administrador'))
                ->with(['flota:id,nombre', 'flotas:id,nombre'])
                ->orderBy('name')
                ->get(),
            'opciones' => [
                'flotas' => Flota::where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
                'roles' => $usuario->esSuperAdministrador()
                    ? RolUsuario::opciones()
                    : collect(RolUsuario::opciones())->reject(fn ($r) => $r['valor'] === 'super_administrador')->values(),
            ],
        ]);
    }

    public function store(UsuarioRequest $request): RedirectResponse
    {
        $datos = $request->validated();

        $creado = User::create([
            ...collect($datos)->except(['flotas', 'password'])->all(),
            'password' => Hash::make($datos['password']),
            'email_verified_at' => now(),
        ]);

        $creado->flotas()->sync($datos['flotas'] ?? []);

        return back()->with('exito', 'Usuario creado.');
    }

    public function update(UsuarioRequest $request, User $usuario): RedirectResponse
    {
        $datos = $request->validated();

        $usuario->update([
            ...collect($datos)->except(['flotas', 'password'])->all(),
            ...(filled($datos['password'] ?? null) ? ['password' => Hash::make($datos['password'])] : []),
        ]);

        $usuario->flotas()->sync($datos['flotas'] ?? []);

        return back()->with('exito', 'Usuario actualizado.');
    }
}
