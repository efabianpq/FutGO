<?php

namespace Tests\Feature\Privacy;

use App\Models\Torneos\Club;
use App\Models\Torneos\ClubPlayer;
use App\Models\User;
use App\Support\Privacy\DocumentHasher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DocumentEncryptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_document_y_phone_se_guardan_cifrados_pero_el_modelo_los_descifra(): void
    {
        $user = User::factory()->create([
            'document'       => '1.090.000.137',
            'phone_whatsapp' => '3001234567',
        ]);

        $raw = DB::table('users')->where('id', $user->id)->first();

        // En BD no está el texto plano.
        $this->assertNotEquals('1.090.000.137', $raw->document);
        $this->assertNotEquals('3001234567', $raw->phone_whatsapp);
        $this->assertStringNotContainsString('1090000137', $raw->document);

        // El modelo lo descifra transparentemente.
        $fresh = $user->fresh();
        $this->assertSame('1.090.000.137', $fresh->document);
        $this->assertSame('3001234567', $fresh->phone_whatsapp);
    }

    public function test_document_hash_se_rellena_y_permite_buscar_por_blind_index(): void
    {
        $user = User::factory()->create(['document' => '12.345.678']);

        // El hash se calculó sobre el documento normalizado.
        $this->assertSame(DocumentHasher::hash('12345678'), $user->fresh()->document_hash);

        // Se encuentra aunque el WHERE sea con otro formato del mismo documento.
        $this->assertTrue(User::whereDocument('12-345-678')->where('id', $user->id)->exists());
        $this->assertTrue(User::whereDocument('12345678')->where('id', $user->id)->exists());
    }

    public function test_anti_duplicado_de_club_player_funciona_por_hash(): void
    {
        $captain = User::factory()->create();
        $club = Club::create([
            'name'            => 'Blind Index FC',
            'slug'            => 'blind-index-fc',
            'captain_user_id' => $captain->id,
            'created_by_user_id' => $captain->id,
        ]);

        ClubPlayer::create([
            'club_id'             => $club->id,
            'full_name'           => 'Uno',
            'document'            => 'CC-99',
            'verification_status' => 'por_verificar',
            'status'              => 'active',
        ]);

        // Mismo documento en otro formato → detectable por el blind index.
        $this->assertTrue(
            $club->players()->whereDocument('cc99')->exists()
        );
    }
}
