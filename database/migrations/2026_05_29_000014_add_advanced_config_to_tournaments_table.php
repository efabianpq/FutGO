<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            // Visibilidad y categoría
            $table->enum('visibility', ['public', 'private'])->default('public')->after('sport');
            $table->enum('category', ['libre', 'veteranos', 'sub15', 'sub17', 'sub20', 'femenino', 'mixto'])
                  ->default('libre')->after('visibility');
            $table->string('city', 80)->nullable()->after('category');
            $table->string('venue', 100)->nullable()->after('city');

            // Equipos
            $table->unsignedTinyInteger('max_teams')->default(8)->after('classifies_per_group');

            // Sistema de puntuación
            $table->unsignedTinyInteger('points_win')->default(3)->after('max_teams');
            $table->unsignedTinyInteger('points_draw')->default(1)->after('points_win');
            $table->unsignedTinyInteger('points_loss')->default(0)->after('points_draw');

            // Criterios de desempate
            $table->json('tiebreaker_order')->nullable()->after('points_loss');
            $table->enum('knockout_tiebreak', ['penalties', 'extra_time_penalties', 'replay'])
                  ->default('penalties')->after('tiebreaker_order');

            // Configuración deportiva
            $table->unsignedTinyInteger('min_players_per_team')->default(7)->after('knockout_tiebreak');
            $table->unsignedTinyInteger('max_players_per_team')->default(25)->after('min_players_per_team');
            $table->unsignedTinyInteger('match_duration')->default(90)->after('max_players_per_team');
            $table->unsignedTinyInteger('max_substitutions')->default(5)->after('match_duration');

            // Información adicional (futuro)
            $table->unsignedInteger('registration_fee')->default(0)->after('max_substitutions');
            $table->string('prize_description', 200)->nullable()->after('registration_fee');
            $table->text('rules')->nullable()->after('prize_description');
            $table->string('logo_url', 255)->nullable()->after('rules');
            $table->string('banner_url', 255)->nullable()->after('logo_url');
        });
    }

    public function down(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropColumn([
                'visibility', 'category', 'city', 'venue',
                'max_teams',
                'points_win', 'points_draw', 'points_loss',
                'tiebreaker_order', 'knockout_tiebreak',
                'min_players_per_team', 'max_players_per_team', 'match_duration', 'max_substitutions',
                'registration_fee', 'prize_description', 'rules', 'logo_url', 'banner_url',
            ]);
        });
    }
};
