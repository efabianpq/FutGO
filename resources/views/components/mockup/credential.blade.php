@props([
    'name' => 'Andrés Soto',
    'role' => 'Delantero · #9',
    'fairPlay' => '9.2',
    'season' => 'Temporada ' . date('Y'),
    'matches' => 37,
    'goals' => 28,
    'mvp' => 6,
    'photo' => '/FutGO/landing/cred-placeholder.svg',
    'qrTarget' => null,   // qué codifica el QR; por defecto, la home pública
])

@php
    // QR REAL generado con bacon/bacon-qr-code (mismo backend que CredentialService),
    // no un SVG estático de maqueta. Codifica una URL honesta de la plataforma.
    $qrSvg = null;
    try {
        $writer = new \BaconQrCode\Writer(new \BaconQrCode\Renderer\ImageRenderer(
            new \BaconQrCode\Renderer\RendererStyle\RendererStyle(120, 1),
            new \BaconQrCode\Renderer\Image\SvgImageBackEnd()
        ));
        $qrSvg = $writer->writeString($qrTarget ?? url('/'));
        // Quitar el prólogo XML para embeber inline dentro del HTML.
        $qrSvg = preg_replace('/^<\?xml[^>]*\?>\s*/', '', $qrSvg);
    } catch (\Throwable $e) {
        $qrSvg = null;
    }
@endphp

<div class="max-w-[320px] mx-auto rounded-xl overflow-hidden text-white shadow-card-2 border border-white/10"
     style="background:linear-gradient(165deg,#141d2e,#0a0e16)">

    {{-- Cabecera --}}
    <div class="flex items-center justify-between px-[18px] pt-[18px]">
        <span class="font-x font-extrabold text-[15px]" style="font-stretch:120%">Fut<b style="color:var(--color-green)">GO</b></span>
        <span class="font-mono text-[10px]" style="letter-spacing:.1em; color:rgba(238,243,238,.5)">{{ $season }}</span>
    </div>

    {{-- Identidad --}}
    <div class="flex items-center gap-3.5 px-[18px] py-4">
        <div class="w-[66px] h-[66px] rounded-[14px] overflow-hidden shrink-0 border-2 border-white/10" style="background:#1c2820">
            <img src="{{ $photo }}" alt="" class="w-full h-full object-cover">
        </div>
        <div>
            <div class="font-x font-extrabold text-[21px] leading-none" style="font-stretch:118%">{{ $name }}</div>
            <div class="font-mono text-[10.5px] uppercase mt-1.5" style="letter-spacing:.08em; color:var(--color-green)">
                {{ $role }} &nbsp;·&nbsp; <span style="color:#ffd23f">★ Fair play {{ $fairPlay }}</span>
            </div>
        </div>
    </div>

    {{-- Stats --}}
    <div class="grid grid-cols-3 border-t border-white/10">
        @foreach([[$matches, 'Partidos'], [$goals, 'Goles'], [$mvp, 'MVP']] as $i => [$n, $l])
            <div class="p-3 text-center @if($i < 2) border-r border-white/10 @endif">
                <div class="font-x font-extrabold text-[24px]" style="font-stretch:118%">{{ $n }}</div>
                <div class="font-mono text-[9px] uppercase mt-0.5" style="letter-spacing:.1em; color:rgba(238,243,238,.55)">{{ $l }}</div>
            </div>
        @endforeach
    </div>

    {{-- QR --}}
    <div class="flex items-center gap-3.5 px-[18px] py-3.5" style="background:rgba(0,0,0,.25)">
        <div class="w-[56px] h-[56px] rounded-[9px] bg-white p-[5px] shrink-0" role="img"
             aria-label="Código QR para verificar la credencial del jugador">
            @if($qrSvg)
                <div class="w-full h-full [&>svg]:w-full [&>svg]:h-full [&>svg]:block">{!! $qrSvg !!}</div>
            @endif
        </div>
        <div class="text-[11.5px] leading-snug" style="color:rgba(238,243,238,.7)">
            <b style="color:#eef3ee">Credencial digital con QR.</b> Verificá tu identidad y tu carrera en cualquier cancha, al instante.
        </div>
    </div>
</div>
<p class="text-center font-mono text-[12px] text-subtle mt-4">Tu perfil te acompaña a cada partido</p>
