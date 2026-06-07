<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Suite PWA — verifica manifest, service worker, iconos,
 * meta tags en el layout y botones de instalación en el nav.
 */
class PwaTest extends TestCase
{
    use RefreshDatabase;

    // ── Helpers ──────────────────────────────────────────────────────────

    private function manifestPath(): string
    {
        return public_path('FutGO/pwa/manifest.webmanifest');
    }

    private function manifest(): array
    {
        return json_decode(file_get_contents($this->manifestPath()), true);
    }

    private function guestPage(): \Illuminate\Testing\TestResponse
    {
        // login usa layouts/app → muestra nav guest completo
        return $this->get('/login');
    }

    private function authPage(): \Illuminate\Testing\TestResponse
    {
        $user = User::factory()->create([
            'is_active' => true,
            'modules'   => 'torneos',
        ]);
        return $this->actingAs($user)->get(route('profile.show'));
    }

    // ── 1. Manifest existe y es JSON válido ─────────────────────────────

    public function test_manifest_webmanifest_existe_en_disco(): void
    {
        $this->assertFileExists($this->manifestPath());
    }

    public function test_manifest_es_json_valido(): void
    {
        $content = file_get_contents($this->manifestPath());
        $json    = json_decode($content, true);
        $this->assertNotNull($json, 'manifest.webmanifest no es JSON válido');
        $this->assertIsArray($json);
    }

    // ── 2. Manifest tiene los campos requeridos ──────────────────────────

    public function test_manifest_tiene_name_y_short_name(): void
    {
        $m = $this->manifest();
        $this->assertArrayHasKey('name', $m);
        $this->assertNotEmpty($m['name']);
        $this->assertArrayHasKey('short_name', $m);
        $this->assertNotEmpty($m['short_name']);
        $this->assertLessThanOrEqual(12, mb_strlen($m['short_name']), 'short_name excede 12 caracteres');
    }

    public function test_manifest_display_es_standalone(): void
    {
        $this->assertEquals('standalone', $this->manifest()['display']);
    }

    public function test_manifest_theme_color_es_hex_valido(): void
    {
        $themeColor = $this->manifest()['theme_color'];
        $this->assertStringStartsWith('#', $themeColor);
        $this->assertMatchesRegularExpression('/^#[0-9a-fA-F]{6}$/', $themeColor);
    }

    public function test_manifest_start_url_es_correcto(): void
    {
        $this->assertStringContainsString('source=pwa', $this->manifest()['start_url']);
    }

    // ── 3. Manifest tiene los 4 iconos (192/512 × any/maskable) ─────────

    public function test_manifest_tiene_cuatro_iconos(): void
    {
        $icons = $this->manifest()['icons'];
        $this->assertCount(4, $icons);
    }

    public function test_manifest_iconos_tienen_purpose_any_y_maskable(): void
    {
        $icons    = $this->manifest()['icons'];
        $purposes = array_column($icons, 'purpose');
        $this->assertContains('any', $purposes, 'Falta ícono con purpose "any"');
        $this->assertContains('maskable', $purposes, 'Falta ícono con purpose "maskable"');
    }

    public function test_manifest_iconos_tienen_tamanios_192_y_512(): void
    {
        $icons = $this->manifest()['icons'];
        $sizes = array_column($icons, 'sizes');
        $this->assertContains('192x192', $sizes, 'Falta ícono 192x192');
        $this->assertContains('512x512', $sizes, 'Falta ícono 512x512');
    }

    // ── 4. Archivos de icono existen en disco ───────────────────────────

    public function test_icono_pwa_192_existe(): void
    {
        $this->assertFileExists(public_path('FutGO/pwa/icon-192.png'));
    }

    public function test_icono_pwa_512_existe(): void
    {
        $this->assertFileExists(public_path('FutGO/pwa/icon-512.png'));
    }

    public function test_icono_pwa_192_maskable_existe(): void
    {
        $this->assertFileExists(public_path('FutGO/pwa/icon-192-maskable.png'));
    }

    public function test_icono_pwa_512_maskable_existe(): void
    {
        $this->assertFileExists(public_path('FutGO/pwa/icon-512-maskable.png'));
    }

    public function test_icono_apple_touch_180_existe(): void
    {
        $this->assertFileExists(public_path('FutGO/pwa/icon-180.png'));
    }

    // ── 5. Layout incluye meta tags PWA ─────────────────────────────────

    public function test_layout_incluye_link_manifest(): void
    {
        $this->guestPage()->assertSee('<link rel="manifest"', false);
    }

    public function test_layout_incluye_meta_theme_color(): void
    {
        $this->guestPage()->assertSee('name="theme-color"', false);
    }

    public function test_layout_incluye_apple_touch_icon(): void
    {
        $this->guestPage()->assertSee('rel="apple-touch-icon"', false);
    }

    // ── 6. Layout incluye los 3 metas de iOS ────────────────────────────

    public function test_layout_incluye_apple_mobile_web_app_capable(): void
    {
        $this->guestPage()->assertSee('apple-mobile-web-app-capable', false);
    }

    public function test_layout_incluye_apple_status_bar_style(): void
    {
        $this->guestPage()->assertSee('apple-mobile-web-app-status-bar-style', false);
    }

    public function test_layout_incluye_apple_title(): void
    {
        $this->guestPage()->assertSee('apple-mobile-web-app-title', false);
    }

    // ── 7. public/sw.js existe ──────────────────────────────────────────

    public function test_service_worker_existe_en_public(): void
    {
        $this->assertFileExists(public_path('sw.js'));
    }

    // ── 8. sw.js contiene los listeners y el nombre de cache ────────────

    public function test_service_worker_contiene_listener_install(): void
    {
        $this->assertStringContainsString(
            "'install'",
            file_get_contents(public_path('sw.js'))
        );
    }

    public function test_service_worker_contiene_listener_activate(): void
    {
        $this->assertStringContainsString(
            "'activate'",
            file_get_contents(public_path('sw.js'))
        );
    }

    public function test_service_worker_contiene_listener_fetch(): void
    {
        $this->assertStringContainsString(
            "'fetch'",
            file_get_contents(public_path('sw.js'))
        );
    }

    public function test_service_worker_define_cache_name(): void
    {
        $this->assertStringContainsString(
            'CACHE_NAME',
            file_get_contents(public_path('sw.js'))
        );
    }

    // ── 9. Nav guest contiene referencia al store PWA ───────────────────

    public function test_nav_guest_desktop_contiene_store_pwa_install(): void
    {
        $response = $this->guestPage();
        $response->assertSee('$store.pwa.install()', false);
        $response->assertSee('$store.pwa.canInstall', false);
    }

    // ── 10. Nav auth contiene referencia al store PWA ───────────────────

    public function test_nav_auth_contiene_store_pwa_install(): void
    {
        $response = $this->authPage();
        $response->assertSee('$store.pwa.install()', false);
        $response->assertSee('$store.pwa.canInstall', false);
    }

    // ── 11. Layout contiene modal iOS ────────────────────────────────────

    public function test_layout_contiene_modal_ios_show_condition(): void
    {
        $this->guestPage()->assertSee('showIosModal', false);
    }

    public function test_layout_contiene_instruccion_anadir_pantalla_inicio(): void
    {
        $this->guestPage()->assertSee('Añadir a pantalla de inicio', false);
    }

    // ── 12. Layout contiene toast de instalación correcta ───────────────

    public function test_layout_contiene_toast_show_condition(): void
    {
        $this->guestPage()->assertSee('showToast', false);
    }

    public function test_layout_contiene_texto_instalado_correctamente(): void
    {
        $this->guestPage()->assertSee('instalado correctamente', false);
    }
}
