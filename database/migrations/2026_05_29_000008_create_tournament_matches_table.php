<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tournament_matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('phase_id')
                  ->constrained('tournament_phases')
                  ->cascadeOnDelete();
            $table->foreignId('group_id')
                  ->nullable()
                  ->constrained('tournament_groups')
                  ->nullOnDelete();
            $table->foreignId('home_team_id')
                  ->constrained('teams')
                  ->cascadeOnDelete();
            $table->foreignId('away_team_id')
                  ->constrained('teams')
                  ->cascadeOnDelete();
            $table->foreignId('winner_team_id')
                  ->nullable()
                  ->constrained('teams')
                  ->nullOnDelete();
            $table->unsignedTinyInteger('home_score')->nullable();
            $table->unsignedTinyInteger('away_score')->nullable();
            $table->enum('status', ['scheduled','live','finished','postponed'])
                  ->default('scheduled');
            $table->dateTime('scheduled_at')->nullable();
            $table->string('venue', 100)->nullable();
            $table->unsignedSmallInteger('match_number');
            $table->timestamps();

            $table->unique(['phase_id', 'match_number']);
            $table->index('status');
            $table->index('scheduled_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tournament_matches');
    }
};
