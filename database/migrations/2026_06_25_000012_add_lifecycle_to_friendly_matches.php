<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FutGO Social — Fase 1 · Sesión S1-C · ciclo de vida del amistoso.
 *
 * Columnas ADITIVAS sobre friendly_matches (creado en S1-A) para soportar:
 *  - marca de cuándo reportó cada club (UX "tu rival también va a cargarlo" y
 *    la notificación de desacuerdo).
 *  - ESCALAMIENTO de una disputa a un admin de plataforma (el sistema no fuerza
 *    un árbitro externo; cualquiera de los dos puede escalar).
 *  - RESOLUCIÓN por admin (resultado oficial cuando los capitanes no concilian).
 *
 * No toca columnas existentes ni otras tablas. Reversible.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('friendly_matches', function (Blueprint $table) {
            // Cuándo reportó cada lado (null = todavía no reportó).
            $table->dateTime('home_reported_at')->nullable()->after('home_reported_away_score');
            $table->dateTime('away_reported_at')->nullable()->after('away_reported_away_score');

            // Escalamiento de la disputa a un admin de plataforma.
            $table->dateTime('escalated_at')->nullable()->after('result_agreement');
            $table->foreignId('escalated_by_club_id')->nullable()->after('escalated_at')
                  ->constrained('clubs')->nullOnDelete();

            // Resolución por admin (resultado oficial de un partido en disputa).
            $table->foreignId('resolved_by_user_id')->nullable()->after('final_away_score')
                  ->constrained('users')->nullOnDelete();
            $table->dateTime('resolved_at')->nullable()->after('resolved_by_user_id');

            $table->index('escalated_at');
        });
    }

    public function down(): void
    {
        Schema::table('friendly_matches', function (Blueprint $table) {
            $table->dropIndex(['escalated_at']);
            $table->dropConstrainedForeignId('escalated_by_club_id');
            $table->dropConstrainedForeignId('resolved_by_user_id');
            $table->dropColumn(['home_reported_at', 'away_reported_at', 'escalated_at', 'resolved_at']);
        });
    }
};
