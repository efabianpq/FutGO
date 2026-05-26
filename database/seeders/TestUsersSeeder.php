<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class TestUsersSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();
        $password = Hash::make('Test2026!');

        for ($i = 1; $i <= 5; $i++) {
            DB::table('users')->updateOrInsert(
                ['email' => "user{$i}@test.com"],
                [
                    'name' => "Usuario Prueba {$i}",
                    'password' => $password,
                    'role' => 'user',
                    'is_active' => true,
                    'notifications_enabled' => true,
                    'email_verified_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }
}
