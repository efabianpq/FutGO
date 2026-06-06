<x-share.frame title="Resultado" :subtitle="$tournament->name">
    <text x="476" y="40" fill="#9fb4a8" font-size="26" font-weight="600" text-anchor="middle">{{ mb_strtoupper($match->phase?->name ?? 'Partido') }}@if ($match->isWalkover()) · W.O.@endif</text>

    {{-- Local --}}
    <text x="476" y="170" fill="#ffffff" font-size="46" font-weight="800" text-anchor="middle">{{ \Illuminate\Support\Str::limit($match->homeTeam?->name ?? '—', 24) }}</text>

    {{-- Marcador --}}
    <g transform="translate(0, 300)">
        <text x="360" y="80" fill="#00E676" font-size="150" font-weight="900" text-anchor="middle">{{ $match->home_score }}</text>
        <text x="476" y="64" fill="#7d9488" font-size="64" font-weight="700" text-anchor="middle">-</text>
        <text x="592" y="80" fill="#00E676" font-size="150" font-weight="900" text-anchor="middle">{{ $match->away_score }}</text>
    </g>

    {{-- Visitante --}}
    <text x="476" y="540" fill="#ffffff" font-size="46" font-weight="800" text-anchor="middle">{{ \Illuminate\Support\Str::limit($match->awayTeam?->name ?? '—', 24) }}</text>

    @if ($match->scheduled_at)
        <text x="476" y="610" fill="#9fb4a8" font-size="26" font-weight="500" text-anchor="middle">{{ \Carbon\Carbon::parse($match->scheduled_at)->locale('es')->isoFormat('D [de] MMMM YYYY') }}</text>
    @endif
</x-share.frame>
