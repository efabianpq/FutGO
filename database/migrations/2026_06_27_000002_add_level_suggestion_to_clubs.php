<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * S3-A · Sugerencia de recategorización de nivel: cuando un club gana
 * consistentemente contra clubs de nivel superior, el sistema le sugiere al
 * capitán subir su nivel declarado. Es solo un AVISO (no fuerza nada): esta
 * columna registra que el capitán decidió ignorarlo, para no volver a mostrarlo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clubs', function (Blueprint $table) {
            $table->timestamp('level_suggestion_dismissed_at')->nullable()->after('play_level');
        });
    }

    public function down(): void
    {
        Schema::table('clubs', function (Blueprint $table) {
            $table->dropColumn('level_suggestion_dismissed_at');
        });
    }
};
