<?php

namespace Tests\Feature;

use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Estos tests originalmente vivían en /. El video se movió a /como-funciona
 * cuando la home se simplificó para ser solo hero + CTAs.
 */
class WelcomeVideoTest extends TestCase
{
    use RefreshDatabase;

    public function test_placeholder_cuando_no_hay_video(): void
    {
        Settings::set(Settings::VIDEO_URL, '');

        $this->get(route('how-it-works'))
            ->assertOk()
            ->assertSee('Video explicativo próximamente');
    }

    public function test_renderiza_iframe_para_url_watch(): void
    {
        Settings::set(Settings::VIDEO_URL, 'https://www.youtube.com/watch?v=dQw4w9WgXcQ');

        $this->get(route('how-it-works'))
            ->assertOk()
            ->assertSee('https://www.youtube.com/embed/dQw4w9WgXcQ', false)
            ->assertDontSee('Video explicativo próximamente');
    }

    public function test_renderiza_iframe_para_url_youtu_be(): void
    {
        Settings::set(Settings::VIDEO_URL, 'https://youtu.be/dQw4w9WgXcQ');

        $this->get(route('how-it-works'))
            ->assertOk()
            ->assertSee('https://www.youtube.com/embed/dQw4w9WgXcQ', false);
    }

    public function test_renderiza_iframe_para_url_embed(): void
    {
        Settings::set(Settings::VIDEO_URL, 'https://www.youtube.com/embed/abc123XYZ');

        $this->get(route('how-it-works'))
            ->assertOk()
            ->assertSee('https://www.youtube.com/embed/abc123XYZ', false);
    }

    public function test_url_no_youtube_muestra_placeholder(): void
    {
        Settings::set(Settings::VIDEO_URL, 'https://vimeo.com/123456');

        $this->get(route('how-it-works'))
            ->assertOk()
            ->assertSee('Video explicativo próximamente');
    }
}
