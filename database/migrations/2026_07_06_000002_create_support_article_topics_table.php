<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_article_topics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('support_article_id')->constrained()->cascadeOnDelete();
            $table->string('feature_key', 100);
            $table->timestamps();

            $table->unique(['support_article_id', 'feature_key']);
            $table->index('feature_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_article_topics');
    }
};
