<?php

namespace Database\Factories;

use App\Models\ChecklistSeccion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\ChecklistOpcion>
 */
class ChecklistOpcionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'seccion_id' => ChecklistSeccion::factory(),
            'etiqueta' => fake()->word(),
            'es_optima' => true,
            'orden' => 1,
        ];
    }

    /** Opción que constituye hallazgo (RN-04). */
    public function noOptima(): static
    {
        return $this->state(['es_optima' => false]);
    }
}
