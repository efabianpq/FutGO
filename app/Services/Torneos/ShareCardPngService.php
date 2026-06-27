<?php

namespace App\Services\Torneos;

use App\Models\Social\FriendlyMatch;
use App\Models\Torneos\Tournament;
use App\Models\Torneos\TournamentMatch;
use Illuminate\Support\Facades\Storage;

/**
 * Genera tarjetas compartibles en PNG usando GD (portátil a Hostinger).
 *
 * Soporta los mismos tipos que TournamentShareController (goleadores, posiciones,
 * partido, mvp) más el nuevo tipo "amistoso". Los PNG se almacenan en el disco
 * configurado en MEDIA_DISK para servirse con caché de larga duración.
 *
 * Si GD no está disponible, devuelve null y el controlador debe degradar al SVG.
 */
class ShareCardPngService
{
    // Dimensiones de la tarjeta (igual que el SVG).
    private const W = 1080;
    private const H = 1080;

    /** Ruta de fuente TTF resuelta (memoizada para evitar múltiples file_exists()). */
    private ?string $resolvedFont = null;
    private bool $fontResolved = false;

    // Paleta FutGO.
    private const BG_TOP    = [14, 58, 39];   // #0e3a27
    private const BG_BOT    = [9,  31, 21];   // #091f15
    private const ACCENT    = [0, 230, 118];  // #00E676
    private const TEXT_WHITE = [255, 255, 255];
    private const TEXT_MUTE  = [159, 180, 168]; // #9fb4a8
    private const TEXT_DIM   = [125, 148, 136]; // #7d9488
    private const FOOTER_BG  = [10, 28, 19];   // #0a1c13
    private const ROW_DARK   = [15, 58, 40];   // #0f3a28
    private const ROW_LIGHT  = [18, 63, 44];   // #123f2c

    /** Directorio de almacenamiento dentro del disco MEDIA_DISK. */
    private const STORE_DIR = 'cards';

    /** Ruta del archivo almacenado para un tipo/slug/identificador. */
    public function storagePath(string $tournamentSlug, string $type, int|string $id = ''): string
    {
        $suffix = $id !== '' ? "-{$id}" : '';
        return self::STORE_DIR . "/{$tournamentSlug}/{$type}{$suffix}.png";
    }

    /**
     * Genera y almacena el PNG para una tarjeta de torneo (goleadores/posiciones/mvp/partido).
     * Devuelve la URL pública del PNG almacenado, o null si GD no está disponible.
     */
    public function generateForTournament(
        Tournament $tournament,
        string $type,
        array $data,
        int|string $id = ''
    ): ?string {
        if (! $this->gdAvailable()) {
            return null;
        }

        $png = match ($type) {
            'goleadores' => $this->drawGoleadores($tournament->name, $data['scorers'] ?? collect()),
            'posiciones' => $this->drawPosiciones($tournament->name, $data['phases'] ?? collect()),
            'partido'    => $this->drawPartido($tournament->name, $data['match']),
            'mvp'        => $this->drawMvp($tournament->name, $data['match'] ?? null),
            default      => null,
        };

        if ($png === null) {
            return null;
        }

        $path = $this->storagePath($tournament->slug, $type, $id);
        $this->store($path, $png);

        return $this->url($path);
    }

    /**
     * Genera y almacena el PNG para una tarjeta de amistoso.
     * Devuelve la URL pública o null si GD no está disponible.
     */
    public function generateForFriendly(FriendlyMatch $match): ?string
    {
        if (! $this->gdAvailable()) {
            return null;
        }

        $png = $this->drawAmistoso($match);
        if ($png === null) {
            return null;
        }

        $path = $this->friendlyStoragePath($match->id);
        $this->store($path, $png);

        return $this->url($path);
    }

    /** ¿Está GD disponible en este entorno? */
    public function gdAvailable(): bool
    {
        return extension_loaded('gd') && function_exists('imagecreatetruecolor');
    }

    /** Ruta de almacenamiento del PNG de un amistoso (única fuente de verdad). */
    public function friendlyStoragePath(int $matchId): string
    {
        return self::STORE_DIR . "/amistosos/{$matchId}.png";
    }

    // ─── Dibujadores de cada tipo ─────────────────────────────────────────────

    private function drawGoleadores(string $tournamentName, $scorers): string
    {
        $img = $this->baseCanvas('Goleadores', $tournamentName);
        $font = $this->fontPath();
        $y = 348;

        foreach ($scorers->take(8) as $i => $st) {
            $rowColor = $i % 2 ? self::ROW_DARK : self::ROW_LIGHT;
            $this->drawRow($img, 64, $y, 952, 68, $rowColor);

            $name  = mb_strimwidth($st->teamPlayer?->displayName() ?? '—', 0, 24, '…');
            $team  = mb_strimwidth($st->teamPlayer?->team?->name ?? '', 0, 28, '…');
            $goals = (string)($st->goals ?? 0);

            $this->text($img, $font, 38, 94, $y + 46, self::ACCENT, (string)($i + 1));
            $this->text($img, $font, 32, 160, $y + 38, self::TEXT_WHITE, $name);
            $this->text($img, $font, 20, 160, $y + 60, self::TEXT_MUTE, $team);
            $this->textRight($img, $font, 44, 64 + 952 - 30, $y + 50, self::ACCENT, $goals);

            $y += 80;
        }

        if ($scorers->isEmpty()) {
            $this->text($img, $font, 32, 64, 408, self::TEXT_MUTE, 'Aún no hay goles registrados.');
        }

        return $this->toPng($img);
    }

    private function drawPosiciones(string $tournamentName, $phases): string
    {
        $img = $this->baseCanvas('Posiciones', $tournamentName);
        $font = $this->fontPath();
        $y = 348;

        foreach ($phases->take(2) as $phase) {
            if ($phases->count() > 1) {
                $this->text($img, $font, 24, 64, $y, self::ACCENT, mb_strtoupper($phase['phase'] ?? ''));
                $y += 40;
            }
            foreach (($phase['standings'] ?? collect())->take(6) as $i => $s) {
                $rowColor = $i % 2 ? self::ROW_DARK : self::ROW_LIGHT;
                $this->drawRow($img, 64, $y, 952, 56, $rowColor);

                $teamName = mb_strimwidth($s->team?->name ?? '—', 0, 22, '…');
                $this->text($img, $font, 28, 94, $y + 38, self::ACCENT, (string)($i + 1));
                $this->text($img, $font, 28, 140, $y + 38, self::TEXT_WHITE, $teamName);
                $this->textRight($img, $font, 32, 64 + 952 - 30, $y + 40, self::ACCENT, (string)($s->points ?? 0));
                $y += 60;
            }
            $y += 20;
        }

        return $this->toPng($img);
    }

    private function drawPartido(string $tournamentName, TournamentMatch $match): string
    {
        $img  = $this->baseCanvas('Resultado', $tournamentName);
        $font = $this->fontPath();

        $phase   = mb_strtoupper($match->phase?->name ?? 'Partido');
        $homeName = mb_strimwidth($match->homeTeam?->name ?? '—', 0, 24, '…');
        $awayName = mb_strimwidth($match->awayTeam?->name ?? '—', 0, 24, '…');
        $homeScore = (string)$match->home_score;
        $awayScore = (string)$match->away_score;

        $cx = self::W / 2;

        $this->textCentered($img, $font, 26, $cx, 388, self::TEXT_MUTE, $phase);
        $this->textCentered($img, $font, 46, $cx, 518, self::TEXT_WHITE, $homeName);

        // Marcador
        $this->textCentered($img, $font, 150, $cx - 116, 728, self::ACCENT, $homeScore);
        $this->textCentered($img, $font, 64, $cx, 712, self::TEXT_DIM, '-');
        $this->textCentered($img, $font, 150, $cx + 116, 728, self::ACCENT, $awayScore);

        $this->textCentered($img, $font, 46, $cx, 888, self::TEXT_WHITE, $awayName);

        if ($match->scheduled_at) {
            $date = \Carbon\Carbon::parse($match->scheduled_at)->locale('es')->isoFormat('D [de] MMMM YYYY');
            $this->textCentered($img, $font, 26, $cx, 958, self::TEXT_MUTE, $date);
        }

        return $this->toPng($img);
    }

    private function drawMvp(string $tournamentName, ?TournamentMatch $match): string
    {
        $img  = $this->baseCanvas('MVP', $tournamentName);
        $font = $this->fontPath();
        $cx   = self::W / 2;

        if ($match?->mvpTeamPlayer) {
            $name = mb_strimwidth($match->mvpTeamPlayer->displayName(), 0, 28, '…');
            $team = mb_strimwidth($match->mvpTeamPlayer->team?->name ?? '', 0, 32, '…');
            $this->textCentered($img, $font, 22, $cx, 400, self::TEXT_MUTE, 'MEJOR JUGADOR DEL PARTIDO');
            $this->textCentered($img, $font, 72, $cx, 520, self::ACCENT, $name);
            $this->textCentered($img, $font, 36, $cx, 580, self::TEXT_MUTE, $team);
        } else {
            $this->textCentered($img, $font, 32, $cx, 500, self::TEXT_MUTE, 'Sin MVP registrado.');
        }

        return $this->toPng($img);
    }

    private function drawAmistoso(FriendlyMatch $match): string
    {
        $img  = $this->baseCanvas('Amistoso FutGO', 'Resultado del partido');
        $font = $this->fontPath();

        $homeName  = mb_strimwidth($match->homeClub?->name ?? '—', 0, 24, '…');
        $awayName  = mb_strimwidth($match->awayClub?->name ?? '—', 0, 24, '…');
        $homeScore = (string)($match->final_home_score ?? 0);
        $awayScore = (string)($match->final_away_score ?? 0);
        $cx        = self::W / 2;

        $this->textCentered($img, $font, 26, $cx, 388, self::TEXT_MUTE, 'AMISTOSO');
        $this->textCentered($img, $font, 46, $cx, 518, self::TEXT_WHITE, $homeName);

        $this->textCentered($img, $font, 150, $cx - 116, 728, self::ACCENT, $homeScore);
        $this->textCentered($img, $font, 64, $cx, 712, self::TEXT_DIM, '-');
        $this->textCentered($img, $font, 150, $cx + 116, 728, self::ACCENT, $awayScore);

        $this->textCentered($img, $font, 46, $cx, 888, self::TEXT_WHITE, $awayName);

        if ($match->scheduled_at) {
            $date = \Carbon\Carbon::parse($match->scheduled_at)->locale('es')->isoFormat('D [de] MMMM YYYY');
            $this->textCentered($img, $font, 26, $cx, 958, self::TEXT_MUTE, $date);
        }

        return $this->toPng($img);
    }

    // ─── Canvas base y primitivas GD ─────────────────────────────────────────

    /**
     * Crea el canvas base 1080×1080 con el branding FutGO:
     * fondo en gradiente, franja de acento superior, cabecera y pie.
     *
     * @return \GdImage
     */
    private function baseCanvas(string $title, string $subtitle)
    {
        $img = imagecreatetruecolor(self::W, self::H);
        imagesavealpha($img, true);

        // Gradiente vertical de fondo (top→bot).
        for ($y = 0; $y < self::H; $y++) {
            $r = (int)(self::BG_TOP[0] + (self::BG_BOT[0] - self::BG_TOP[0]) * $y / self::H);
            $g = (int)(self::BG_TOP[1] + (self::BG_BOT[1] - self::BG_TOP[1]) * $y / self::H);
            $b = (int)(self::BG_TOP[2] + (self::BG_BOT[2] - self::BG_TOP[2]) * $y / self::H);
            imageline($img, 0, $y, self::W - 1, $y, imagecolorallocate($img, $r, $g, $b));
        }

        $accentColor = $this->color($img, self::ACCENT);
        $footerColor = $this->color($img, self::FOOTER_BG);

        // Franja de acento superior.
        imagefilledrectangle($img, 0, 0, self::W - 1, 15, $accentColor);

        // Pie de página.
        imagefilledrectangle($img, 0, 1010, self::W - 1, self::H - 1, $footerColor);

        $font = $this->fontPath();

        // Cabecera: "FUTGO" + subtítulo + título.
        $this->text($img, $font, 58, 64, 118, self::ACCENT, 'FUTGO');
        $sub = mb_strimwidth($subtitle, 0, 48, '…');
        $this->text($img, $font, 27, 64, 166, self::TEXT_MUTE, $sub);
        $this->text($img, $font, 76, 64, 278, self::TEXT_WHITE, mb_strtoupper($title));

        // Pie: slogan.
        $this->text($img, $font, 26, 64, 1054, self::TEXT_DIM, 'Donde crece el fútbol amateur');

        return $img;
    }

    /** Renderiza texto. Usa TTF si hay fuente; degrada a imagestring. */
    private function text($img, ?string $font, int $size, int $x, int $y, array $rgb, string $text): void
    {
        if (! $text) {
            return;
        }
        $color = $this->color($img, $rgb);
        if ($font) {
            imagettftext($img, $size * 0.75, 0, $x, $y, $color, $font, $text);
        } else {
            // Fallback: bitmap font (limitado pero sin dependencias).
            $gdSize = max(1, min(5, (int)($size / 14)));
            imagestring($img, $gdSize, $x, $y - 14, $text, $color);
        }
    }

    /** Renderiza texto centrado en x. */
    private function textCentered($img, ?string $font, int $size, int $cx, int $y, array $rgb, string $text): void
    {
        if (! $text) {
            return;
        }
        $color = $this->color($img, $rgb);
        if ($font) {
            $box = imagettfbbox($size * 0.75, 0, $font, $text);
            if ($box === false) {
                // GD no pudo medir la fuente — fallback a bitmap.
                $font = null;
            } else {
                $tw = abs($box[4] - $box[0]);
                imagettftext($img, $size * 0.75, 0, (int)($cx - $tw / 2), $y, $color, $font, $text);
                return;
            }
        }
        $gdSize = max(1, min(5, (int)($size / 14)));
        $tw = imagefontwidth($gdSize) * strlen($text);
        imagestring($img, $gdSize, (int)($cx - $tw / 2), $y - 14, $text, $color);
    }

    /** Renderiza texto alineado a la derecha de $rightX. */
    private function textRight($img, ?string $font, int $size, int $rightX, int $y, array $rgb, string $text): void
    {
        if (! $text) {
            return;
        }
        $color = $this->color($img, $rgb);
        if ($font) {
            $box = imagettfbbox($size * 0.75, 0, $font, $text);
            if ($box === false) {
                $font = null;
            } else {
                $tw = abs($box[4] - $box[0]);
                imagettftext($img, $size * 0.75, 0, (int)($rightX - $tw), $y, $color, $font, $text);
                return;
            }
        }
        $gdSize = max(1, min(5, (int)($size / 14)));
        $tw = imagefontwidth($gdSize) * strlen($text);
        imagestring($img, $gdSize, (int)($rightX - $tw), $y - 14, $text, $color);
    }

    /** Dibuja un rectángulo redondeado (usando polígono aproximado). */
    private function drawRow($img, int $x, int $y, int $w, int $h, array $rgb): void
    {
        $color = $this->color($img, $rgb);
        imagefilledrectangle($img, $x, $y, $x + $w, $y + $h, $color);
    }

    /** Convierte el GdImage a PNG binario y destruye el recurso. Retorna null si GD falla. */
    private function toPng($img): ?string
    {
        ob_start();
        $ok   = imagepng($img, null, 6); // compresión 6 (balance velocidad/tamaño)
        $data = ob_get_clean();
        imagedestroy($img);

        if (! $ok || $data === false || $data === '') {
            return null;
        }

        return $data;
    }

    /** Asigna o reutiliza un color GD. */
    private function color($img, array $rgb): int
    {
        return imagecolorallocate($img, $rgb[0], $rgb[1], $rgb[2]);
    }

    // ─── Fuente TTF ───────────────────────────────────────────────────────────

    /**
     * Busca una fuente TTF utilizable (memoizada por instancia).
     * Orden de preferencia:
     *   1. config('share_card.font_path') / SHARE_CARD_FONT_PATH env (via config)
     *   2. Fuentes empaquetadas en resources/fonts/
     *   3. Fuentes comunes de Linux (Hostinger)
     *   4. Fuentes comunes de Windows (Laragon dev)
     */
    private function fontPath(): ?string
    {
        if ($this->fontResolved) {
            return $this->resolvedFont;
        }

        $candidates = array_filter([
            config('filesystems.share_card_font_path', env('SHARE_CARD_FONT_PATH')),
            base_path('resources/fonts/Inter-Bold.ttf'),
            base_path('resources/fonts/Roboto-Bold.ttf'),
            base_path('resources/fonts/OpenSans-Bold.ttf'),
            '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
            '/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf',
            '/usr/share/fonts/truetype/ubuntu/Ubuntu-B.ttf',
            'C:\\Windows\\Fonts\\arialbd.ttf',
            'C:\\Windows\\Fonts\\Arial Bold.ttf',
            'C:\\Windows\\Fonts\\calibrib.ttf',
        ]);

        foreach ($candidates as $path) {
            if ($path && file_exists($path)) {
                $this->resolvedFont = $path;
                $this->fontResolved = true;
                return $path;
            }
        }

        $this->resolvedFont = null;
        $this->fontResolved = true;
        return null; // degradación a bitmap fonts
    }

    // ─── Storage ──────────────────────────────────────────────────────────────

    private function store(string $path, string $data): void
    {
        Storage::disk($this->disk())->put($path, $data);
    }

    public function url(string $path): string
    {
        return Storage::disk($this->disk())->url($path);
    }

    public function exists(string $path): bool
    {
        return Storage::disk($this->disk())->exists($path);
    }

    public function get(string $path): ?string
    {
        if (! $this->exists($path)) {
            return null;
        }
        return Storage::disk($this->disk())->get($path);
    }

    private function disk(): string
    {
        return config('filesystems.media_disk', 'public');
    }
}
