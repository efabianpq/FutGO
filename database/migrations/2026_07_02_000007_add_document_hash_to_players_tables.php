<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Centro de Privacidad · Blind index para document en plantillas.
 *
 * `club_players.document` y `team_players.document` pasan a cifrarse; se agrega
 * document_hash (HMAC) para que la detección de perfiles reclamables
 * (ProfileClaimService) y el dedupe sigan funcionando con WHERE sobre el hash.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_players', function (Blueprint $table) {
            $table->string('document_hash', 64)->nullable()->after('document');
            $table->index('document_hash');
        });

        Schema::table('team_players', function (Blueprint $table) {
            $table->string('document_hash', 64)->nullable()->after('document');
            $table->index('document_hash');
        });
    }

    public function down(): void
    {
        Schema::table('club_players', function (Blueprint $table) {
            $table->dropIndex(['document_hash']);
            $table->dropColumn('document_hash');
        });

        Schema::table('team_players', function (Blueprint $table) {
            $table->dropIndex(['document_hash']);
            $table->dropColumn('document_hash');
        });
    }
};
