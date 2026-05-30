<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('player_stats', function (Blueprint $table) {
            $table->unsignedSmallInteger('wins')->default(0)->after('matches_played');
            $table->unsignedSmallInteger('draws')->default(0)->after('wins');
            $table->unsignedSmallInteger('losses')->default(0)->after('draws');
            $table->unsignedSmallInteger('clean_sheets')->default(0)->after('losses');
        });
    }

    public function down(): void
    {
        Schema::table('player_stats', function (Blueprint $table) {
            $table->dropColumn(['wins', 'draws', 'losses', 'clean_sheets']);
        });
    }
};
