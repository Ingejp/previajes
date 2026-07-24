<?php

namespace Database\Factories;

use App\Models\Flota;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Flota>
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
