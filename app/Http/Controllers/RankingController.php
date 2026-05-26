<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class RankingController extends Controller
{
    public function index(): View
    {
        return view('ranking.index');
    }
}
