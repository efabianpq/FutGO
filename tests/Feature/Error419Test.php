<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Verifica que la vista de error 419 (CSRF / sesión expirada) existe,
 * renderiza sin excepciones y contiene el contenido requerido.
 */
class Error419Test extends TestCase
{
    private function rendered(): string
    {
        return view('errors.419')->render();
    }

    public function test_vista_419_renderiza_sin_errores(): void
    {
        $html = $this->rendered();
        $this->assertIsString($html);
        $this->assertNotEmpty($html);
    }

    public function test_vista_419_contiene_sesion_expirada(): void
    {
        $this->assertStringContainsString('Sesión expirada', $this->rendered());
    }

    public function test_vista_419_contiene_boton_volver_al_inicio(): void
    {
        $this->assertStringContainsString('Volver al inicio', $this->rendered());
    }

    public function test_vista_419_contiene_boton_iniciar_sesion(): void
    {
        $this->assertStringContainsString('Iniciar sesión', $this->rendered());
    }
}
