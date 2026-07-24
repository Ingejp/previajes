<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Detalle del previaje. Hay dos niveles de observaciones que son
 * complementarios, no sustitutos (§9):
 *
 *  - `previaje_seccion_observaciones`: la observación general y obligatoria de
 *    cada sección, como en el formulario original ("Observaciones motor").
 *  - `previaje_respuestas.observaciones`: el detalle específico de cada ítem
 *    (RF-09.2 / RN-10), obligatorio sólo cuando la respuesta no es óptima.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('previaje_seccion_observaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('previaje_id')->constrained('previajes')->cascadeOnDelete();
            $table->foreignId('checklist_seccion_id')->constrained('checklist_secciones')->restrictOnDelete();
            $table->text('observaciones');
            $table->timestamps();

            $table->unique(['previaje_id', 'checklist_seccion_id'], 'previaje_seccion_unico');
        });

        Schema::create('previaje_respuestas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('previaje_id')->constrained('previajes')->cascadeOnDelete();
            $table->foreignId('checklist_item_id')->constrained('checklist_items')->restrictOnDelete();
            $table->foreignId('checklist_opcion_id')->constrained('checklist_opciones')->restrictOnDelete();
            // RF-08 / RN-06: galones agregados cuando un ítem de fluido va en nivel bajo.
            $table->decimal('cantidad_agregada', 10, 2)->nullable();
            $table->text('observaciones')->nullable();
            $table->timestamps();

            $table->unique(['previaje_id', 'checklist_item_id'], 'previaje_item_unico');
        });

        // RF-11 / RN-11: varias fotos por ítem, mínimo una cuando hay hallazgo.
        Schema::create('previaje_fotos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('previaje_id')->constrained('previajes')->cascadeOnDelete();
            // Nullable: la foto puede ser del previaje en general, no de un ítem.
            $table->foreignId('checklist_item_id')->nullable()->constrained('checklist_items')->nullOnDelete();
            $table->string('ruta_archivo');
            $table->unsignedInteger('tamano_kb')->nullable();
            // La compresión corre en un Job en cola (RNF-02); hasta que termina,
            // la foto queda marcada como no procesada.
            $table->boolean('procesada')->default(false);
            $table->timestamps();

            $table->index(['previaje_id', 'checklist_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('previaje_fotos');
        Schema::dropIfExists('previaje_respuestas');
        Schema::dropIfExists('previaje_seccion_observaciones');
    }
};
