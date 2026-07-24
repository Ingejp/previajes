<?php

namespace Database\Factories;

use App\Models\Flota;
use App\Models\TipoEquipo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Equipo>
 */
class EquipoFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'codigo' => strtoupper(fake()->unique()->bothify('??-###')),
            'tipo_equipo_id' => TipoEquipo::factory(),
            'marca' => fake()->company(),
            'modelo' => fake()->word(),
            'flota_id' => Flota::factory(),
            'activo' => true,
        ];
    }
}
