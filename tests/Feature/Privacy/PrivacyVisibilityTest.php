<?php

namespace Tests\Feature\Privacy;

use App\Models\Privacy\PrivacySetting;
use App\Models\User;
use App\Services\Privacy\ConsentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrivacyVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private function playerWithSettings(array $settings): User
    {
        $user = User::factory()->create(['name' => 'Publico Test']);
        $user->privacy()->update($settings);

        return $user;
    }

    public function test_perfil_no_publico_devuelve_404_a_invitados(): void
    {
        $user = $this->playerWithSettings(['public_profile' => false]);

        $this->get(route('social.player.show', $user->futgo_id))->assertNotFound();

        // El propio dueño sí puede verlo.
        $this->actingAs($user)->get(route('social.player.show', $user->futgo_id))->assertOk();
    }

    public function test_usuario_no_searchable_no_aparece_en_el_buscador(): void
    {
        $visible = $this->playerWithSettings(['searchable' => true]);
        $hidden  = $this->playerWithSettings(['searchable' => false]);
        $visible->update(['name' => 'Zlatan Buscable']);
        $hidden->update(['name' => 'Zlatan Oculto']);

        $viewer = User::factory()->create();
        $response = $this->actingAs($viewer)->get(route('social.search', ['q' => 'Zlatan']));

        $response->assertOk();
        $response->assertSee('Zlatan Buscable');
        $response->assertDontSee('Zlatan Oculto');
    }

    public function test_baja_de_marketing_por_enlace_firmado(): void
    {
        $user = User::factory()->create();
        // Aceptó marketing.
        app(ConsentService::class)->updateMarketing($user, true, request());

        $url = ConsentService::unsubscribeUrl($user);
        $this->get($url)->assertOk();

        $this->assertFalse(app(ConsentService::class)->hasMarketingConsent($user));
    }

    public function test_enlace_de_baja_sin_firma_es_rechazado(): void
    {
        $user = User::factory()->create();
        $this->get(route('comunicaciones.baja', ['user' => $user->id]))->assertForbidden();
    }
}
