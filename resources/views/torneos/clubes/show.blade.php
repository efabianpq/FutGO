@extends('layouts.app')
@section('title', $club->name . ' · Club')

@section('content')
@php
    $statusMeta = [
        'draft'       => ['Borrador',    'upcoming'],
        'open'        => ['Inscripción', 'win'],
        'in_progress' => ['En juego',    'live'],
        'finished'    => ['Finalizado',  'default'],
        'cancelled'   => ['Cancelado',   'default'],
    ];
    $canManage = auth()->user()->isAdmin() || $club->created_by_user_id === auth()->id();
@endphp

<div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

    @if (session('status'))
        <div class="mb-4 bg-gol/20 border border-gol text-pitch-deep px-4 py-3 rounded-md font-display font-semibold">{{ session('status') }}</div>
    @endif

    {{-- Encabezado del club --}}
    <div class="bg-white border border-line rounded-md shadow-card-2 p-6 mb-6 flex flex-wrap items-center gap-5">
        <x-avatar :name="$club->name" :src="$club->shield_url" size="xl" />
        <div class="min-w-0 flex-1">
            <p class="eyebrow">🛡️ Equipo</p>
            <h1 class="font-display font-bold text-display-m text-pitch uppercase mt-1 break-words">{{ $club->name }}</h1>
            <p class="font-mono text-[12px] text-ink-mute mt-1">
                Capitán: {{ $club->captain?->name ?? '—' }} · {{ $participations->count() }} participación(es) · {{ $players->count() }} jugadores
            </p>
        </div>
        @if ($canManage)
            <x-btn :href="route('torneos.clubes.manage', $club)" variant="primary" size="sm">Gestionar plantilla</x-btn>
        @endif
    </div>

    {{-- Stats acumuladas --}}
    <p class="font-mono text-[11px] tracking-wide-label uppercase text-ink-mute mb-3">Estadísticas acumuladas (todos los torneos)</p>
    <div class="grid grid-cols-3 sm:grid-cols-6 gap-3 mb-8">
        @foreach ([
            ['PJ', $agg['played'], 'pitch'],
            ['PG', $agg['won'], 'gol'],
            ['PE', $agg['drawn'], 'pitch'],
            ['PP', $agg['lost'], 'alerta'],
            ['GF', $agg['goals_for'], 'pitch'],
            ['GC', $agg['goals_against'], 'pitch'],
        ] as [$lbl, $val, $accent])
            <div class="bg-white border border-line rounded-md shadow-card p-3 text-center border-l-4
                {{ $accent === 'gol' ? 'border-l-gol' : ($accent === 'alerta' ? 'border-l-alerta' : 'border-l-pitch') }}">
                <p class="font-mono text-[10px] uppercase tracking-wide-label text-ink-mute">{{ $lbl }}</p>
                <p class="font-display font-extrabold text-2xl mt-0.5
                    {{ $accent === 'gol' ? 'text-gol-deep' : ($accent === 'alerta' ? 'text-alerta' : 'text-pitch') }}">{{ $val }}</p>
            </div>
        @endforeach
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Historial de participaciones --}}
        <section class="bg-white border border-line rounded-md shadow-card-2 overflow-hidden">
            <div class="bg-pitch-mist border-b border-line px-4 py-3">
                <p class="font-mono text-[11px] tracking-wide-label uppercase text-pitch">Historial de participaciones</p>
            </div>
            @if ($participations->isEmpty())
                <div class="p-6 text-center text-ink-soft text-[14px]">Sin participaciones registradas.</div>
            @else
                <ul class="divide-y divide-line-soft">
                    @foreach ($participations as $team)
                        @php [$lbl, $variant] = $statusMeta[$team->tournament->status] ?? [$team->tournament->status, 'default']; @endphp
                        <li class="px-4 py-3 flex items-center justify-between gap-3">
                            <div class="min-w-0">
                                <a href="{{ route('torneos.hub', $team->tournament) }}" class="font-display font-semibold text-pitch text-[14px] hover:underline truncate block">{{ $team->tournament->name }}</a>
                                <p class="font-mono text-[11px] text-ink-mute">Inscripto como {{ $team->name }}</p>
                            </div>
                            <x-badge :variant="$variant">{{ $lbl }}</x-badge>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        {{-- Goleadores históricos --}}
        <section class="bg-white border border-line rounded-md shadow-card-2 overflow-hidden">
            <div class="bg-pitch-mist border-b border-line px-4 py-3">
                <p class="font-mono text-[11px] tracking-wide-label uppercase text-pitch">Goleadores históricos</p>
            </div>
            @if ($topScorers->isEmpty())
                <div class="p-6 text-center text-ink-soft text-[14px]">Sin goles registrados todavía.</div>
            @else
                <ul class="divide-y divide-line-soft">
                    @foreach ($topScorers as $row)
                        <li class="px-4 py-3 flex items-center justify-between">
                            <span class="font-display font-semibold text-pitch text-[14px]">{{ $row['name'] }}</span>
                            <span class="font-display font-extrabold text-gol-deep text-[16px]">{{ $row['goals'] }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>

    {{-- Jugadores históricos --}}
    <section class="bg-white border border-line rounded-md shadow-card-2 overflow-hidden mt-6">
        <div class="bg-pitch-mist border-b border-line px-4 py-3">
            <p class="font-mono text-[11px] tracking-wide-label uppercase text-pitch">Jugadores históricos ({{ $players->count() }})</p>
        </div>
        @if ($players->isEmpty())
            <div class="p-6 text-center text-ink-soft text-[14px]">Sin jugadores registrados.</div>
        @else
            <ul class="divide-y divide-line-soft">
                @foreach ($players as $tp)
                    <li class="px-4 py-3 flex items-center gap-3">
                        <x-avatar :user="$tp->user" :name="$tp->displayName()" size="sm" />
                        <div class="min-w-0">
                            <p class="font-display font-semibold text-pitch text-[14px] truncate">{{ $tp->displayName() }}</p>
                            <p class="font-mono text-[11px] text-ink-mute">{{ $tp->position ?? 'Sin posición' }}</p>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</div>
@endsection
