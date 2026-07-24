<?php

namespace Database\Seeders;

use App\Models\ChecklistSeccion;
use App\Models\TipoEquipo;
use Illuminate\Database\Seeder;

/**
 * RF-03 y RN-07: tipos de equipo y qué secciones del checklist les aplican.
 *
 * `dias_alerta_sin_previaje` (RF-16.1 / RN-12) queda con valores de arranque
 * razonables por tipo; son editables desde el CRUD de tipos de equipo y deben
 * confirmarse con negocio.
 */
class TipoEquipoSeeder extends Seeder
{
    public function run(): void
    {
        $secciones = ChecklistSeccion::query()->pluck('id', 'nombre');

        foreach ($this->tipos() as $nombre => $config) {
            $tipo = TipoEquipo::updateOrCreate(
                ['nombre' => $nombre],
                ['dias_alerta_sin_previaje' => $config['dias_alerta'], 'activo' => true],
            );

            $tipo->secciones()->sync(
                collect($config['secciones'])
                    ->map(fn (string $seccion) => $secciones[$seccion])
                    ->all(),
            );
        }
    }

    /**
     * El cabezal arranca con las tres secciones del formulario actual. Los
     * demás tipos llevan sólo lo que de verdad se les revisa: un genset no
     * tiene llantas ni cabina, así que no hereda esas secciones (RF-03).
     *
     * @return array<string, array{dias_alerta: int, secciones: array<int, string>}>
     */
    private function tipos(): array
    {
        return [
            'Cabezal' => [
                'dias_alerta' => 2,
                'secciones' => ['MOTOR', 'CHASIS', 'CABINA Y ACCESORIOS'],
            ],
            'Chasis' => [
                'dias_alerta' => 7,
                'secciones' => ['CHASIS'],
            ],
            'Genset' => [
                'dias_alerta' => 7,
                'secciones' => ['MOTOR'],
            ],
            'Reach Stacker' => [
                'dias_alerta' => 3,
                'secciones' => ['MOTOR', 'CHASIS', 'CABINA Y ACCESORIOS'],
            ],
            'Top Loader' => [
                'dias_alerta' => 3,
                'secciones' => ['MOTOR', 'CHASIS', 'CABINA Y ACCESORIOS'],
            ],
        ];
    }
}
