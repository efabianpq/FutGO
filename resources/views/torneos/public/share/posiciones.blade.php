@php
    $group = optional($phases->first())->groups?->first();
    $rows = $group?->standings ?? collect();
@endphp
<x-share.frame title="Posiciones" :subtitle="$tournament->name">
    @if ($group)
        <text x="0" y="0" fill="#00E676" font-size="30" font-weight="700">{{ mb_strtoupper($group->name) }}</text>
        {{-- Cabecera de columnas --}}
        <g transform="translate(0, 34)">
            <text x="500" y="20" fill="#7d9488" font-size="22" font-weight="600" text-anchor="middle">PJ</text>
            <text x="620" y="20" fill="#7d9488" font-size="22" font-weight="600" text-anchor="middle">DG</text>
            <text x="900" y="20" fill="#7d9488" font-size="22" font-weight="600" text-anchor="end">PTS</text>
        </g>
        @forelse ($rows->take(8) as $i => $s)
            @php $y = 56 + $i * 78; @endphp
            <g transform="translate(0, {{ $y }})">
                <rect x="0" y="0" width="952" height="66" rx="12" fill="{{ $i % 2 ? '#0f3a28' : '#123f2c' }}"/>
                <text x="30" y="44" fill="#00E676" font-size="34" font-weight="900">{{ $s->position }}</text>
                <text x="92" y="44" fill="#ffffff" font-size="30" font-weight="700">{{ \Illuminate\Support\Str::limit($s->team?->name ?? '—', 26) }}</text>
                <text x="500" y="44" fill="#cfe0d6" font-size="28" text-anchor="middle">{{ $s->played }}</text>
                <text x="620" y="44" fill="#cfe0d6" font-size="28" text-anchor="middle">{{ $s->goal_difference }}</text>
                <text x="922" y="46" fill="#00E676" font-size="38" font-weight="900" text-anchor="end">{{ $s->points }}</text>
            </g>
        @empty
            <text x="0" y="100" fill="#9fb4a8" font-size="30" font-weight="600">Aún sin posiciones.</text>
        @endforelse
    @else
        <text x="0" y="60" fill="#9fb4a8" font-size="32" font-weight="600">Este torneo todavía no tiene tabla de grupos.</text>
    @endif
</x-share.frame>
