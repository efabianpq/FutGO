<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Equipo PERMANENTE y transversal (hallazgos post-Sesión B).
 *
 * El equipo (entidad `clubs`) deja de depender de un torneo: tiene su propia
 * plantilla permanente (`club_players`) y su capitán (`clubs.captain_user_id`).
 * Cada `teams` pasa a ser solo la PARTICIPACIÓN del equipo en un torneo, cuya
 * plantilla (`team_players`) es un snapshot copiado al enrolar.
 *
 * BACKFILL (en transacción, sin pérdida):
 *  - clubs.captain_user_id ← capitán del team más reciente del club (o su creador).
 *  - club_players ← unión deduplicada de los team_players de todos los teams del
 *    club (por user_id si registrado; por documento/nombre si invitado).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clubs', function (Blueprint $table) {
            $table->foreignId('captain_user_id')->nullable()->after('created_by_user_id')
                  ->constrained('users')->nullOnDelete();
        });

        Schema::create('club_players', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained('clubs')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_captain')->default(false);
            $table->string('full_name', 120)->nullable();
            $table->string('document', 40)->nullable();
            $table->enum('verification_status', ['registrado', 'por_verificar'])->default('registrado');
            $table->string('jersey_number', 3)->nullable();
            $table->string('position', 30)->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();

            $table->unique(['club_id', 'user_id']);
            $table->index('document');
        });

        DB::transaction(function () {
            foreach (DB::table('clubs')->orderBy('id')->get() as $club) {
                // Equipos (participaciones) del club, más reciente primero.
                $teams = DB::table('teams')->where('club_id', $club->id)->orderByDesc('id')->get();

                // Capitán permanente: el del team más reciente, si no el creador del club.
                $captainId = $teams->first()->captain_user_id ?? $club->created_by_user_id;
                if ($captainId) {
                    DB::table('clubs')->where('id', $club->id)->update(['captain_user_id' => $captainId]);
                }

                $teamIds = $teams->pluck('id')->all();
                if (empty($teamIds)) {
                    continue;
                }

                // Plantilla permanente = unión deduplicada de los team_players.
                $seenUsers = [];
                $seenDocs  = [];
                $rows = DB::table('team_players')->whereIn('team_id', $teamIds)->orderBy('id')->get();

                foreach ($rows as $tp) {
                    // Dedup: por user_id (registrado) o por documento (invitado).
                    if ($tp->user_id) {
                        if (in_array($tp->user_id, $seenUsers, true)) {
                            continue;
                        }
                        $seenUsers[] = $tp->user_id;
                    } elseif (! empty($tp->document)) {
                        $docKey = mb_strtolower($tp->document);
                        if (in_array($docKey, $seenDocs, true)) {
                            continue;
                        }
                        $seenDocs[] = $docKey;
                    }

                    DB::table('club_players')->insert([
                        'club_id'             => $club->id,
                        'user_id'             => $tp->user_id,
                        'is_captain'          => ($tp->user_id && $tp->user_id == $captainId) ? 1 : 0,
                        'full_name'           => $tp->full_name,
                        'document'            => $tp->document,
                        'verification_status' => $tp->verification_status ?? 'registrado',
                        'jersey_number'       => $tp->jersey_number,
                        'position'            => $tp->position,
                        'status'              => in_array($tp->status, ['active', 'inactive'], true) ? $tp->status : 'active',
                        'created_at'          => now(),
                        'updated_at'          => now(),
                    ]);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('club_players');

        Schema::table('clubs', function (Blueprint $table) {
            $table->dropForeign(['captain_user_id']);
            $table->dropColumn('captain_user_id');
        });
    }
};
