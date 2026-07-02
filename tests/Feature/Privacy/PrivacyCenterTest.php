<?php

namespace Tests\Feature\Privacy;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrivacyCenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_las_secciones_del_centro_cargan(): void
    {
        $user = User::factory()->create();

        foreach (['privacidad.centro', 'privacidad.configuracion', 'privacidad.consentimientos', 'privacidad.sesiones', 'privacidad.actividad'] as $route) {
            $this->actingAs($user)->get(route($route))->assertOk();
        }
    }

    public function test_invitado_no_accede_al_centro(): void
    {
        $this->get(route('privacidad.centro'))->assertRedirect(route('login'));
    }

    public function test_actualiza_la_configuracion_de_privacidad(): void
    {
        $user = User::factory()->create();
        // Defaults: show_email=false, searchable=true.

        $this->actingAs($user)->patch(route('privacidad.configuracion.update'), [
            'show_email'     => '1',
            'show_stats'     => '1',
            'allow_messages' => 'nadie',
            // searchable ausente → debe quedar false
        ])->assertRedirect();

        $settings = $user->privacy()->fresh();
        $this->assertTrue($settings->show_email);
        $this->assertFalse($settings->searchable);
        $this->assertSame('nadie', $settings->allow_messages);
    }

    public function test_toggle_de_marketing_registra_consentimiento(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->patch(route('privacidad.consentimientos.marketing'), ['marketing' => '1'])
            ->assertRedirect();
        $this->assertDatabaseHas('user_consents', [
            'user_id' => $user->id, 'document_type' => 'marketing', 'accepted' => true, 'source' => 'settings',
        ]);

        $this->actingAs($user)->patch(route('privacidad.consentimientos.marketing'), [])
            ->assertRedirect();
        $this->assertDatabaseHas('user_consents', [
            'user_id' => $user->id, 'document_type' => 'marketing', 'accepted' => false,
        ]);
    }
}
