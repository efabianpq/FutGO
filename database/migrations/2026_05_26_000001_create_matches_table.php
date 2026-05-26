<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('matches', function (Blueprint $table) {
            $table->id();
            $table->string('phase', 50);
            $table->string('group_name', 5)->nullable();
            $table->integer('match_number')->unique();
            $table->string('home_team', 60);
            $table->string('away_team', 60);
            $table->string('home_flag', 10)->nullable();
            $table->string('away_flag', 10)->nullable();
            $table->dateTime('match_datetime');
            $table->string('venue', 100)->nullable();
            $table->enum('status', ['upcoming', 'live', 'finished'])->default('upcoming');
            $table->tinyInteger('home_score_official')->unsigned()->nullable();
            $table->tinyInteger('away_score_official')->unsigned()->nullable();
            $table->dateTime('lock_datetime');
            $table->string('api_match_id', 50)->nullable();
            $table->timestamps();

            $table->index('phase');
            $table->index('status');
            $table->index('match_datetime');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('matches');
    }
};
