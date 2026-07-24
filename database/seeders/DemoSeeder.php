<?php

namespace Database\Seeders;

use App\Enums\RolUsuario;
use App\Models\Equipo;
use App\Models\Flota;
use App\Models\TipoEquipo;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Datos de ejemplo para desarrollo y demostración: un usuario por rol y unos
 * equipos de cada tipo. NO debe correrse en producción — `DatabaseSeeder` lo
 * omite fuera de entornos locales.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $honduras = Flota::where('nombre', 'Honduras')->firstOrFail();
        $guatemala = Flota::where('nombre', 'Guatemala')->firstOrFail();

        $usuarios = [
            ['Mario Mecánico', 'mecanico@previajes.test', RolUsuario::Mecanico, $honduras],
            ['Sonia Supervisora', 'supervisor@previajes.test', RolUsuario::Supervisor, $honduras],
            ['Ana Administradora', 'admin@previajes.test', RolUsuario::Administrador, $honduras],
        ];

        foreach ($usuarios as [$nombre, $email, $rol, $flota]) {
            $usuario = User::updateOrCreate(
                ['email' => $email],
                [
                    'name' => $nombre,
                    'password' => Hash::make('password'),
                    'rol' => $rol,
                    'flota_id' => $flota->id,
                    'activo' => true,
                    'email_verified_at' => now(),
                ],
            );

            // La supervisora cubre una flota adicional a la principal, para
            // ejercitar el filtrado multi-flota de la auditoría (RF-20 /
            // RN-09). No se repite Honduras aquí: ya la cubre `flota_id`, y
            // `flotasAccesibles()` une ambas fuentes.
            if ($rol === RolUsuario::Supervisor) {
                $usuario->flotas()->sync([$guatemala->id]);
            }
        }

        $tipos = TipoEquipo::query()->pluck('id', 'nombre');

        $equipos = [
            ['C-101', 'Cabezal', 'Freightliner', 'Cascadia', $honduras],
            ['C-102', 'Cabezal', 'International', 'LT625', $honduras],
            ['C-103', 'Cabezal', 'Kenworth', 'T680', $guatemala],
            ['CH-201', 'Chasis', 'Wabash', 'DuraPlate', $honduras],
            ['G-301', 'Genset', 'Carrier', 'Vector 6500', $honduras],
            ['RS-401', 'Reach Stacker', 'Kalmar', 'DRG450', $guatemala],
        ];

        foreach ($equipos as [$codigo, $tipo, $marca, $modelo, $flota]) {
            Equipo::updateOrCreate(
                ['flota_id' => $flota->id, 'codigo' => $codigo],
                [
                    'tipo_equipo_id' => $tipos[$tipo],
                    'marca' => $marca,
                    'modelo' => $modelo,
                    'activo' => true,
                ],
            );
        }

        $this->command?->info('Usuarios demo creados con contraseña "password".');
    }
}
