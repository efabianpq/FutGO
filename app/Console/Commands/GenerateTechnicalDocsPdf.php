<?php

namespace App\Console\Commands;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use League\CommonMark\GithubFlavoredMarkdownConverter;

/**
 * Genera el PDF del informe técnico a partir de TECHNICAL_DOCS.md
 *
 * Uso: php artisan docs:generate-pdf
 * Output: docs/informe_tecnico_soypachonmundial.pdf
 */
class GenerateTechnicalDocsPdf extends Command
{
    protected $signature = 'docs:generate-pdf';
    protected $description = 'Genera docs/informe_tecnico_soypachonmundial.pdf desde TECHNICAL_DOCS.md';

    public function handle(): int
    {
        $sourcePath = base_path('TECHNICAL_DOCS.md');
        $outputPath = base_path('docs/informe_tecnico_soypachonmundial.pdf');

        if (! file_exists($sourcePath)) {
            $this->error("No se encontró TECHNICAL_DOCS.md en {$sourcePath}");
            return self::FAILURE;
        }

        // Asegurar carpeta docs/
        if (! is_dir(dirname($outputPath))) {
            mkdir(dirname($outputPath), 0775, true);
        }

        $this->info('📖 Leyendo TECHNICAL_DOCS.md...');
        $markdown = file_get_contents($sourcePath);

        $this->info('🔄 Convirtiendo Markdown → HTML...');
        // Laravel 11 trae league/commonmark como dependencia transitive.
        // Usamos GithubFlavored para soporte de tablas.
        $converter = new GithubFlavoredMarkdownConverter([
            'html_input' => 'allow',
            'allow_unsafe_links' => true,
        ]);
        $html = $converter->convert($markdown)->getContent();

        $this->info('🎨 Aplicando estilos PDF (landscape, 10px, márgenes compactos)...');
        $pdf = Pdf::loadView('docs.technical-pdf', [
            'content' => $html,
            'generatedAt' => now()->locale('es')->isoFormat('dddd D [de] MMMM YYYY [a las] HH:mm'),
        ])->setPaper('letter', 'landscape');

        $this->info('💾 Guardando PDF...');
        file_put_contents($outputPath, $pdf->output());

        $sizeKb = round(filesize($outputPath) / 1024);
        $this->info("✅ Generado: docs/informe_tecnico_soypachonmundial.pdf ({$sizeKb} KB)");

        return self::SUCCESS;
    }
}
