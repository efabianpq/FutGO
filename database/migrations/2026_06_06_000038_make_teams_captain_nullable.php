<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * H7: un equipo "por_validar" (creado por el admin del torneo) todavía no tiene
 * capitán, por lo que teams.captain_user_id pasa a ser NULLABLE.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->foreignId('captain_user_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->foreignId('captain_user_id')->nullable(false)->change();
        });
    }
};
