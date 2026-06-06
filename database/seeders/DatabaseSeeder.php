<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            MatchSeeder::class,
            EliminatorySeeder::class,
            AdminUserSeeder::class,
            TestUsersSeeder::class,
            InvitationCodeSeeder::class,
            AchievementSeeder::class,
        ]);
    }
}
