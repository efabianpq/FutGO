<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Social\ContentReport;
use App\Models\Torneos\ProfileClaim;
use App\Models\User;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        return view('admin.dashboard', [
            'usersCount'          => User::count(),
            'pendingReports'      => ContentReport::where('status', 'pending')->count(),
            'escalatedClaims'     => ProfileClaim::where('status', 'escalated')->count(),
        ]);
    }
}
