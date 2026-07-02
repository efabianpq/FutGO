<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Tests\TestCase;

/**
 * Verifica que los endpoints de perfil no permiten mass-assignment de campos sensibles.
 *
 * El riesgo: $fillable del modelo User incluye role. Ningún controlador usa
 * $request->all() sobre User, pero este test lo documenta y lo hace un
 * contrato explícito de regresión.
 */
class MassAssignmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    public function test_patch_perfil_no_puede_escalar_role_a_admin(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $this->actingAs($user)->patch(route('profile.update'), [
            'phone_whatsapp' => '3001234567',
            'role'           => 'admin',
        ]);

        $this->assertSame('user', $user->fresh()->role);
    }

    public function test_patch_perfil_actualiza_correctamente_los_campos_permitidos(): void
    {
        $user = User::factory()->create([
            'phone_whatsapp' => '3001111111',
            'document'       => null,
        ]);

        $this->actingAs($user)->patch(route('profile.update'), [
            'phone_whatsapp'         => '3009999999',
            'document'               => '12345678',
            'notifications_enabled'  => true,
        ])->assertRedirect();

        $user->refresh();
        $this->assertSame('3009999999', $user->phone_whatsapp);
        $this->assertSame('12345678', $user->document);
        $this->assertTrue((bool) $user->notifications_enabled);
    }
}
