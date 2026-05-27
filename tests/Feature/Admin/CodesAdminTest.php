<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CodesAdminTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'is_active' => true]);
    }

    public function test_genera_codigos_con_formato_spm_xxxx(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.codes.generate'), ['quantity' => 5])
            ->assertRedirect();

        $codes = DB::table('invitation_codes')->pluck('code')->all();
        $this->assertCount(5, $codes);

        foreach ($codes as $code) {
            $this->assertMatchesRegularExpression('/^SPM-[A-Z0-9]{4}$/', $code,
                "Código '{$code}' no respeta el formato SPM-XXXX");
        }
    }

    public function test_valida_cantidad_entre_1_y_100(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.codes.generate'), ['quantity' => 0])
            ->assertSessionHasErrors('quantity');

        $this->actingAs($this->admin())
            ->post(route('admin.codes.generate'), ['quantity' => 101])
            ->assertSessionHasErrors('quantity');
    }

    public function test_desactiva_codigo_disponible(): void
    {
        $id = DB::table('invitation_codes')->insertGetId([
            'code' => 'SPM-TEST', 'is_used' => false, 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($this->admin())
            ->patch(route('admin.codes.deactivate', $id))
            ->assertRedirect();

        $this->assertDatabaseHas('invitation_codes', ['id' => $id, 'is_active' => false]);
    }

    public function test_no_desactiva_codigo_ya_usado(): void
    {
        $id = DB::table('invitation_codes')->insertGetId([
            'code' => 'SPM-USED', 'is_used' => true, 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($this->admin())
            ->patch(route('admin.codes.deactivate', $id))
            ->assertSessionHasErrors('code');

        $this->assertDatabaseHas('invitation_codes', ['id' => $id, 'is_active' => true]);
    }

    public function test_exporta_solo_codigos_disponibles_en_text_plain(): void
    {
        DB::table('invitation_codes')->insert([
            ['code' => 'SPM-AAAA', 'is_used' => false, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'SPM-BBBB', 'is_used' => true, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'SPM-CCCC', 'is_used' => false, 'is_active' => false, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'SPM-DDDD', 'is_used' => false, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $res = $this->actingAs($this->admin())->get(route('admin.codes.export'));
        $res->assertOk();
        $res->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
        $body = $res->getContent();

        $this->assertStringContainsString('SPM-AAAA', $body);
        $this->assertStringContainsString('SPM-DDDD', $body);
        $this->assertStringNotContainsString('SPM-BBBB', $body); // usado
        $this->assertStringNotContainsString('SPM-CCCC', $body); // desactivado
    }
}
