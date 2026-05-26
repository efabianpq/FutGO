<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class RankingService
{
    /**
     * Crea una fila base de rankings (0/0) si el usuario no la tiene aún.
     */
    public function ensureRankingRow(int $userId): void
    {
        $exists = DB::table('rankings')->where('user_id', $userId)->exists();
        if ($exists) {
            return;
        }

        DB::table('rankings')->insert([
            'user_id' => $userId,
            'total_points' => 0,
            'exact_predictions' => 0,
            'current_position' => null,
            'previous_position' => null,
            'last_calculated_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Recalcula totales y posiciones para TODOS los usuarios activos.
     * - Suma predictions.points_earned (no nulos)
     * - Cuenta cuántos pronósticos quedaron en 5 pts
     * - Asigna current_position con desempate por exact_predictions DESC
     * - Mueve current_position previo a previous_position
     * - Marca last_calculated_at = now()
     *
     * @return array<int, array{user_id:int, name:string, total_points:int, exact_predictions:int, current_position:int}>
     */
    public function recalculateAll(): array
    {
        $now = now();

        // 1) Snapshot de previous_position
        $prev = DB::table('rankings')->pluck('current_position', 'user_id');

        // 2) Asegurar fila para cada usuario activo
        $activeUsers = User::where('is_active', true)->pluck('id', 'id');
        foreach ($activeUsers as $userId) {
            $this->ensureRankingRow($userId);
        }

        // 3) Agregar totales por usuario activo
        $agg = DB::table('users')
            ->leftJoin('predictions', 'predictions.user_id', '=', 'users.id')
            ->where('users.is_active', true)
            ->selectRaw('users.id as user_id, users.name, COALESCE(SUM(predictions.points_earned),0) as total_points, COALESCE(SUM(CASE WHEN predictions.points_earned = 5 THEN 1 ELSE 0 END),0) as exact_predictions')
            ->groupBy('users.id', 'users.name')
            ->get();

        // 4) Ordenar con desempate (total DESC, exactos DESC, id ASC)
        $sorted = $agg->sortBy([
            ['total_points', 'desc'],
            ['exact_predictions', 'desc'],
            ['user_id', 'asc'],
        ])->values();

        // 5) Asignar posiciones (manejando empates compartidos para que la posición refleje el rank)
        $position = 0;
        $skip = 0;
        $lastKey = null;
        $rows = [];
        foreach ($sorted as $idx => $row) {
            $key = [(int) $row->total_points, (int) $row->exact_predictions];
            if ($lastKey !== null && $key === $lastKey) {
                $skip++;
            } else {
                $position = $position + 1 + $skip;
                $skip = 0;
            }
            $lastKey = $key;

            $rows[] = [
                'user_id' => (int) $row->user_id,
                'name' => $row->name,
                'total_points' => (int) $row->total_points,
                'exact_predictions' => (int) $row->exact_predictions,
                'current_position' => $position,
            ];
        }

        // 6) Persistir
        foreach ($rows as $r) {
            DB::table('rankings')->where('user_id', $r['user_id'])->update([
                'total_points' => $r['total_points'],
                'exact_predictions' => $r['exact_predictions'],
                'previous_position' => $prev[$r['user_id']] ?? null,
                'current_position' => $r['current_position'],
                'last_calculated_at' => $now,
                'updated_at' => $now,
            ]);
        }

        return $rows;
    }
}
