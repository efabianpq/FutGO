<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('match_lineups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')
                  ->constrained('tournament_matches')
                  ->cascadeOnDelete();
            $table->foreignId('team_player_id')
                  ->constrained('team_players')
                  ->cascadeOnDelete();
            $table->foreignId('team_id')
                  ->constrained('teams')
                  ->cascadeOnDelete();
            $table->boolean('started')->default(true);
            $table->unsignedTinyInteger('minute_in')->default(0);
            $table->unsignedTinyInteger('minute_out')->nullable();
            $table->timestamps();

            $table->unique(['match_id', 'team_player_id']);
            $table->index(['match_id', 'team_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('match_lineups');
    }
};
