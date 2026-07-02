<?php

namespace Tests\Feature\Privacy;

use App\Models\Privacy\AuditLog;
use App\Models\User;
use App\Services\Privacy\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_nunca_persiste_claves_sensibles_y_enmascara_email(): void
    {
        $user = User::factory()->create();

        AuditLogger::record('test_action', $user, null, [
            'password'      => 'secreto',
            'document'      => '123456',
            'token'         => 'abc',
            'contact_email' => 'juan.perez@example.com',
            'inocuo'        => 'ok',
        ]);

        $log = AuditLog::where('action', 'test_action')->firstOrFail();
        $meta = $log->metadata;

        $this->assertArrayNotHasKey('password', $meta);
        $this->assertArrayNotHasKey('document', $meta);
        $this->assertArrayNotHasKey('token', $meta);
        $this->assertSame('ok', $meta['inocuo']);
        // Email enmascarado.
        $this->assertSame('j*********@example.com', $meta['contact_email']);
    }

    public function test_login_y_configuracion_quedan_auditados(): void
    {
        $user = User::factory()->create();

        // El evento Login se dispara al autenticar.
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect();
        $this->assertDatabaseHas('audit_logs', ['user_id' => $user->id, 'action' => 'login']);

        // Actualizar la configuración de privacidad queda registrado.
        $this->actingAs($user)->patch(route('privacidad.configuracion.update'), ['allow_messages' => 'todos']);
        $this->assertDatabaseHas('audit_logs', ['user_id' => $user->id, 'action' => 'privacy_settings_updated']);
    }

    public function test_auditar_no_rompe_si_algo_falla(): void
    {
        // Con un usuario inexistente/id nulo no debe lanzar excepción.
        AuditLogger::record('accion_sin_usuario');
        $this->assertDatabaseHas('audit_logs', ['action' => 'accion_sin_usuario']);
    }
}
