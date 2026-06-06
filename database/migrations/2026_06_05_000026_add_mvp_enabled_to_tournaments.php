<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Configuración por torneo: ¿se usa la metodología de MVP (figura del partido)?
 * Algunos torneos no la utilizan, por eso es opcional (default false).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->boolean('mvp_enabled')->default(false)->after('max_substitutions');
        });
    }

    public function down(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropColumn('mvp_enabled');
        });
    }
};
