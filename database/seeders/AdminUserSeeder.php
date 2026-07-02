<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();
        $email = env('ADMIN_EMAIL', 'admin@futgo.co');

        // firstOrCreate (no updateOrInsert): si el admin ya existe, un re-seed
        // (p.ej. durante un deploy) no debe pisar una contraseña ya cambiada.
        if (DB::table('users')->where('email', $email)->exists()) {
            return;
        }

        DB::table('users')->insert([
            'email' => $email,
            'name' => 'Admin FutGO',
            'password' => Hash::make(env('ADMIN_PASSWORD', 'Admin2026!')),
            'role' => 'admin',
            'notifications_enabled' => true,
            'email_verified_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
