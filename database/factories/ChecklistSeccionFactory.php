<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\ChecklistSeccion>
 */
class ChecklistSeccionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => strtoupper(fake()->unique()->words(2, true)),
            'orden' => 1,
            'activo' => true,
        ];
    }
}
