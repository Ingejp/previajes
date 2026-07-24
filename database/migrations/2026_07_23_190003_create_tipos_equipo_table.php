<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RF-03: catálogo administrable de tipos de equipo, que crece sin tocar código.
 * RF-16.1 / RN-12: el umbral de "días sin previaje" vive aquí, por tipo de
 * equipo, no como un valor global.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tipos_equipo', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->unique();
            $table->unsignedSmallInteger('dias_alerta_sin_previaje')->default(2);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tipos_equipo');
    }
};
