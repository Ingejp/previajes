<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\TipoEquipo>
 */
class TipoEquipoFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => fake()->unique()->word(),
            'dias_alerta_sin_previaje' => 2,
            'activo' => true,
        ];
    }
}
