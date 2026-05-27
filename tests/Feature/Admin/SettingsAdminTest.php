<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsAdminTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'is_active' => true]);
    }

    public function test_guarda_pozo_y_aparece_en_ranking(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.settings.update'), [
                'prize_pool' => 1_000_000,
                'tournament_name' => '@SoyPachonMundial',
                'welcome_message' => 'Bienvenidos',
            ])->assertRedirect();

        $this->assertSame(1_000_000, Settings::prizePool());

        $res = $this->get(route('ranking.index'));
        $res->assertSee('600000', false); // 60%
    }

    public function test_pozo_vacio_se_borra(): void
    {
        Settings::setPrizePool(500_000);

        $this->actingAs($this->admin())
            ->post(route('admin.settings.update'), [
                'prize_pool' => null,
                'tournament_name' => 'Test',
                'welcome_message' => '',
            ])->assertRedirect();

        $this->assertNull(Settings::prizePool());
    }

    public function test_guarda_nombre_y_mensaje_y_aparecen_en_welcome(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.settings.update'), [
                'prize_pool' => null,
                'tournament_name' => 'Polla 2026 Test',
                'welcome_message' => 'Mensaje de bienvenida personalizado',
            ])->assertRedirect();

        $this->assertSame('Polla 2026 Test', Settings::tournamentName());
        $this->assertSame('Mensaje de bienvenida personalizado', Settings::welcomeMessage());

        $res = $this->get(route('home'));
        $res->assertSee('Polla 2026 Test');
        $res->assertSee('Mensaje de bienvenida personalizado');
    }
}
