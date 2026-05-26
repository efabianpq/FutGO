<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EliminatorySeeder extends Seeder
{
    public function run(): void
    {
        // [match_number, phase, date, time (Colombia), home, away]
        // Equipos en blanco como plantilla — el admin los actualizará cuando se conozcan los clasificados.
        // Fechas y horas son estimadas según la ventana FIFA 28 jun – 19 jul; el admin las refinará.
        $matches = [
            // -------- Dieciseisavos de Final (Ronda de 32): 16 partidos --------
            [73, 'dieciseisavos', '2026-06-28', '12:00', 'Clasificado A1', 'Clasificado B2'],
            [74, 'dieciseisavos', '2026-06-28', '15:00', 'Clasificado C1', 'Clasificado D2'],
            [75, 'dieciseisavos', '2026-06-28', '18:00', 'Clasificado E1', 'Clasificado F2'],
            [76, 'dieciseisavos', '2026-06-28', '21:00', 'Clasificado G1', 'Clasificado H2'],
            [77, 'dieciseisavos', '2026-06-29', '12:00', 'Clasificado I1', 'Clasificado J2'],
            [78, 'dieciseisavos', '2026-06-29', '15:00', 'Clasificado K1', 'Clasificado L2'],
            [79, 'dieciseisavos', '2026-06-29', '18:00', 'Clasificado B1', 'Clasificado A2'],
            [80, 'dieciseisavos', '2026-06-29', '21:00', 'Clasificado D1', 'Clasificado C2'],
            [81, 'dieciseisavos', '2026-06-30', '12:00', 'Clasificado F1', 'Clasificado E2'],
            [82, 'dieciseisavos', '2026-06-30', '15:00', 'Clasificado H1', 'Clasificado G2'],
            [83, 'dieciseisavos', '2026-06-30', '18:00', 'Clasificado J1', 'Clasificado I2'],
            [84, 'dieciseisavos', '2026-06-30', '21:00', 'Clasificado L1', 'Clasificado K2'],
            [85, 'dieciseisavos', '2026-07-01', '12:00', 'Clasificado A1', 'Mejor 3ro #1'],
            [86, 'dieciseisavos', '2026-07-01', '15:00', 'Clasificado B1', 'Mejor 3ro #2'],
            [87, 'dieciseisavos', '2026-07-01', '18:00', 'Clasificado C1', 'Mejor 3ro #3'],
            [88, 'dieciseisavos', '2026-07-01', '21:00', 'Clasificado D1', 'Mejor 3ro #4'],

            // -------- Octavos de Final (Ronda de 16): 8 partidos --------
            [89, 'octavos', '2026-07-04', '14:00', 'Ganador 16vos #1',  'Ganador 16vos #2'],
            [90, 'octavos', '2026-07-04', '18:00', 'Ganador 16vos #3',  'Ganador 16vos #4'],
            [91, 'octavos', '2026-07-05', '14:00', 'Ganador 16vos #5',  'Ganador 16vos #6'],
            [92, 'octavos', '2026-07-05', '18:00', 'Ganador 16vos #7',  'Ganador 16vos #8'],
            [93, 'octavos', '2026-07-06', '14:00', 'Ganador 16vos #9',  'Ganador 16vos #10'],
            [94, 'octavos', '2026-07-06', '18:00', 'Ganador 16vos #11', 'Ganador 16vos #12'],
            [95, 'octavos', '2026-07-07', '14:00', 'Ganador 16vos #13', 'Ganador 16vos #14'],
            [96, 'octavos', '2026-07-07', '18:00', 'Ganador 16vos #15', 'Ganador 16vos #16'],

            // -------- Cuartos de Final: 4 partidos --------
            [97,  'cuartos', '2026-07-09', '15:00', 'Ganador Octavos #1', 'Ganador Octavos #2'],
            [98,  'cuartos', '2026-07-10', '15:00', 'Ganador Octavos #3', 'Ganador Octavos #4'],
            [99,  'cuartos', '2026-07-11', '15:00', 'Ganador Octavos #5', 'Ganador Octavos #6'],
            [100, 'cuartos', '2026-07-11', '19:00', 'Ganador Octavos #7', 'Ganador Octavos #8'],

            // -------- Semifinales: 2 partidos --------
            [101, 'semifinal', '2026-07-14', '15:00', 'Ganador Cuartos #1', 'Ganador Cuartos #2'],
            [102, 'semifinal', '2026-07-15', '15:00', 'Ganador Cuartos #3', 'Ganador Cuartos #4'],

            // -------- Tercer puesto y Final --------
            [103, '3er_puesto', '2026-07-18', '11:00', 'Perdedor Semifinal #1', 'Perdedor Semifinal #2'],
            [104, 'final',      '2026-07-19', '14:00', 'Ganador Semifinal #1',  'Ganador Semifinal #2'],
        ];

        $now = Carbon::now();

        foreach ($matches as [$num, $phase, $date, $time, $home, $away]) {
            $datetime = Carbon::createFromFormat('Y-m-d H:i', "$date $time", 'America/Bogota');

            DB::table('matches')->updateOrInsert(
                ['match_number' => $num],
                [
                    'phase' => $phase,
                    'group_name' => null,
                    'home_team' => $home,
                    'away_team' => $away,
                    'home_flag' => null,
                    'away_flag' => null,
                    'match_datetime' => $datetime->format('Y-m-d H:i:s'),
                    'lock_datetime' => $datetime->copy()->subMinutes(5)->format('Y-m-d H:i:s'),
                    'venue' => 'Por definir',
                    'status' => 'upcoming',
                    'home_score_official' => null,
                    'away_score_official' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }
}
