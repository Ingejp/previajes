<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RF-05, RF-06, RF-07: el checklist completo (secciones, ítems y opciones de
 * respuesta) vive en catálogos administrables, nunca fijo en código. Las tres
 * secciones actuales del formulario de Google se precargan vía seeder.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checklist_secciones', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->unique();
            $table->unsignedSmallInteger('orden')->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        // RN-07: qué checklist aplica a qué tipo de equipo (un genset no lleva llantas).
        Schema::create('tipo_equipo_seccion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tipo_equipo_id')->constrained('tipos_equipo')->cascadeOnDelete();
            $table->foreignId('checklist_seccion_id')->constrained('checklist_secciones')->cascadeOnDelete();

            $table->unique(['tipo_equipo_id', 'checklist_seccion_id'], 'tipo_equipo_seccion_unico');
        });

        Schema::create('checklist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seccion_id')->constrained('checklist_secciones')->cascadeOnDelete();
            $table->string('descripcion');
            // RF-08: al marcarse en nivel bajo, habilita el campo de galones agregados.
            $table->boolean('es_fluido')->default(false);
            $table->unsignedSmallInteger('orden')->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->index(['seccion_id', 'activo', 'orden']);
        });

        // RF-07: cada sección define su propio juego de opciones, y cada opción
        // declara si es "óptima" para poder calcular el estatus del previaje.
        Schema::create('checklist_opciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seccion_id')->constrained('checklist_secciones')->cascadeOnDelete();
            $table->string('etiqueta');
            $table->boolean('es_optima')->default(false);
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();

            $table->index(['seccion_id', 'orden']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checklist_opciones');
        Schema::dropIfExists('checklist_items');
        Schema::dropIfExists('tipo_equipo_seccion');
        Schema::dropIfExists('checklist_secciones');
    }
};
