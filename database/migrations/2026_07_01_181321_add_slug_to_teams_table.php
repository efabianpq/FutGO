<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Agrega slug único a teams (participación de un club en un torneo) para que
 * las URLs internas de gestión (/torneos/{slug}/..., admin/torneos/.../equipos/{slug})
 * dejen de exponer el id numérico, igual que ya ocurre con tournaments y clubs.
 *
 * BACKFILL: cada equipo existente deriva su slug del nombre (mismo patrón que
 * la migración de clubs), desambiguando con sufijo numérico si dos equipos
 * (normalmente el mismo club en distintos torneos) comparten nombre.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->string('slug', 140)->nullable()->after('name');
        });

        DB::transaction(function () {
            $usedSlugs = [];

            foreach (DB::table('teams')->orderBy('id')->get() as $team) {
                $base = Str::slug($team->name) ?: 'equipo';
                $slug = $base;
                $i = 1;
                while (in_array($slug, $usedSlugs, true)) {
                    $i++;
                    $slug = $base . '-' . $i;
                }
                $usedSlugs[] = $slug;

                DB::table('teams')->where('id', $team->id)->update(['slug' => $slug]);
            }
        });

        Schema::table('teams', function (Blueprint $table) {
            $table->string('slug', 140)->nullable(false)->unique()->change();
        });
    }

    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn('slug');
        });
    }
};
