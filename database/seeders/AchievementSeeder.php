<?php

namespace Database\Seeders;

use App\Models\Torneos\Achievement;
use Illuminate\Database\Seeder;

/**
 * Catálogo INICIAL de logros (Sesión F). Idempotente (updateOrCreate por code).
 * Agregar logros nuevos = agregar filas aquí (o insertarlas en BD); sin cambios de esquema.
 *
 * metric ∈ {matches_played, goals, assists, mvps, clean_sheets, wins, fair_play}.
 */
class AchievementSeeder extends Seeder
{
    public function run(): void
    {
        $catalog = [
            ['debut',        'Debut',               'Jugaste tu primer partido oficial.',   '🎉', 'matches_played', 1,   null, 10],
            ['veterano_10',  'Veterano',            'Disputaste 10 partidos.',               '🎽', 'matches_played', 10,  null, 20],
            ['veterano_50',  'Curtido en mil batallas', 'Disputaste 50 partidos.',           '🛡️', 'matches_played', 50,  null, 30],
            ['goleador_10',  'Artillero',           'Convertiste 10 goles.',                 '⚽', 'goals',          10,  null, 40],
            ['goleador_50',  'Goleador histórico',  'Convertiste 50 goles.',                 '🔥', 'goals',          50,  null, 50],
            ['goleador_100', 'Leyenda del gol',     'Convertiste 100 goles.',                '👑', 'goals',          100, null, 60],
            ['asistidor_10', 'Asistidor',           'Diste 10 asistencias.',                 '🅰️', 'assists',        10,  null, 70],
            ['figura_5',     'Figura recurrente',   'Fuiste MVP en 5 partidos.',             '⭐', 'mvps',           5,   null, 80],
            ['muro_10',      'Muro infranqueable',  'Mantuviste 10 vallas invictas.',        '🧱', 'clean_sheets',   10,  null, 90],
            ['ganador_25',   'Ganador serial',      'Ganaste 25 partidos.',                  '🏆', 'wins',           25,  null, 100],
            ['juego_limpio', 'Capitán ejemplar',    'Fair play ≥ 90 con al menos 10 partidos.', '🤝', 'fair_play',   90,  10,   110],
        ];

        foreach ($catalog as [$code, $name, $desc, $icon, $metric, $threshold, $minMatches, $order]) {
            Achievement::updateOrCreate(
                ['code' => $code],
                [
                    'name' => $name, 'description' => $desc, 'icon' => $icon,
                    'metric' => $metric, 'threshold' => $threshold,
                    'min_matches' => $minMatches, 'sort_order' => $order, 'is_active' => true,
                ]
            );
        }
    }
}
