<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Flota>
 */
class FlotaFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => fake()->unique()->country(),
            'pais' => fake()->country(),
            'activo' => true,
        ];
    }
}
