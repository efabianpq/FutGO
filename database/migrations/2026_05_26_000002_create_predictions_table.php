<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('predictions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('match_id')->constrained('matches')->cascadeOnDelete();
            $table->tinyInteger('home_score')->unsigned();
            $table->tinyInteger('away_score')->unsigned();
            $table->tinyInteger('points_earned')->unsigned()->nullable();
            $table->boolean('is_locked')->default(false);
            $table->timestamps();

            $table->unique(['user_id', 'match_id']);
            $table->index('match_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('predictions');
    }
};
