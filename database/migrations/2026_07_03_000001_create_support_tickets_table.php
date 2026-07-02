<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('category', [
                'bug', 'duda', 'disputa', 'sugerencia',
                'funcionalidad', 'reclamo', 'abuso',
                'cuenta', 'verificacion', 'otro',
            ])->default('otro');
            $table->enum('status', [
                'abierto', 'en_diagnostico', 'esperando_usuario',
                'en_revision', 'resuelto', 'cerrado', 'reabierto',
            ])->default('abierto');
            $table->enum('priority', ['critica', 'alta', 'media', 'baja'])->default('media');
            $table->decimal('classifier_confidence', 3, 2)->default(0.00);
            $table->string('subject', 200);
            $table->json('context_snapshot')->nullable();
            $table->json('error_trace')->nullable();
            $table->json('audit_timeline')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->text('resolution_notes')->nullable();
            $table->enum('satisfaction_response', ['positiva', 'negativa'])->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('satisfaction_sent_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['status', 'priority']);
            $table->index('category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_tickets');
    }
};
