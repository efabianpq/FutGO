<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_articles', function (Blueprint $table) {
            $table->string('excerpt', 300)->nullable()->after('content');
        });
    }

    public function down(): void
    {
        Schema::table('support_articles', function (Blueprint $table) {
            $table->dropColumn('excerpt');
        });
    }
};
