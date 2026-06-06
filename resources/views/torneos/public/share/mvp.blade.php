<x-share.frame title="MVP de la fecha" :subtitle="$tournament->name">
    @if ($match && $match->mvp)
        <circle cx="120" cy="160" r="120" fill="#123f2c" stroke="#00E676" stroke-width="6"/>
        <text x="120" y="195" fill="#00E676" font-size="110" font-weight="900" text-anchor="middle">★</text>

        <text x="300" y="120" fill="#ffffff" font-size="56" font-weight="900">{{ \Illuminate\Support\Str::limit($match->mvp->displayName(), 22) }}</text>
        <text x="300" y="176" fill="#9fb4a8" font-size="32" font-weight="600">{{ \Illuminate\Support\Str::limit($match->mvp->team?->name ?? '', 30) }}</text>

        <g transform="translate(0, 320)">
            <rect x="0" y="0" width="952" height="120" rx="16" fill="#0f3a28"/>
            <text x="40" y="56" fill="#9fb4a8" font-size="24" font-weight="600">FIGURA DEL PARTIDO</text>
            <text x="40" y="98" fill="#ffffff" font-size="34" font-weight="700">{{ \Illuminate\Support\Str::limit($match->homeTeam?->name ?? '—', 16) }} {{ $match->home_score }} - {{ $match->away_score }} {{ \Illuminate\Support\Str::limit($match->awayTeam?->name ?? '—', 16) }}</text>
        </g>
    @else
        <text x="0" y="60" fill="#9fb4a8" font-size="32" font-weight="600">Sin figura del partido designada todavía.</text>
    @endif
</x-share.frame>
