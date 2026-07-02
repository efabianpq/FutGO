<?php

namespace Tests\Feature\Privacy;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DataExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_usuario_descarga_sus_datos_sin_datos_de_terceros(): void
    {
        $me = User::factory()->create([
            'name' => 'Yo Mismo', 'document' => '55667788', 'phone_whatsapp' => '3009998877',
        ]);
        $otro = User::factory()->create(['email' => 'otro-secreto@example.com']);

        $response = $this->actingAs($me)->get(route('privacidad.exportar.descargar'));

        $response->assertOk();
        $response->assertHeader('content-disposition');

        $content = $response->getContent();
        // Datos propios presentes.
        $this->assertStringContainsString($me->futgo_id, $content);
        $this->assertStringContainsString('55667788', $content);
        // Datos de terceros ausentes.
        $this->assertStringNotContainsString('otro-secreto@example.com', $content);

        // Queda registrada la solicitud de exportación (auditoría).
        $this->assertDatabaseHas('data_requests', [
            'user_id' => $me->id, 'type' => 'export', 'status' => 'completed',
        ]);
    }

    public function test_la_pagina_de_exportacion_y_habeas_data_cargan(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get(route('privacidad.exportar'))->assertOk();
        $this->actingAs($user)->get(route('privacidad.habeas'))->assertOk();
    }
}
