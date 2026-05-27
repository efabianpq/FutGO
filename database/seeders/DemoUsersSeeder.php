<?php

namespace Database\Seeders;

use App\Services\RankingService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DemoUsersSeeder extends Seeder
{
    public const USERS = [
        ['name' => 'Carlos Mendoza',     'email' => 'carlos@demo.com',    'phone' => '3001000001'],
        ['name' => 'María González',     'email' => 'maria@demo.com',     'phone' => '3001000002'],
        ['name' => 'Andrés Pérez',       'email' => 'andres@demo.com',    'phone' => '3001000003'],
        ['name' => 'Laura Ramírez',      'email' => 'laura@demo.com',     'phone' => '3001000004'],
        ['name' => 'Juan Rodríguez',     'email' => 'juan@demo.com',      'phone' => '3001000005'],
        ['name' => 'Sofía Castro',       'email' => 'sofia@demo.com',     'phone' => '3001000006'],
        ['name' => 'Diego Morales',      'email' => 'diego@demo.com',     'phone' => '3001000007'],
        ['name' => 'Valentina Torres',   'email' => 'valentina@demo.com', 'phone' => '3001000008'],
        ['name' => 'Sebastián Vargas',   'email' => 'sebastian@demo.com', 'phone' => '3001000009'],
        ['name' => 'Camila Jiménez',     'email' => 'camila@demo.com',    'phone' => '3001000010'],
    ];

    public function run(): void
    {
        $now = Carbon::now();
        $password = Hash::make('Demo2026!');
        $ranking = app(RankingService::class);

        // Ocultar usuarios de prueba del paso 3 para no contaminar el ranking del demo
        DB::table('users')->whereIn('email', [
            'user1@test.com', 'user2@test.com', 'user3@test.com',
            'user4@test.com', 'user5@test.com',
        ])->update(['is_active' => false, 'updated_at' => $now]);

        foreach (self::USERS as $i => $u) {
            $code = sprintf('SPM-DEMO%02d', $i + 1);

            DB::table('users')->updateOrInsert(
                ['email' => $u['email']],
                [
                    'name' => $u['name'],
                    'phone_whatsapp' => $u['phone'],
                    'password' => $password,
                    'role' => 'user',
                    'is_active' => true,
                    'notifications_enabled' => true,
                    'email_verified_at' => $now,
                    'invitation_code' => $code,
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );

            $userId = (int) DB::table('users')->where('email', $u['email'])->value('id');

            DB::table('invitation_codes')->updateOrInsert(
                ['code' => $code],
                [
                    'is_used' => true,
                    'used_by_user_id' => $userId,
                    'used_at' => $now,
                    'is_active' => true,
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );

            $ranking->ensureRankingRow($userId);
        }
    }
}
