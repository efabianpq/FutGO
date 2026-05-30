<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Observaciones arbitrales del acta (incidencias del partido reportadas por el
 * árbitro). Distinto de `observations` (notas de programación/calendario).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournament_matches', function (Blueprint $table) {
            $table->text('referee_notes')->nullable()->after('coordinator');
        });
    }

    public function down(): void
    {
        Schema::table('tournament_matches', function (Blueprint $table) {
            $table->dropColumn('referee_notes');
        });
    }
};
