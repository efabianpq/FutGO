<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FutGO Social — Fase 1 · content_reports.
 *
 * Requerimiento de SEGURIDAD desde el día uno: las oportunidades y mensajes ya
 * son texto libre. Reporte polimórfico de contenido/usuario abusivo
 * (opportunity | message | user | club) con revisión manual de un admin.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_reports', function (Blueprint $table) {
            $table->id();

            // Quién reporta (siempre un usuario registrado).
            $table->foreignId('reporter_user_id')->constrained('users')->cascadeOnDelete();

            // Qué se reporta: 'opportunity' | 'message' | 'user' | 'club' (morph map).
            $table->morphs('reportable');   // reportable_type + reportable_id (+ índice)

            $table->string('reason', 50);
            $table->text('details')->nullable();

            // pendiente | revisado | resuelto
            $table->string('status', 20)->default('pendiente');

            // Resolución del admin.
            $table->text('admin_notes')->nullable();
            $table->foreignId('reviewed_by_user_id')->nullable()
                  ->constrained('users')->nullOnDelete();
            $table->dateTime('reviewed_at')->nullable();

            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_reports');
    }
};
