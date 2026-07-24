<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RF-02: el módulo soporta múltiples flotas (países) desde el diseño inicial.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flotas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->unique();
            $table->string('pais');
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flotas');
    }
};
