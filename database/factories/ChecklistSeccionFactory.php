<?php

namespace Database\Factories;

use App\Models\ChecklistSeccion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChecklistSeccion>
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
