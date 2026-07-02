<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Centro de Privacidad · Solicitudes de datos personales (habeas data).
 *
 * type=export  → portabilidad: se arma un ZIP/JSON con los datos del usuario.
 * type=delete  → derecho al olvido: confirmación por código + periodo de gracia
 *                (executes_at = now()+30d) antes de la anonimización definitiva.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 20);        // export | delete
            // pending | processing | ready | completed | cancelled
            $table->string('status', 20)->default('pending');
            $table->string('verification_code', 12)->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->string('file_path')->nullable();   // export listo (disco MEDIA_DISK)
            $table->string('requested_ip', 45)->nullable();
            $table->timestamp('executes_at')->nullable();  // fin del periodo de gracia (delete)
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'type', 'status']);
            $table->index(['type', 'status', 'executes_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_requests');
    }
};
