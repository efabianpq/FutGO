<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('standings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('phase_id')
                  ->constrained('tournament_phases')
                  ->cascadeOnDelete();
            $table->foreignId('group_id')
                  ->nullable()
                  ->constrained('tournament_groups')
                  ->nullOnDelete();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('played')->default(0);
            $table->unsignedSmallInteger('won')->default(0);
            $table->unsignedSmallInteger('drawn')->default(0);
            $table->unsignedSmallInteger('lost')->default(0);
            $table->unsignedSmallInteger('goals_for')->default(0);
            $table->unsignedSmallInteger('goals_against')->default(0);
            $table->smallInteger('goal_difference')->default(0);
            $table->unsignedSmallInteger('points')->default(0);
            $table->unsignedSmallInteger('position')->nullable();
            $table->timestamp('last_calculated_at')->nullable();
            $table->timestamps();

            $table->unique(['phase_id', 'group_id', 'team_id']);
            $table->index('points');
            $table->index('position');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('standings');
    }
};
