<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class InvitationCodeController extends Controller
{
    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // sin 0/O/I/1/L confusos

    public function index(): View
    {
        $codes = DB::table('invitation_codes')
            ->leftJoin('users', 'users.id', '=', 'invitation_codes.used_by_user_id')
            ->orderByDesc('invitation_codes.created_at')
            ->get([
                'invitation_codes.id',
                'invitation_codes.code',
                'invitation_codes.is_used',
                'invitation_codes.is_active',
                'invitation_codes.used_at',
                'invitation_codes.created_at',
                'users.name as used_by_name',
                'users.email as used_by_email',
            ]);

        $availableCount = $codes->where('is_used', false)->where('is_active', true)->count();
        $usedCount = $codes->where('is_used', true)->count();
        $deactivatedCount = $codes->where('is_active', false)->count();

        return view('admin.codes.index', compact('codes', 'availableCount', 'usedCount', 'deactivatedCount'));
    }

    public function generate(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        $now = now();
        $created = 0;
        $maxTries = $data['quantity'] * 20; // colchón para colisiones
        $tries = 0;

        while ($created < $data['quantity'] && $tries < $maxTries) {
            $tries++;
            $code = $this->newCode();
            $inserted = DB::table('invitation_codes')->insertOrIgnore([
                'code' => $code,
                'is_used' => false,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            if ($inserted) {
                $created++;
            }
        }

        return back()->with('status', "Se generaron {$created} código(s) de invitación.");
    }

    public function deactivate(Request $request, int $code): RedirectResponse
    {
        $row = DB::table('invitation_codes')->where('id', $code)->first();

        if (! $row) {
            return back()->withErrors(['code' => 'Código no encontrado.']);
        }

        if ($row->is_used) {
            return back()->withErrors(['code' => 'No se puede desactivar un código ya utilizado.']);
        }

        DB::table('invitation_codes')->where('id', $code)->update([
            'is_active' => false,
            'updated_at' => now(),
        ]);

        return back()->with('status', "Código {$row->code} desactivado.");
    }

    public function export(): Response
    {
        $codes = DB::table('invitation_codes')
            ->where('is_used', false)
            ->where('is_active', true)
            ->orderBy('code')
            ->pluck('code')
            ->all();

        $body = count($codes) === 0
            ? '(No hay códigos disponibles)'
            : implode("\n", $codes);

        return response($body, 200)
            ->header('Content-Type', 'text/plain; charset=UTF-8');
    }

    private function newCode(): string
    {
        $alpha = self::ALPHABET;
        $len = strlen($alpha);
        $suffix = '';
        for ($i = 0; $i < 4; $i++) {
            $suffix .= $alpha[random_int(0, $len - 1)];
        }
        return 'SPM-' . $suffix;
    }
}
