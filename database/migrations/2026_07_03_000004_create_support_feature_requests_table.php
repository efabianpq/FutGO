<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_feature_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->nullable()
                  ->constrained('support_tickets')->nullOnDelete();
            $table->string('title', 200);
            $table->text('description');
            $table->enum('status', [
                'recibido', 'evaluando', 'planeado',
                'en_desarrollo', 'lanzado', 'descartado',
            ])->default('recibido');
            $table->integer('votes_count')->default(0);
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_feature_requests');
    }
};
