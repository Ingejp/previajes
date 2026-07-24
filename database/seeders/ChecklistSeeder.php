<?php

namespace Database\Seeders;

use App\Models\ChecklistItem;
use App\Models\ChecklistOpcion;
use App\Models\ChecklistSeccion;
use Illuminate\Database\Seeder;

/**
 * Anexo A: el checklist vigente del Google Form "PRE VIAJE DE CABEZALES FLOTA
 * HONDURAS", precargado para no perder continuidad con el proceso actual
 * (RF-05, RF-06, RF-07).
 *
 * A partir de aquí el catálogo se administra desde la UI (RF-18); este seeder
 * sólo define el punto de partida y es idempotente, así que puede volver a
 * correrse sin duplicar.
 */
class ChecklistSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->secciones() as $orden => $datos) {
            $seccion = ChecklistSeccion::updateOrCreate(
                ['nombre' => $datos['nombre']],
                ['orden' => $orden + 1, 'activo' => true],
            );

            foreach ($datos['opciones'] as $ordenOpcion => [$etiqueta, $esOptima]) {
                ChecklistOpcion::updateOrCreate(
                    ['seccion_id' => $seccion->id, 'etiqueta' => $etiqueta],
                    ['es_optima' => $esOptima, 'orden' => $ordenOpcion + 1],
                );
            }

            foreach ($datos['items'] as $ordenItem => [$descripcion, $esFluido]) {
                ChecklistItem::updateOrCreate(
                    ['seccion_id' => $seccion->id, 'descripcion' => $descripcion],
                    ['es_fluido' => $esFluido, 'orden' => $ordenItem + 1, 'activo' => true],
                );
            }
        }
    }

    /**
     * Cada opción declara si es "óptima" (sin hallazgo). Eso es lo que permite
     * calcular el estatus del previaje sin hardcodear etiquetas (RN-04).
     *
     * Los ítems marcados como fluido habilitan el campo de galones agregados
     * cuando la respuesta no es óptima (RF-08 / RN-06).
     *
     * @return array<int, array{nombre: string, opciones: array<int, array{0: string, 1: bool}>, items: array<int, array{0: string, 1: bool}>}>
     */
    private function secciones(): array
    {
        return [
            [
                'nombre' => 'MOTOR',
                'opciones' => [
                    ['Nivel Óptimo', true],
                    ['Nivel bajo', false],
                    // "Nivelación GLNS" implica que el nivel estaba bajo y hubo
                    // que nivelarlo, así que cuenta como hallazgo. Es editable
                    // desde el CRUD de opciones si negocio decide lo contrario.
                    ['Nivelación GLNS', false],
                ],
                'items' => [
                    ['Nivel aceite motor', true],
                    ['Nivel aceite de dirección', true],
                    ['Nivel de refrigerante, revisión de radiador (fugas)', true],
                    ['Nivel de líquido de frenos', true],
                    ['Estado de mangueras / Estado y tensión de fajas', false],
                    ['Ajuste y sujetadores de capó de motor', false],
                    ['Limpieza de filtros de aire', false],
                ],
            ],
            [
                'nombre' => 'CHASIS',
                'opciones' => [
                    ['Nivel Óptimo', true],
                    ['Nivel Bajo', false],
                ],
                'items' => [
                    ['Drenar humedad de tanques de aire', false],
                    ['Presión de llantas (145 psi) - Calibrar', false],
                    ['Torquear pernos y tuercas de llantas, revisión de válvulas y estado de llantas (desgaste)', false],
                    ['Sistema de frenos, ratch, membranas y fricciones, mangueras de aire', false],
                ],
            ],
            [
                'nombre' => 'CABINA Y ACCESORIOS',
                'opciones' => [
                    ['SI', true],
                    ['NO', false],
                ],
                'items' => [
                    ['Nivel de diésel, en pulgadas', true],
                    ['Candado de seguridad', false],
                    ['Tapón del tanque', false],
                ],
            ],
        ];
    }
}
