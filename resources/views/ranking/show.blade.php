@extends('layouts.app')

@section('title', 'Auditoría — ' . $participant->name)

@section('content')
<div class="max-w-5xl mx-auto px-4 py-8">
    <a href="{{ route('ranking.index') }}" class="font-display font-bold text-[13px] uppercase tracking-wide-cta text-pitch hover:underline">
        ← Volver al ranking
    </a>

    {{-- Header con stats del participante --}}
    <div class="bg-white border border-line rounded-md shadow-card p-6 sm:p-8 mt-4 mb-8">
        <div class="grid grid-cols-1 sm:grid-cols-4 gap-6">
            <div class="sm:col-span-2">
                <p class="font-mono text-[11px] tracking-wide-label uppercase text-ink-mute">Participante</p>
                <h1 class="font-display font-bold text-display-m text-pitch uppercase mt-1">{{ $participant->name }}</h1>
            </div>
            @if ($ranking)
                <div>
                    <p class="font-mono text-[11px] tracking-wide-label uppercase text-ink-mute">Posición</p>
                    <p class="font-display font-extrabold text-display-m mt-1">
                        @if ($ranking->current_position === 1)
                            <span class="text-gol-deep">🥇 1</span>
                        @elseif ($ranking->current_position === 2)
                            <span class="text-[#8a8a8a]">🥈 2</span>
                        @elseif ($ranking->current_position === 3)
                            <span class="text-[#b87333]">🥉 3</span>
                        @else
                            <span class="text-ink">{{ $ranking->current_position ?? '—' }}</span>
                        @endif
                    </p>
                </div>
                <div>
                    <p class="font-mono text-[11px] tracking-wide-label uppercase text-ink-mute">Puntos · Exactos</p>
                    <p class="font-display font-extrabold text-display-m text-pitch mt-1">
                        {{ $ranking->total_points }}<span class="text-display-s text-ink-soft"> · {{ $ranking->exact_predictions }}🎯</span>
                    </p>
                </div>
            @endif
        </div>
    </div>

    @if ($totalFinished === 0)
        <div class="bg-white border border-line rounded-md shadow-card p-10 sm:p-16 text-center">
            <div class="text-5xl mb-4">⏳</div>
            <p class="font-display font-bold text-display-s text-pitch uppercase">Aún no hay partidos finalizados para este participante</p>
            <p class="font-mono text-[11px] tracking-wide-label uppercase text-ink-mute mt-3">La auditoría aparecerá cuando el administrador cargue los primeros resultados oficiales.</p>
        </div>
    @else
        @foreach ($phases as $phase)
            <section class="mb-8">
                <header class="flex items-end justify-between mb-3 pb-2 border-b-2 border-pitch">
                    <h2 class="font-display font-bold text-display-s text-pitch uppercase">{{ $phase['label'] }}</h2>
                    <span class="font-mono text-[11px] tracking-wide-label uppercase text-ink-mute">{{ count($phase['rows']) }} partidos</span>
                </header>

                <div class="bg-white border border-line rounded-md shadow-card overflow-hidden">
                    <table class="w-full">
                        <thead class="bg-pitch text-bone">
                            <tr class="font-mono text-[10.5px] tracking-wide-label uppercase text-left">
                                <th class="px-4 py-2.5">#</th>
                                <th class="px-4 py-2.5">Partido</th>
                                <th class="px-4 py-2.5 text-right">Pronóstico</th>
                                <th class="px-4 py-2.5 text-right">Resultado</th>
                                <th class="px-4 py-2.5 text-right">Puntos</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line-soft">
                            @foreach ($phase['rows'] as $row)
                                @php
                                    $pts = (int) $row['points_earned'];
                                    $badgeVariant = match ($pts) {
                                        5, 3 => 'win',
                                        2 => 'default',
                                        1 => 'win',
                                        default => 'upcoming',
                                    };
                                @endphp
                                <tr class="hover:bg-bone-soft transition-colors duration-fast">
                                    <td class="px-4 py-3 font-mono text-[11px] tracking-wide-eyebrow uppercase text-ink-mute">#{{ $row['match_number'] }}</td>
                                    <td class="px-4 py-3">
                                        <p class="font-display font-bold text-body uppercase tracking-[.02em]">
                                            {{ $row['home_flag'] }} {{ $row['home_team'] }}
                                            <span class="text-ink-mute font-body normal-case">vs</span>
                                            {{ $row['away_flag'] }} {{ $row['away_team'] }}
                                        </p>
                                        <p class="font-mono text-[10.5px] tracking-wide-label uppercase text-ink-mute mt-1">
                                            @if ($row['group_name']) Grupo {{ $row['group_name'] }} · @endif
                                            {{ $row['date_label'] }} GMT-5
                                        </p>
                                    </td>
                                    <td class="px-4 py-3 text-right font-display font-extrabold text-display-s">
                                        @if ($row['prediction'])
                                            <span class="text-pitch">{{ $row['prediction'] }}</span>
                                        @else
                                            <span class="font-body font-normal text-body-s text-ink-mute italic">Sin pronóstico</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-right font-display font-extrabold text-display-s text-ink">{{ $row['official'] }}</td>
                                    <td class="px-4 py-3 text-right">
                                        <x-badge :variant="$badgeVariant">{{ $pts }} pts</x-badge>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endforeach
    @endif
</div>
@endsection
