<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RF-20 y OWASP "fallas de registro y monitoreo": bitácora de accesos que
 * alimenta la pantalla de auditoría junto con activity_log.
 *
 * `usuario_id` es nullable y `email_intentado` se guarda siempre, para poder
 * ver intentos fallidos contra correos que ni siquiera existen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accesos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('email_intentado')->nullable();
            $table->enum('evento', ['login', 'logout', 'fallido'])->default('login');
            $table->boolean('exitoso')->default(true);
            $table->string('ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['usuario_id', 'created_at']);
            $table->index(['exitoso', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accesos');
    }
};
