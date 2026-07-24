<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Catálogos que el sistema necesita para arrancar (§12). Todos son
     * idempotentes: volver a correrlos actualiza, no duplica.
     *
     * Los datos de demostración quedan fuera de producción a propósito.
     */
    public function run(): void
    {
        $this->call([
            FlotaSeeder::class,
            ChecklistSeeder::class,
            TipoEquipoSeeder::class,
            ConfiguracionSeeder::class,
            UsuarioSeeder::class,
        ]);

        if (! app()->environment('production')) {
            $this->call(DemoSeeder::class);
        }
    }
}
