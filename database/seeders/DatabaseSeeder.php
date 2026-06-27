<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Módulo Polla Mundial (CONGELADO) + datos base de plataforma.
        $this->call([
            MatchSeeder::class,
            EliminatorySeeder::class,
            AdminUserSeeder::class,
            InvitationCodeSeeder::class,
            // Catálogo de logros del módulo Torneos (requerido por el mundo demo).
            AchievementSeeder::class,
        ]);

        // Mundo DEMO completo (Torneos + FutGO Social) — solo en local o si se
        // pide explícitamente con DEMO_DATA=true. Construye una comunidad de
        // fútbol amateur colombiano coherente y cubre todas las funcionalidades.
        if (app()->environment('local') || env('DEMO_DATA', false)) {
            $this->call(\Database\Seeders\Demo\DemoSeeder::class);
        }
    }
}
