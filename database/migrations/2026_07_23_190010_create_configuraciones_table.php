<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RF-16.1: catálogo genérico clave/valor para parámetros GLOBALES del sistema
 * (ej. tamaño máximo de foto). El umbral de días sin previaje NO vive aquí:
 * es un campo de `tipos_equipo`, porque varía por tipo de equipo (RN-12).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('configuraciones', function (Blueprint $table) {
            $table->id();
            $table->string('clave')->unique();
            $table->string('valor');
            $table->string('descripcion')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('configuraciones');
    }
};
