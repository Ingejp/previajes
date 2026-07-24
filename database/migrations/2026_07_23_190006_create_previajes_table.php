<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RF-09, RF-10, RN-01: el previaje siempre pertenece a un equipo, un mecánico y
 * una flota, y la fecha/hora la asigna el servidor vía `created_at`.
 *
 * RF-09.1: kilometraje y horómetro se capturan ambos en todo previaje. Quedan
 * nullable sólo para el equipo que físicamente no tenga uno de los dos
 * instrumentos; el formulario siempre muestra los dos campos.
 *
 * RF-12: los previajes no se borran físicamente, se anulan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('previajes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipo_id')->constrained('equipos')->restrictOnDelete();
            $table->foreignId('mecanico_id')->constrained('users')->restrictOnDelete();
            // Redundante respecto de equipos.flota_id, pero evita un join en
            // cada consulta de reportes y dashboard (§9).
            $table->foreignId('flota_id')->constrained('flotas')->restrictOnDelete();
            $table->unsignedBigInteger('kilometraje')->nullable();
            $table->decimal('horometro', 12, 2)->nullable();
            $table->enum('estatus', ['sin_hallazgos', 'con_hallazgos', 'anulado'])->default('sin_hallazgos');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();

            // Anulación (RF-12): se preserva el historial completo.
            $table->timestamp('anulado_en')->nullable();
            $table->foreignId('anulado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->text('motivo_anulacion')->nullable();

            $table->timestamps();

            // RN-03: distinguir siempre el previaje más reciente por equipo.
            $table->index(['equipo_id', 'created_at']);
            $table->index(['flota_id', 'estatus']);
            $table->index(['mecanico_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('previajes');
    }
};
