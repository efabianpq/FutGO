<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));

        $rows = DB::table('users')
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($w) use ($search) {
                    $w->where('users.name', 'like', "%{$search}%")
                      ->orWhere('users.email', 'like', "%{$search}%");
                });
            })
            ->orderBy('users.id')
            ->get([
                'users.id',
                'users.name',
                'users.email',
                'users.phone_whatsapp',
                'users.role',
                'users.created_at',
            ]);

        return view('admin.users.index', [
            'rows' => $rows,
            'search' => $search,
        ]);
    }
}
