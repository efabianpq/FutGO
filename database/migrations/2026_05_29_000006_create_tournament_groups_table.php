<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tournament_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('phase_id')
                  ->constrained('tournament_phases')
                  ->cascadeOnDelete();
            $table->string('name', 10);
            $table->unsignedTinyInteger('order')->default(1);
            $table->timestamps();

            $table->unique(['phase_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tournament_groups');
    }
};
