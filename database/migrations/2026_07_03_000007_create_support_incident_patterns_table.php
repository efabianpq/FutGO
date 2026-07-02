<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_incident_patterns', function (Blueprint $table) {
            $table->id();
            $table->string('pattern_key', 100)->index();
            $table->integer('tickets_count')->default(1);
            $table->timestamp('first_detected_at');
            $table->timestamp('team_alerted_at')->nullable();
            $table->boolean('resolved')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_incident_patterns');
    }
};
