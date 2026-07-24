<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RF-04: catálogo de equipos (cabezales, chasis, gensets, etc.).
 *
 * El código/placa es único dentro de su flota, no globalmente: dos flotas de
 * países distintos pueden usar la misma numeración de unidad.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipos', function (Blueprint $table) {
            $table->id();
            $table->string('codigo');
            $table->foreignId('tipo_equipo_id')->constrained('tipos_equipo')->restrictOnDelete();
            $table->string('marca')->nullable();
            $table->string('modelo')->nullable();
            $table->foreignId('flota_id')->constrained('flotas')->restrictOnDelete();
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['flota_id', 'codigo']);
            $table->index(['flota_id', 'activo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipos');
    }
};
