<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_articles', function (Blueprint $table) {
            $table->id();
            $table->string('title', 200);
            $table->string('slug', 200)->unique();
            $table->text('content');
            $table->enum('category', ['torneos', 'social', 'cuenta', 'tecnico', 'politicas']);
            $table->enum('source', ['manual', 'auto_generado'])->default('manual');
            $table->foreignId('source_ticket_id')->nullable()
                  ->constrained('support_tickets')->nullOnDelete();
            $table->integer('helpful_count')->default(0);
            $table->integer('not_helpful_count')->default(0);
            $table->boolean('is_published')->default(false);
            $table->timestamps();

            $table->index(['category', 'is_published']);
        });

        // El índice FULLTEXT solo existe en MySQL; SQLite (tests) no lo soporta.
        // La búsqueda de la KB usa LIKE como fallback, así que esto es opcional.
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            Schema::table('support_articles', function (Blueprint $table) {
                $table->fullText(['title', 'content']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('support_articles');
    }
};
