<?php

namespace Tests\Feature\Privacy;

use App\Models\Privacy\DataRequest;
use App\Models\Torneos\Club;
use App\Models\Torneos\ClubPlayer;
use App\Models\User;
use App\Notifications\Privacy\AccountDeletionCodeNotification;
use App\Services\Privacy\AccountDeletionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AccountDeletionFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_flujo_completo_con_codigo_gracia_y_cancelacion(): void
    {
        Notification::fake();
        $user = User::factory()->create(['password' => Hash::make('secret123')]);

        // Paso 1: contraseña incorrecta → error.
        $this->actingAs($user)->post(route('privacidad.eliminar.solicitar'), [
            'password' => 'mala', 'confirm' => '1',
        ])->assertSessionHasErrors('password');

        // Paso 1 OK: envía código.
        $this->actingAs($user)->post(route('privacidad.eliminar.solicitar'), [
            'password' => 'secret123', 'confirm' => '1',
        ])->assertRedirect(route('privacidad.eliminar'));

        Notification::assertSentTo($user, AccountDeletionCodeNotification::class);
        $dr = DataRequest::where('user_id', $user->id)->deletes()->firstOrFail();
        $this->assertSame(DataRequest::STATUS_PENDING, $dr->status);

        // Paso 3: código incorrecto → error.
        $this->actingAs($user)->post(route('privacidad.eliminar.verificar'), ['code' => '000000'])
            ->assertSessionHasErrors('code');

        // Código correcto → periodo de gracia.
        $this->actingAs($user)->post(route('privacidad.eliminar.verificar'), ['code' => $dr->verification_code])
            ->assertRedirect(route('privacidad.eliminar'));

        $dr->refresh();
        $this->assertSame(DataRequest::STATUS_PROCESSING, $dr->status);
        $this->assertNotNull($dr->executes_at);
        $this->assertNotNull($user->fresh()->delete_requested_at);

        // Cancelación durante la gracia.
        $this->actingAs($user->fresh())->delete(route('privacidad.eliminar.cancelar'))
            ->assertRedirect(route('privacidad.centro'));

        $this->assertSame(DataRequest::STATUS_CANCELLED, $dr->fresh()->status);
        $this->assertNull($user->fresh()->delete_requested_at);
    }

    public function test_ejecucion_anonimiza_usuario_y_plantillas_preservando_stats(): void
    {
        $user = User::factory()->create([
            'name' => 'Fabián Pachón', 'document' => '123456', 'phone_whatsapp' => '3001112233',
        ]);
        $captain = User::factory()->create();
        $club = Club::create([
            'name' => 'FC Test', 'slug' => 'fc-test',
            'captain_user_id' => $captain->id, 'created_by_user_id' => $captain->id,
        ]);
        $cp = ClubPlayer::create([
            'club_id' => $club->id, 'user_id' => $user->id,
            'full_name' => 'Fabián Pachón', 'document' => '123456',
            'verification_status' => 'registrado', 'status' => 'active',
        ]);

        app(AccountDeletionService::class)->anonymize($user);

        $user->refresh();
        $this->assertSame('Usuario eliminado', $user->name);
        $this->assertStringContainsString('@futgo.invalid', $user->email);
        $this->assertNull($user->document);
        $this->assertNull($user->phone_whatsapp);

        $cp->refresh();
        $this->assertSame('Jugador eliminado', $cp->full_name);
        $this->assertNull($cp->document);

        // El vínculo (id) se preserva: las stats históricas no se rompen.
        $this->assertSame($user->id, $cp->user_id);
    }

    public function test_el_comando_ejecuta_las_solicitudes_vencidas(): void
    {
        $user = User::factory()->create(['name' => 'Por Borrar']);
        $dr = DataRequest::create([
            'user_id' => $user->id, 'type' => DataRequest::TYPE_DELETE,
            'status' => DataRequest::STATUS_PROCESSING, 'verified_at' => now()->subDays(31),
            'executes_at' => now()->subDay(),
        ]);

        $this->artisan('futgo:purge-deleted-accounts')->assertSuccessful();

        $this->assertSame(DataRequest::STATUS_COMPLETED, $dr->fresh()->status);
        $this->assertSame('Usuario eliminado', $user->fresh()->name);
    }
}
