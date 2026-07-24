<?php

namespace Database\Seeders;

use App\Models\Flota;
use Illuminate\Database\Seeder;

/** RF-02: el módulo nace multi-flota; Honduras es la flota del proceso actual. */
class FlotaSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([['Honduras', 'Honduras'], ['Guatemala', 'Guatemala']] as [$nombre, $pais]) {
            Flota::updateOrCreate(['nombre' => $nombre], ['pais' => $pais, 'activo' => true]);
        }
    }
}
