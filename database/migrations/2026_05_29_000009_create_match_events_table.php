<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('match_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')
                  ->constrained('tournament_matches')
                  ->cascadeOnDelete();
            $table->foreignId('team_player_id')
                  ->constrained('team_players')
                  ->cascadeOnDelete();
            $table->enum('type', [
                'goal','own_goal','assist',
                'yellow_card','red_card',
                'substitution_in','substitution_out'
            ]);
            $table->unsignedTinyInteger('minute')->nullable();
            $table->string('notes', 200)->nullable();
            $table->timestamps();

            $table->index(['match_id', 'type']);
            $table->index('team_player_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('match_events');
    }
};
