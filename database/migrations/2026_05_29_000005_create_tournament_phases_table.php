<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tournament_phases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
            $table->string('name', 60);
            $table->enum('type', ['groups','knockout','third_place'])->default('groups');
            $table->unsignedTinyInteger('order')->default(1);
            $table->boolean('is_active')->default(false);
            $table->timestamps();

            $table->unique(['tournament_id', 'order']);
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tournament_phases');
    }
};
