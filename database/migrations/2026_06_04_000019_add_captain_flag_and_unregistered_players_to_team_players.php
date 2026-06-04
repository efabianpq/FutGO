<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Unifica la identidad jugador/capitán y habilita jugadores no registrados.
 *
 * DECISIÓN DE MODELO (Sesión A):
 *  - La condición de capitán se deriva POR EQUIPO, nunca por un rol global en users.
 *  - Se conserva teams.captain_user_id como puntero denormalizado (FK a users,
 *    ampliamente usado y O(1) para "quién es el capitán").
 *  - Se agrega team_players.is_captain como marcador autoritativo a nivel de
 *    membresía: el jugador del equipo marcado is_captain=true ES el capitán y
 *    coincide siempre con teams.captain_user_id (se sincronizan en código).
 *
 * JUGADORES NO REGISTRADOS:
 *  - user_id pasa a ser NULLABLE: un jugador real puede existir sin cuenta en la app.
 *  - full_name / document: datos mínimos para identificar a un jugador sin user_id.
 *  - verification_status: 'registrado' (tiene user_id) | 'por_verificar' (sin cuenta).
 *    Cuando el jugador se registre podrá "reclamar" su perfil: se setea user_id y
 *    verification_status pasa a 'registrado'.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('team_players', function (Blueprint $table) {
            // Marcador de capitán a nivel de membresía.
            $table->boolean('is_captain')->default(false)->after('user_id');

            // Datos del jugador no registrado (sin cuenta en la app).
            $table->string('full_name', 120)->nullable()->after('is_captain');
            $table->string('document', 40)->nullable()->after('full_name');
            $table->enum('verification_status', ['registrado', 'por_verificar'])
                  ->default('registrado')
                  ->after('document');
        });

        // user_id pasa a nullable (jugador sin cuenta). Mantiene la FK existente.
        Schema::table('team_players', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->change();
        });

        // Índice para anti-duplicados por documento dentro de un torneo
        // (la consulta filtra por team→tournament, pero el índice acelera el lookup).
        Schema::table('team_players', function (Blueprint $table) {
            $table->index('document');
        });

        // Backfill (portátil MySQL/SQLite): el jugador que coincide con
        // teams.captain_user_id queda marcado is_captain=true.
        foreach (DB::table('teams')->select('id', 'captain_user_id')->cursor() as $team) {
            DB::table('team_players')
                ->where('team_id', $team->id)
                ->where('user_id', $team->captain_user_id)
                ->update(['is_captain' => true]);
        }
    }

    public function down(): void
    {
        Schema::table('team_players', function (Blueprint $table) {
            $table->dropIndex(['document']);
            $table->dropColumn(['is_captain', 'full_name', 'document', 'verification_status']);
        });

        Schema::table('team_players', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
        });
    }
};
