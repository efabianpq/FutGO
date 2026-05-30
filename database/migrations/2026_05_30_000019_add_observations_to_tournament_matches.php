<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournament_matches', function (Blueprint $table) {
            // Observaciones de programación/calendario del partido (sede, motivo de
            // postergación, notas logísticas). Distinto de la ficha de resultado.
            $table->text('observations')->nullable()->after('venue');
        });
    }

    public function down(): void
    {
        Schema::table('tournament_matches', function (Blueprint $table) {
            $table->dropColumn('observations');
        });
    }
};
