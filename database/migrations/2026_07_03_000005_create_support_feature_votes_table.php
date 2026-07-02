<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_feature_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('feature_request_id')
                  ->constrained('support_feature_requests')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['feature_request_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_feature_votes');
    }
};
