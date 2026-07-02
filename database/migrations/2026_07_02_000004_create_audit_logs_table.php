<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Centro de Privacidad · Registro de auditoría (append-only, inmutable).
 *
 * Un registro por acción importante: login, cambio de password/email, aceptación
 * de política, exportación/eliminación de datos, alta/baja de torneo/equipo, etc.
 * NUNCA guarda password/token/document; el email va enmascarado en metadata.
 * Sin updated_at: las filas no se modifican.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 60);
            $table->nullableMorphs('auditable');   // auditable_type (alias morph) + auditable_id
            $table->string('ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['user_id', 'created_at']);
            $table->index('action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
