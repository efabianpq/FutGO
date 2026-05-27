<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Support\Settings;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $activeParticipants = DB::table('users')
            ->where('role', 'user')
            ->where('is_active', true)
            ->count();

        $pendingParticipants = DB::table('users')
            ->where('role', 'user')
            ->where('is_active', false)
            ->count();

        $finishedMatches = Game::where('status', 'finished')->count();
        $totalMatches = Game::count();

        $top3 = DB::table('rankings')
            ->join('users', 'users.id', '=', 'rankings.user_id')
            ->where('users.is_active', true)
            ->orderBy('rankings.current_position')
            ->limit(3)
            ->get(['users.name', 'rankings.total_points', 'rankings.exact_predictions', 'rankings.current_position']);

        $lastCalculated = Game::where('status', 'finished')
            ->orderByDesc('updated_at')
            ->limit(5)
            ->get(['id', 'match_number', 'home_team', 'away_team', 'home_score_official', 'away_score_official', 'updated_at']);

        return view('admin.dashboard', [
            'activeParticipants' => $activeParticipants,
            'pendingParticipants' => $pendingParticipants,
            'finishedMatches' => $finishedMatches,
            'totalMatches' => $totalMatches,
            'prizes' => Settings::prizeBreakdown(),
            'top3' => $top3,
            'lastCalculated' => $lastCalculated,
        ]);
    }
}
