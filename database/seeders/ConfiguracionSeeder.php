<?php

namespace Database\Seeders;

use App\Models\Configuracion;
use Illuminate\Database\Seeder;

/**
 * RF-16.1: parámetros globales del sistema. El umbral de días sin previaje NO
 * está aquí: vive en `tipos_equipo` porque varía por tipo (RN-12).
 */
class ConfiguracionSeeder extends Seeder
{
    public function run(): void
    {
        $parametros = [
            [
                'clave' => Configuracion::TAMANO_MAXIMO_FOTO_KB,
                'valor' => '400',
                'descripcion' => 'Peso máximo, en KB, al que se comprime cada foto de evidencia (RF-11).',
            ],
            [
                'clave' => Configuracion::ANCHO_MAXIMO_FOTO_PX,
                'valor' => '1600',
                'descripcion' => 'Lado mayor, en píxeles, al que se redimensiona la foto antes de comprimirla.',
            ],
        ];

        foreach ($parametros as $parametro) {
            Configuracion::updateOrCreate(
                ['clave' => $parametro['clave']],
                ['valor' => $parametro['valor'], 'descripcion' => $parametro['descripcion']],
            );
        }
    }
}
