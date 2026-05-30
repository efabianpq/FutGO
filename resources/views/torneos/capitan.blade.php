@extends('layouts.app')
@section('title', 'Panel del Capitán')

@section('content')
@php
    $teamStatusMeta = [
        'pending'  => ['Pendiente', 'upcoming'],
        'approved' => ['Aprobado',  'win'],
        'rejected' => ['Rechazado', 'default'],
    ];
@endphp

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

    <div class="mb-8">
        <p class="eyebrow">Portal del capitán</p>
        <h1 class="font-display font-bold text-display-m text-pitch uppercase mt-1">Panel del Capitán</h1>
        <p class="text-ink-soft text-[14px] mt-1">Tu centro de control: plantilla, jugadores, partidos y seguimiento.</p>
    </div>

    @if (session('status'))
        <div class="mb-4 bg-gol/20 border border-gol text-pitch-deep px-4 py-3 rounded-md font-display font-semibold">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-4 bg-alerta/15 border border-alerta text-alerta-deep px-4 py-3 rounded-md font-display font-semibold">{{ session('error') }}</div>
    @endif

    @foreach ($teamCards as $card)
        @php
            $team = $card['team'];
            $tournament = $card['tournament'];
            [$tsl, $tsv] = $teamStatusMeta[$team->status] ?? [$team->status, 'default'];
        @endphp

        <section class="bg-white border border-line rounded-md shadow-card-2 overflow-hidden mb-8">

            {{-- Cabecera del equipo --}}
            <div class="p-5 border-b border-line flex flex-wrap items-start justify-between gap-4 bg-pitch-mist">
                <div class="flex items-center gap-3 min-w-0">
                    @if ($team->color)
                        <span class="inline-block w-5 h-5 rounded-full border border-line shrink-0" style="background:{{ $team->color }}"></span>
                    @endif
                    <div class="min-w-0">
                        <h2 class="font-display font-bold text-pitch text-display-s uppercase leading-tight truncate">{{ $team->name }}</h2>
                        <p class="font-mono text-[11px] text-ink-mute mt-0.5">{{ $tournament?->name }}</p>
                    </div>
                    <x-badge :variant="$tsv">{{ $tsl }}</x-badge>
                </div>
                <div class="flex gap-2">
                    <x-btn :href="route('torneos.equipo.show', $tournament)" variant="primary" size="sm">Gestionar plantilla</x-btn>
                    <x-btn :href="route('torneos.hub', $tournament)" variant="ghost" size="sm">Hub</x-btn>
                </div>
            </div>

            {{-- Estadísticas del equipo --}}
            <div class="grid grid-cols-3 sm:grid-cols-6 divide-x divide-line-soft border-b border-line text-center">
                @foreach ([
                    ['PJ', $card['stats']['played']],
                    ['PG', $card['stats']['won']],
                    ['PE', $card['stats']['drawn']],
                    ['PP', $card['stats']['lost']],
                    ['GF', $card['stats']['goals_for']],
                    ['GC', $card['stats']['goals_against']],
                ] as [$lbl, $val])
                    <div class="px-2 py-3">
                        <p class="font-display font-extrabold text-2xl text-pitch">{{ $val }}</p>
                        <p class="font-mono text-[10px] uppercase tracking-wide-label text-ink-mute">{{ $lbl }}</p>
                    </div>
                @endforeach
            </div>

            <div class="p-5 grid grid-cols-1 lg:grid-cols-3 gap-6">

                {{-- Mi equipo: plantilla --}}
                <div>
                    <p class="font-display font-bold text-pitch uppercase text-[14px] mb-3">Plantilla
                        <span class="font-mono text-[11px] text-ink-mute">({{ $card['approved']->count() }})</span>
                    </p>
                    @if ($card['approved']->isEmpty() && $card['inactive']->isEmpty())
                        <p class="text-[13px] text-ink-mute italic">Todavía no hay jugadores aprobados.</p>
                    @else
                        <ul class="space-y-1.5">
                            @foreach ($card['approved'] as $p)
                                <li class="flex items-center gap-2 text-[13px]">
                                    <span class="font-mono text-[11px] text-ink-mute w-6 text-right">#{{ $p->jersey_number ?? '–' }}</span>
                                    <span class="font-display font-semibold text-pitch truncate">{{ $p->user?->name ?? 'Jugador' }}</span>
                                    @if ($p->user_id === $team->captain_user_id)
                                        <span class="font-mono text-[9px] uppercase tracking-wide-label text-gol-deep">©</span>
                                    @endif
                                    @if ($p->position)<span class="font-mono text-[10px] text-ink-mute">{{ $p->position }}</span>@endif
                                </li>
                            @endforeach
                            @foreach ($card['inactive'] as $p)
                                <li class="flex items-center gap-2 text-[13px] opacity-70">
                                    <span class="font-mono text-[11px] text-ink-mute w-6 text-right">#{{ $p->jersey_number ?? '–' }}</span>
                                    <span class="font-display font-semibold text-ink-soft truncate line-through">{{ $p->user?->name ?? 'Jugador' }}</span>
                                    <span class="font-mono text-[9px] uppercase tracking-wide-label text-alerta">🟥 suspendido</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>

                {{-- Gestión de jugadores --}}
                <div>
                    <p class="font-display font-bold text-pitch uppercase text-[14px] mb-3">Gestión de jugadores</p>

                    <p class="font-mono text-[10px] uppercase tracking-wide-label text-gol-deep mb-1">Pendientes ({{ $card['pending']->count() }})</p>
                    @if ($card['pending']->isEmpty())
                        <p class="text-[12px] text-ink-mute italic mb-3">Sin solicitudes pendientes.</p>
                    @else
                        <ul class="space-y-2 mb-3">
                            @foreach ($card['pending'] as $p)
                                <li class="flex items-center justify-between gap-2 text-[13px] bg-bone-soft rounded-md px-2.5 py-1.5">
                                    <span class="font-display font-semibold text-pitch truncate">{{ $p->user?->name ?? 'Jugador' }}</span>
                                    <span class="flex gap-1 shrink-0">
                                        <form method="POST" action="{{ route('torneos.equipo.players.approve', [$tournament, $p]) }}">
                                            @csrf
                                            <button type="submit" class="font-mono text-[11px] text-gol-deep hover:underline">Aprobar</button>
                                        </form>
                                        <form method="POST" action="{{ route('torneos.equipo.players.reject', [$tournament, $p]) }}"
                                              x-data @submit.prevent="if (confirm('¿Rechazar esta solicitud?')) $el.submit()">
                                            @csrf
                                            <button type="submit" class="font-mono text-[11px] text-alerta hover:underline">Rechazar</button>
                                        </form>
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    <p class="font-mono text-[10px] uppercase tracking-wide-label text-ink-mute mb-1">Rechazados ({{ $card['rejected']->count() }})</p>
                    @if ($card['rejected']->isEmpty())
                        <p class="text-[12px] text-ink-mute italic">Ninguno.</p>
                    @else
                        <ul class="space-y-1">
                            @foreach ($card['rejected'] as $p)
                                <li class="text-[12px] text-ink-mute truncate">{{ $p->user?->name ?? 'Jugador' }}</li>
                            @endforeach
                        </ul>
                    @endif
                </div>

                {{-- Partidos --}}
                <div>
                    <p class="font-display font-bold text-pitch uppercase text-[14px] mb-3">Partidos</p>

                    <p class="font-mono text-[10px] uppercase tracking-wide-label text-ink-mute mb-1">Próximos</p>
                    <x-torneos.upcoming-matches :matches="$card['upcoming']" :limit="3" :team-id="$team->id" :show-phase="true" />

                    <p class="font-mono text-[10px] uppercase tracking-wide-label text-ink-mute mt-4 mb-1">Resultados recientes</p>
                    @if ($card['recent']->isEmpty())
                        <p class="text-[12px] text-ink-mute italic">Sin resultados aún.</p>
                    @else
                        <ul class="divide-y divide-line-soft">
                            @foreach ($card['recent'] as $m)
                                <li class="py-1.5 text-[12px] flex items-center justify-between gap-2">
                                    <span class="truncate">{{ $m->homeTeam?->name ?? '—' }}
                                        <span class="font-mono font-bold text-pitch">{{ $m->home_score }}–{{ $m->away_score }}</span>
                                        {{ $m->awayTeam?->name ?? '—' }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>

            {{-- Seguimiento: alertas --}}
            @if (! empty($card['alerts']))
                <div class="px-5 pb-5">
                    <div class="bg-alerta/10 border border-alerta/30 rounded-md px-4 py-3">
                        <p class="font-display font-bold text-alerta uppercase text-[12px] mb-1">⚠ Seguimiento</p>
                        <ul class="list-disc list-inside space-y-0.5 text-[13px] text-ink-soft">
                            @foreach ($card['alerts'] as $alert)
                                <li>{{ $alert }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @endif
        </section>
    @endforeach
</div>
@endsection
