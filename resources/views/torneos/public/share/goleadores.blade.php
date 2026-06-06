<x-share.frame title="Goleadores" :subtitle="$tournament->name">
    @forelse ($scorers->take(8) as $i => $st)
        @php $y = $i * 80; @endphp
        <g transform="translate(0, {{ $y }})">
            <rect x="0" y="0" width="952" height="68" rx="12" fill="{{ $i % 2 ? '#0f3a28' : '#123f2c' }}"/>
            <text x="30" y="46" fill="#00E676" font-size="38" font-weight="900">{{ $i + 1 }}</text>
            <text x="96" y="38" fill="#ffffff" font-size="32" font-weight="700">{{ \Illuminate\Support\Str::limit($st->teamPlayer?->displayName() ?? '—', 24) }}</text>
            <text x="96" y="60" fill="#9fb4a8" font-size="20" font-weight="500">{{ \Illuminate\Support\Str::limit($st->teamPlayer?->team?->name ?? '', 28) }}</text>
            <text x="922" y="48" fill="#00E676" font-size="44" font-weight="900" text-anchor="end">{{ $st->goals }}</text>
        </g>
    @empty
        <text x="0" y="60" fill="#9fb4a8" font-size="32" font-weight="600">Aún no hay goles registrados.</text>
    @endforelse
</x-share.frame>
