<?php

namespace Database\Factories;

use App\Models\ChecklistItem;
use App\Models\ChecklistSeccion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChecklistItem>
 */
class ChecklistItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'seccion_id' => ChecklistSeccion::factory(),
            'descripcion' => fake()->sentence(3),
            'es_fluido' => false,
            'orden' => 1,
            'activo' => true,
        ];
    }

    /** Ítem que habilita el campo de galones agregados (RF-08). */
    public function fluido(): static
    {
        return $this->state(['es_fluido' => true]);
    }
}
