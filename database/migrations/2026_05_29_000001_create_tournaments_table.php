<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tournaments', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('slug', 100)->unique();
            $table->string('sport', 50)->default('futbol');
            $table->enum('status', ['draft','open','in_progress','finished','cancelled'])
                  ->default('draft');
            $table->enum('format', ['groups_and_knockout','knockout_only','round_robin'])
                  ->default('groups_and_knockout');
            $table->unsignedTinyInteger('groups_count')->default(1);
            $table->unsignedTinyInteger('teams_per_group')->default(4);
            $table->unsignedTinyInteger('classifies_per_group')->default(2);
            $table->boolean('third_place_match')->default(false);
            $table->json('stats_config')->nullable();
            $table->foreignId('created_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('registration_opens_at')->nullable();
            $table->timestamp('registration_closes_at')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('sport');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tournaments');
    }
};
