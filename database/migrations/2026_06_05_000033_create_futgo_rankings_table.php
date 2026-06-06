<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ranking FUTGO CACHEADO (Sesión F). NO se calcula en cada request: lo reconstruye
 * RankingService al finalizar torneos o vía el comando torneos:rebuild-reputation.
 *
 * subject_type: 'player' (subject_id = users.id) | 'team' (subject_id = clubs.id).
 * scope_type:   'global' | 'city' | 'category'  (scope_value = ciudad/categoría).
 * Una fila por (subject_type, subject_id, scope_type, scope_value).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('futgo_rankings', function (Blueprint $table) {
            $table->id();
            $table->enum('subject_type', ['player', 'team']);
            $table->unsignedBigInteger('subject_id');
            $table->enum('scope_type', ['global', 'city', 'category']);
            $table->string('scope_value', 80)->nullable();   // null para global
            $table->string('display_name', 120)->nullable(); // snapshot del nombre
            $table->integer('score')->default(0);
            $table->unsignedInteger('matches_played')->default(0);
            $table->unsignedInteger('goals')->default(0);
            $table->unsignedInteger('assists')->default(0);
            $table->unsignedInteger('mvps')->default(0);
            $table->unsignedSmallInteger('fair_play')->default(100);
            $table->unsignedInteger('position')->default(0);
            $table->timestamp('calculated_at')->nullable();
            $table->timestamps();

            $table->index(['subject_type', 'scope_type', 'scope_value', 'position'], 'futgo_rankings_lookup_idx');
            $table->unique(['subject_type', 'subject_id', 'scope_type', 'scope_value'], 'futgo_rankings_unique_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('futgo_rankings');
    }
};
