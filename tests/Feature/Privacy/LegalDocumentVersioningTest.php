<?php

namespace Tests\Feature\Privacy;

use App\Models\Privacy\LegalDocument;
use App\Models\User;
use App\Services\Privacy\LegalDocumentService;
use Database\Seeders\LegalDocumentsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegalDocumentVersioningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LegalDocumentsSeeder::class);
    }

    public function test_publicar_version_deja_una_sola_vigente_por_tipo(): void
    {
        app(LegalDocumentService::class)->publish([
            'type' => 'terms', 'version' => '2.0', 'title' => 'Términos', 'content' => 'nuevo',
        ]);

        $current = LegalDocument::ofType('terms')->where('is_current', true)->get();

        $this->assertCount(1, $current);
        $this->assertSame('2.0', $current->first()->version);
        // La 1.0 quedó como histórica.
        $this->assertFalse(LegalDocument::ofType('terms')->where('version', '1.0')->first()->is_current);
    }

    public function test_admin_no_puede_republicar_una_version_existente(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'current_privacy_version' => '1.0',
            'current_terms_version' => '1.0',
        ]);

        $this->actingAs($admin)->post(route('admin.legal.store'), [
            'type' => 'terms', 'version' => '1.0', 'title' => 'Términos', 'content' => 'x',
        ])->assertSessionHasErrors('version');
    }

    public function test_no_admin_no_accede_al_panel_legal(): void
    {
        $user = User::factory()->create([
            'current_privacy_version' => '1.0',
            'current_terms_version' => '1.0',
        ]);

        // EnsureAdmin redirige (no 403) a los no-admin.
        $this->actingAs($user)->get(route('admin.legal.index'))
            ->assertRedirect(route('dashboard'));
    }
}
