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

        DB::table('users')->updateOrInsert(
            ['email' => 'admin@soypachonmundial.com'],
            [
                'name' => 'Admin SoyPachon',
                'password' => Hash::make('Admin2026!'),
                'role' => 'admin',
                'is_active' => true,
                'notifications_enabled' => true,
                'email_verified_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );
    }
}
