@props([
    'title' => '',
    'subtitle' => '',
    'accent' => '#00E676',
])
{{-- Marco SVG común de las tarjetas compartibles (1080×1080, branding FutGO). --}}
<svg xmlns="http://www.w3.org/2000/svg" width="1080" height="1080" viewBox="0 0 1080 1080" font-family="Archivo, Arial, sans-serif">
    <defs>
        <linearGradient id="futgoBg" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0" stop-color="#0e3a27"/>
            <stop offset="1" stop-color="#091f15"/>
        </linearGradient>
    </defs>

    <rect width="1080" height="1080" fill="url(#futgoBg)"/>
    <rect x="0" y="0" width="1080" height="16" fill="{{ $accent }}"/>

    {{-- Cabecera --}}
    <text x="64" y="118" fill="{{ $accent }}" font-size="58" font-weight="900" letter-spacing="3">FUTGO</text>
    <text x="64" y="166" fill="#9fb4a8" font-size="27" font-weight="600">{{ \Illuminate\Support\Str::limit($subtitle, 48) }}</text>
    <text x="64" y="278" fill="#ffffff" font-size="76" font-weight="900" letter-spacing="1">{{ mb_strtoupper($title) }}</text>

    {{-- Contenido --}}
    <g transform="translate(64, 348)">{{ $slot }}</g>

    {{-- Pie --}}
    <rect x="0" y="1010" width="1080" height="70" fill="#0a1c13"/>
    <text x="64" y="1054" fill="#7d9488" font-size="26" font-weight="600">Donde crece el fútbol amateur</text>
</svg>
