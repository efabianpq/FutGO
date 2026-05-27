<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));

        $rows = DB::table('users')
            ->leftJoin('rankings', 'rankings.user_id', '=', 'users.id')
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
                'users.role',
                'users.is_active',
                'users.invitation_code',
                'users.created_at',
                'rankings.total_points',
                'rankings.current_position',
            ]);

        return view('admin.users.index', [
            'rows' => $rows,
            'search' => $search,
        ]);
    }

    public function toggleActive(Request $request, User $user): RedirectResponse
    {
        // Evitar que el admin se desactive a sí mismo
        if ($user->id === $request->user()->id) {
            return back()->withErrors(['user' => 'No podés cambiar tu propio estado.']);
        }

        $user->is_active = ! $user->is_active;
        $user->save();

        $estado = $user->is_active ? 'activado' : 'desactivado';
        return back()->with('status', "Usuario {$user->name} {$estado}.");
    }
}
