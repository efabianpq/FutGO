<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('player_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
            $table->foreignId('team_player_id')
                  ->constrained('team_players')
                  ->cascadeOnDelete();
            $table->unsignedSmallInteger('goals')->default(0);
            $table->unsignedSmallInteger('assists')->default(0);
            $table->unsignedSmallInteger('yellow_cards')->default(0);
            $table->unsignedSmallInteger('red_cards')->default(0);
            $table->unsignedSmallInteger('minutes_played')->default(0);
            $table->unsignedSmallInteger('matches_played')->default(0);
            $table->timestamp('last_calculated_at')->nullable();
            $table->timestamps();

            $table->unique(['tournament_id', 'team_player_id']);
            $table->index('goals');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_stats');
    }
};
