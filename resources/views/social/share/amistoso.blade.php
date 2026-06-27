<x-share.frame title="Amistoso FutGO" subtitle="Resultado del partido">
    <text x="476" y="40" fill="#9fb4a8" font-size="26" font-weight="600" text-anchor="middle">AMISTOSO</text>

    {{-- Local --}}
    <text x="476" y="170" fill="#ffffff" font-size="46" font-weight="800" text-anchor="middle">{{ \Illuminate\Support\Str::limit($match->homeClub?->name ?? '—', 24) }}</text>

    {{-- Marcador --}}
    <g transform="translate(0, 300)">
        <text x="360" y="80" fill="#00E676" font-size="150" font-weight="900" text-anchor="middle">{{ $match->final_home_score ?? 0 }}</text>
        <text x="476" y="64" fill="#7d9488" font-size="64" font-weight="700" text-anchor="middle">-</text>
        <text x="592" y="80" fill="#00E676" font-size="150" font-weight="900" text-anchor="middle">{{ $match->final_away_score ?? 0 }}</text>
    </g>

    {{-- Visitante --}}
    <text x="476" y="540" fill="#ffffff" font-size="46" font-weight="800" text-anchor="middle">{{ \Illuminate\Support\Str::limit($match->awayClub?->name ?? '—', 24) }}</text>

    @if ($match->scheduled_at)
        <text x="476" y="610" fill="#9fb4a8" font-size="26" font-weight="500" text-anchor="middle">{{ \Carbon\Carbon::parse($match->scheduled_at)->locale('es')->isoFormat('D [de] MMMM YYYY') }}</text>
    @endif

    @if ($match->venue)
        <text x="476" y="645" fill="#7d9488" font-size="22" font-weight="500" text-anchor="middle">📍 {{ \Illuminate\Support\Str::limit($match->venue->name, 36) }}</text>
    @elseif ($match->location)
        <text x="476" y="645" fill="#7d9488" font-size="22" font-weight="500" text-anchor="middle">📍 {{ \Illuminate\Support\Str::limit($match->location, 36) }}</text>
    @endif
</x-share.frame>
