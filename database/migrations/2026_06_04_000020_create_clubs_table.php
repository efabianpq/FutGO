<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Identidad PERMANENTE del equipo (Sesión B).
 *
 * DECISIÓN DE MODELO: enfoque (b) — se introduce la entidad `clubs` (identidad
 * que persiste entre torneos) y se vincula cada `teams` (que pasa a ser "la
 * inscripción de un club en un torneo") mediante `teams.club_id`.
 *
 * Por qué (b) y no (a): mantener `teams` por torneo deja intactas TODAS las FKs
 * que apuntan a teams.id (standings, tournament_matches, group_teams,
 * player_stats, team_players), por lo que la migración no toca datos históricos.
 * El club agrupa las participaciones (Club hasMany Teams) y soporta el historial.
 *
 * El escudo permanente vive en clubs.shield_url. teams.shield_url se conserva
 * como override por torneo (opcional).
 *
 * BACKFILL (en transacción, sin pérdida de datos): por cada equipo existente se
 * crea/reutiliza un club deduplicando por (captain_user_id + nombre normalizado),
 * de modo que el mismo equipo inscripto en varios torneos quede bajo un único club.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clubs', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->string('slug', 120)->unique();
            $table->string('color', 20)->nullable();
            $table->string('shield_url', 255)->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('name');
        });

        Schema::table('teams', function (Blueprint $table) {
            $table->foreignId('club_id')->nullable()->after('tournament_id')
                  ->constrained('clubs')->nullOnDelete();
        });

        // ── Backfill dentro de transacción ───────────────────────────────────
        DB::transaction(function () {
            // mapa: "captainId|nombreNormalizado" => club_id
            $clubsByKey = [];
            $usedSlugs  = [];

            foreach (DB::table('teams')->orderBy('id')->get() as $team) {
                $normName = Str::lower(trim($team->name));
                $key      = $team->captain_user_id . '|' . $normName;

                if (! isset($clubsByKey[$key])) {
                    // Slug único derivado del nombre.
                    $base = Str::slug($team->name) ?: 'club';
                    $slug = $base;
                    $i    = 1;
                    while (in_array($slug, $usedSlugs, true)
                           || DB::table('clubs')->where('slug', $slug)->exists()) {
                        $i++;
                        $slug = $base . '-' . $i;
                    }
                    $usedSlugs[] = $slug;

                    $clubId = DB::table('clubs')->insertGetId([
                        'name'               => $team->name,
                        'slug'               => $slug,
                        'color'              => $team->color,
                        'shield_url'         => $team->shield_url,
                        'created_by_user_id' => $team->captain_user_id,
                        'created_at'         => now(),
                        'updated_at'         => now(),
                    ]);

                    $clubsByKey[$key] = $clubId;
                }

                DB::table('teams')->where('id', $team->id)
                    ->update(['club_id' => $clubsByKey[$key]]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropForeign(['club_id']);
            $table->dropColumn('club_id');
        });

        Schema::dropIfExists('clubs');
    }
};
