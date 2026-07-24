<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RF-17.1: solución INTERINA para no perder el dato de cambios de llanta
 * mientras no exista el módulo de inventario/mantenimiento.
 *
 * Vive fuera del checklist de previaje a propósito, para no mezclar "hallazgo
 * detectado" con "acción de mantenimiento realizada". Cuando se construya el
 * módulo definitivo, estos registros se migran a órdenes de trabajo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registros_llantas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipo_id')->constrained('equipos')->restrictOnDelete();
            $table->foreignId('usuario_id')->constrained('users')->restrictOnDelete();
            $table->unsignedSmallInteger('cantidad');
            $table->string('posicion')->nullable();
            $table->text('observaciones')->nullable();
            $table->timestamps();

            $table->index(['equipo_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registros_llantas');
    }
};
