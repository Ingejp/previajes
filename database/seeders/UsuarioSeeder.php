<?php

namespace Database\Seeders;

use App\Enums\RolUsuario;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Crea el único usuario imprescindible para arrancar: el super administrador,
 * que después da de alta al resto desde el CRUD de usuarios (RF-18).
 *
 * Las credenciales salen de variables de entorno para no dejar un secreto en
 * el repositorio (§7, fallas criptográficas).
 */
class UsuarioSeeder extends Seeder
{
    public function run(): void
    {
        $email = config('previajes.super_admin.email');

        User::updateOrCreate(
            ['email' => $email],
            [
                'name' => config('previajes.super_admin.nombre'),
                'password' => Hash::make(config('previajes.super_admin.password')),
                'rol' => RolUsuario::SuperAdministrador,
                'flota_id' => null, // opera a nivel global (RF-01)
                'activo' => true,
                'email_verified_at' => now(),
            ],
        );

        $this->command?->warn("Super administrador: {$email} — cambie la contraseña tras el primer ingreso.");
    }
}
