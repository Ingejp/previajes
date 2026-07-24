<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RF-01: cada usuario tiene un rol y puede estar asociado a una o más flotas.
 *
 * `flota_id` es la flota principal (la que se usa por defecto al crear un
 * previaje). La tabla pivote `flota_user` cubre el caso de un supervisor que
 * cubre varias flotas, previsto en el modelo de datos (§9) y exigido por
 * RF-20 / RN-09 ("su(s) flota(s)"). El super administrador opera a nivel
 * global y no requiere asignación.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('rol', ['mecanico', 'supervisor', 'administrador', 'super_administrador'])
                ->default('mecanico')
                ->after('password');
            $table->foreignId('flota_id')->nullable()->after('rol')->constrained('flotas')->nullOnDelete();
            $table->boolean('activo')->default(true)->after('flota_id');
        });

        Schema::create('flota_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('flota_id')->constrained('flotas')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unique(['flota_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flota_user');

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['flota_id']);
            $table->dropColumn(['rol', 'flota_id', 'activo']);
        });
    }
};
