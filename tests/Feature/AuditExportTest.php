<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\Prediction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditExportTest extends TestCase
{
    use RefreshDatabase;

    private function activeUser(): User
    {
        return User::factory()->create(['is_active' => true]);
    }

    private function setupFinishedMatch(): Game
    {
        return Game::create([
            'phase' => 'grupos', 'group_name' => 'A', 'match_number' => 9001,
            'home_team' => 'México', 'away_team' => 'Sudáfrica',
            'home_flag' => '🇲🇽', 'away_flag' => '🇿🇦',
            'match_datetime' => now()->subHours(2),
            'lock_datetime' => now()->subHours(2)->subMinutes(5),
            'venue' => 'Estadio Azteca', 'status' => 'finished',
            'home_score_official' => 2, 'away_score_official' => 1,
        ]);
    }

    public function test_pagina_index_requiere_auth_y_active(): void
    {
        $this->get(route('audit.index'))->assertRedirect(route('login'));

        $inactive = User::factory()->create(['is_active' => false]);
        $this->actingAs($inactive)->get(route('audit.index'))->assertRedirect(route('activate.show'));

        $active = $this->activeUser();
        $this->actingAs($active)->get(route('audit.index'))->assertOk();
    }

    public function test_csv_se_descarga_con_BOM_y_nombre_correcto(): void
    {
        $user = $this->activeUser();
        $game = $this->setupFinishedMatch();
        Prediction::create([
            'user_id' => $user->id, 'match_id' => $game->id,
            'home_score' => 2, 'away_score' => 1, 'points_earned' => 5,
        ]);

        $res = $this->actingAs($user)->get(route('audit.csv'));
        $res->assertOk();
        $res->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $cd = $res->headers->get('content-disposition');
        $this->assertStringContainsString('SoyPachonMundial_Auditoria_', $cd);
        $this->assertStringContainsString('.csv', $cd);

        $content = $res->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $content, 'Debe llevar BOM UTF-8 para Excel');
        // El header tiene las 7 columnas (fputcsv puede o no quotear campos con espacio según versión PHP)
        foreach (['Partido', 'Fase', 'Fecha', 'Resultado oficial', 'Usuario', 'Pronóstico', 'Puntos'] as $col) {
            $this->assertStringContainsString($col, $content, "Falta columna: $col");
        }
        $this->assertStringContainsString('México', $content);
        $this->assertStringContainsString('5', $content);
    }

    public function test_csv_solo_incluye_partidos_finished_con_points_earned(): void
    {
        $user = $this->activeUser();

        $finished = $this->setupFinishedMatch();
        Prediction::create([
            'user_id' => $user->id, 'match_id' => $finished->id,
            'home_score' => 2, 'away_score' => 1, 'points_earned' => 5,
        ]);

        // Partido futuro con pronóstico — NO debe aparecer
        $upcoming = Game::create([
            'phase' => 'grupos', 'group_name' => 'B', 'match_number' => 9002,
            'home_team' => 'SECRETO_HOME', 'away_team' => 'SECRETO_AWAY',
            'match_datetime' => now()->addHours(5),
            'lock_datetime' => now()->addHours(5)->subMinutes(5),
            'status' => 'upcoming',
        ]);
        Prediction::create([
            'user_id' => $user->id, 'match_id' => $upcoming->id,
            'home_score' => 9, 'away_score' => 9,
        ]);

        // Partido finalizado pero sin points_earned (anomalía) — NO debe aparecer
        $finishedSinPts = Game::create([
            'phase' => 'grupos', 'group_name' => 'C', 'match_number' => 9003,
            'home_team' => 'SIN_PUNTOS_HOME', 'away_team' => 'SIN_PUNTOS_AWAY',
            'match_datetime' => now()->subHours(3),
            'lock_datetime' => now()->subHours(3)->subMinutes(5),
            'status' => 'finished',
            'home_score_official' => 1, 'away_score_official' => 0,
        ]);
        Prediction::create([
            'user_id' => $user->id, 'match_id' => $finishedSinPts->id,
            'home_score' => 0, 'away_score' => 0, 'points_earned' => null,
        ]);

        $content = $this->actingAs($user)->get(route('audit.csv'))->streamedContent();

        $this->assertStringContainsString('México', $content);
        $this->assertStringNotContainsString('SECRETO_HOME', $content);
        $this->assertStringNotContainsString('SIN_PUNTOS_HOME', $content);
    }

    public function test_pdf_se_descarga_correctamente(): void
    {
        $user = $this->activeUser();
        $game = $this->setupFinishedMatch();
        Prediction::create([
            'user_id' => $user->id, 'match_id' => $game->id,
            'home_score' => 2, 'away_score' => 1, 'points_earned' => 5,
        ]);

        $res = $this->actingAs($user)->get(route('audit.pdf'));
        $res->assertOk();

        $cd = $res->headers->get('content-disposition');
        $this->assertStringContainsString('SoyPachonMundial_Auditoria_', $cd);
        $this->assertStringContainsString('.pdf', $cd);

        // PDF binary check: empieza con %PDF-
        $this->assertStringStartsWith('%PDF-', $res->getContent());
    }

    public function test_admin_tiene_su_propia_ruta(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $this->actingAs($admin)->get(route('admin.audit.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.audit.csv'))->assertOk();
    }
}
