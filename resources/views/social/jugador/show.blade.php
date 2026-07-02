@extends('layouts.public')
@section('title', $player->name . ' · Jugador · FutGO')

@php
    $ogDesc = $player->name;
    if ($player->play_level) {
        $ogDesc .= ' · ' . (['recreativo'=>'Recreativo','intermedio'=>'Intermedio','competitivo'=>'Competitivo','elite_amateur'=>'Élite Amateur'][$player->play_level] ?? $player->play_level);
    }
    if ($player->city) {
        $ogDesc .= ' · ' . $player->city;
    }
    if ($career) {
        $ogDesc .= ' · ' . $career->matches_played . ' partidos · ' . $career->goals . ' goles';
    }
    $ogDesc .= ' en FutGO.';
@endphp
@section('og_description', $ogDesc)
@if ($player->avatar_url)
    @section('og_image', $player->avatar_url)
@endif

@section('content')
@php
    $levelLabels = [
        'recreativo'    => 'Recreativo',
        'intermedio'    => 'Intermedio',
        'competitivo'   => 'Competitivo',
        'elite_amateur' => 'Élite Amateur',
    ];
@endphp
<div class="max-w-3xl mx-auto px-4 py-6">

    {{-- Cabecera del jugador --}}
    <div class="bg-white border border-line rounded-md shadow-card-2 p-5 mb-5 flex flex-wrap sm:flex-nowrap items-center gap-4">
        <x-avatar :name="$player->name" :src="$player->avatar_url" size="xl" class="shrink-0" />
        <div class="min-w-0 flex-1">
            <p class="eyebrow">Jugador</p>
            <h1 class="font-display font-bold text-2xl sm:text-display-s text-pitch uppercase mt-1 break-words">{{ $player->name }}</h1>
            <p class="font-mono text-[12px] text-ink-mute mt-1">
                {{ $player->futgo_id }}
                @if ($player->play_level)
                    · {{ $levelLabels[$player->play_level] ?? $player->play_level }}
                @endif
                @if ($player->city)
                    · {{ $player->city }}
                @endif
            </p>
            @if ($isSuspended)
                <span class="inline-block mt-2 text-[11px] font-mono uppercase tracking-wide-label text-alerta bg-alerta/10 px-2 py-0.5 rounded">Cuenta suspendida</span>
            @endif
        </div>
        <div class="w-full sm:w-auto shrink-0 flex flex-col items-end gap-2">
            <p class="text-[12px] text-ink-soft">{{ $followersCount }} {{ Str::plural('seguidor', $followersCount) }}</p>
            @auth
                @if (auth()->id() !== $player->id)
                    <x-social.follow-button :followable="$player" type="user" />
                    {{-- H20: Mensajería directa --}}
                    @if ($canDirectMessage)
                        @if ($existingDmConversation)
                            <a href="{{ route('social.conversaciones.show', $existingDmConversation) }}"
                               class="btn btn-secondary btn-sm">💬 Ver conversación</a>
                        @else
                            <form method="POST" action="{{ route('social.conversaciones.direct', $player) }}">
                                @csrf
                                <button type="submit" class="btn btn-secondary btn-sm">💬 Enviar mensaje</button>
                            </form>
                        @endif
                    @endif
                @endif
            @endauth
        </div>
    </div>

    {{-- S2-A "Jugué con vos": dato derivado + acciones directas (retar / invitar) --}}
    @auth
        @if (auth()->id() !== $player->id)
            <div class="bg-white border border-line rounded-md shadow-card p-4 mb-5">
                <div class="flex items-center gap-2 mb-3">
                    <span class="text-lg">⚽</span>
                    @if ($sharedCount > 0)
                        <p class="font-display font-semibold text-pitch text-[14px]">
                            Jugaste <span class="text-gol-deep font-bold">{{ $sharedCount }}</span> {{ Str::plural('vez', $sharedCount) }} con {{ $player->name }}
                        </p>
                    @else
                        <p class="font-display font-semibold text-ink-soft text-[14px]">Todavía no compartieron cancha. ¡Armá un partido!</p>
                    @endif
                </div>
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('social.oportunidades.create', ['tipo' => 'BUSCAR_RIVAL', 'target' => $player->futgo_id]) }}"
                       class="btn btn-primary btn-sm">Retar a un amistoso</a>
                    <a href="{{ route('social.oportunidades.create', ['tipo' => 'BUSCAR_JUGADOR', 'target' => $player->futgo_id]) }}"
                       class="btn btn-secondary btn-sm">Invitar a mi equipo</a>
                </div>
                @unless ($viewerIsCaptain)
                    <p class="font-mono text-[11px] text-ink-mute mt-2">Para retar o invitar necesitás ser capitán de un equipo.</p>
                @endunless
            </div>
        @endif
    @endauth

    {{-- Score de confiabilidad (solo si supera umbral) --}}
    @if ($reliabilityScore)
        <div class="bg-gol/10 border border-gol/30 rounded-md px-4 py-3 mb-5 flex items-center gap-3">
            <span class="font-display font-bold text-2xl text-gol-deep">{{ $reliabilityScore->score }}</span>
            <div>
                <p class="font-display font-semibold text-pitch text-[14px]">Score de confiabilidad</p>
                <p class="text-ink-soft text-[12px]">Basado en asistencia y puntualidad en los últimos 90 días.</p>
            </div>
        </div>
    @endif

    {{-- Métricas sociales --}}
    @if ($friendlyMetrics['total_matches'] > 0 || ($career && $career->matches_played > 0))
        <p class="font-mono text-[11px] tracking-wide-label uppercase text-ink-mute mb-3">Métricas deportivas</p>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-6">
            <div class="bg-white border border-line rounded-md shadow-card p-4 text-center">
                <p class="font-display font-bold text-2xl text-pitch">{{ $friendlyMetrics['total_matches'] }}</p>
                <p class="text-ink-mute text-[11px] font-mono uppercase tracking-wide-label mt-1">Partidos totales</p>
            </div>
            <div class="bg-white border border-line rounded-md shadow-card p-4 text-center">
                <p class="font-display font-bold text-2xl text-pitch">{{ $seasons->count() }}</p>
                <p class="text-ink-mute text-[11px] font-mono uppercase tracking-wide-label mt-1">Torneos</p>
            </div>
            <div class="bg-white border border-line rounded-md shadow-card p-4 text-center">
                <p class="font-display font-bold text-2xl text-pitch">{{ $friendlyMetrics['friendlies_played'] }}</p>
                <p class="text-ink-mute text-[11px] font-mono uppercase tracking-wide-label mt-1">Amistosos</p>
            </div>
            <div class="bg-white border border-line rounded-md shadow-card p-4 text-center">
                @php $pct = $friendlyMetrics['presentation_pct'] ?? null; @endphp
                <p class="font-display font-bold text-2xl text-pitch">{{ $pct !== null ? $pct . '%' : '—' }}</p>
                <p class="text-ink-mute text-[11px] font-mono uppercase tracking-wide-label mt-1">Asistencia</p>
            </div>
        </div>
    @endif

    {{-- Stats acumuladas de torneos --}}
    @if ($career && $career->matches_played > 0)
        <p class="font-mono text-[11px] tracking-wide-label uppercase text-ink-mute mb-3">Stats de torneos</p>
        <div class="grid grid-cols-3 sm:grid-cols-6 gap-2 mb-6">
            @foreach ([
                ['PJ', $career->matches_played],
                ['G', $career->goals],
                ['A', $career->assists ?? 0],
                ['MVP', $career->mvp_count ?? 0],
                ['TA', $career->yellow_cards ?? 0],
                ['TR', $career->red_cards ?? 0],
            ] as [$label, $val])
                <div class="bg-white border border-line rounded-md shadow-card p-3 text-center">
                    <p class="font-display font-bold text-lg text-pitch">{{ $val }}</p>
                    <p class="text-ink-mute text-[10px] font-mono uppercase tracking-wide-label mt-0.5">{{ $label }}</p>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Logros desbloqueados --}}
    @if ($achievements->isNotEmpty())
        <p class="font-mono text-[11px] tracking-wide-label uppercase text-ink-mute mb-3">Logros ({{ $achievements->count() }})</p>
        <div class="flex flex-wrap gap-2 mb-6">
            @foreach ($achievements as $ach)
                <div class="bg-white border border-line rounded-md shadow-card px-3 py-2 flex items-center gap-2" title="{{ $ach->description }}">
                    @if ($ach->icon)
                        <span class="text-lg">{{ $ach->icon }}</span>
                    @endif
                    <span class="font-display font-semibold text-pitch text-[13px]">{{ $ach->name }}</span>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Oportunidades abiertas BUSCAR_EQUIPO --}}
    @if ($openOpportunities->isNotEmpty())
        <p class="font-mono text-[11px] tracking-wide-label uppercase text-ink-mute mb-3">Busca equipo</p>
        <div class="flex flex-col gap-3 mb-6">
            @foreach ($openOpportunities as $op)
                <a href="{{ route('social.oportunidades.show', $op) }}"
                   class="bg-white border border-line rounded-md shadow-card p-4 hover:shadow-card-2 transition-shadow flex items-center justify-between gap-3">
                    <div>
                        <p class="font-display font-semibold text-pitch text-[14px]">Busca equipo</p>
                        <p class="text-ink-soft text-[12px] mt-0.5">
                            {{ $op->city }}
                            @if ($op->required_level)
                                · {{ $levelLabels[$op->required_level] ?? $op->required_level }}
                            @endif
                        </p>
                    </div>
                    <span class="font-mono text-[11px] text-gol uppercase tracking-wide-label">Abierta</span>
                </a>
            @endforeach
        </div>
    @endif

    {{-- Historial de temporadas --}}
    @if ($seasons->isNotEmpty())
        <p class="font-mono text-[11px] tracking-wide-label uppercase text-ink-mute mb-3">Historial de temporadas</p>
        <div class="bg-white border border-line rounded-md shadow-card overflow-hidden overflow-x-auto mb-6">
            <table class="w-full text-[13px]">
                <thead class="bg-pitch-mist">
                    <tr>
                        <th class="text-left px-4 py-3 font-mono text-[11px] uppercase tracking-wide-label text-ink-mute">Torneo</th>
                        <th class="text-left px-4 py-3 font-mono text-[11px] uppercase tracking-wide-label text-ink-mute hidden sm:table-cell">Equipo</th>
                        <th class="text-right px-4 py-3 font-mono text-[11px] uppercase tracking-wide-label text-ink-mute">PJ</th>
                        <th class="text-right px-4 py-3 font-mono text-[11px] uppercase tracking-wide-label text-ink-mute">G</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @foreach ($seasons as $tp)
                        @php
                            $t = $tp->team->tournament;
                            $c = $tp->team->club;
                            $s = $tp->playerStat;
                        @endphp
                        <tr class="hover:bg-pitch-mist/40">
                            <td class="px-4 py-3">
                                @if ($t->visibility === 'public')
                                    <a href="{{ route('torneos.public.show', $t) }}" class="text-pitch hover:underline font-semibold">{{ $t->name }}</a>
                                @else
                                    <span class="font-semibold text-pitch">{{ $t->name }}</span>
                                @endif
                                <span class="ml-2 text-[11px] font-mono text-ink-mute">{{ $t->status === 'finished' ? 'Fin.' : 'En curso' }}</span>
                            </td>
                            <td class="px-4 py-3 hidden sm:table-cell text-ink-soft">{{ $c?->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-right">{{ $s?->matches_played ?? '—' }}</td>
                            <td class="px-4 py-3 text-right">{{ $s?->goals ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if ($seasons->isEmpty() && $achievements->isEmpty() && $friendlyMetrics['total_matches'] === 0)
        <div class="bg-white border border-line rounded-md shadow-card p-6 text-center text-ink-soft text-[14px]">
            Este jugador aún no tiene actividad registrada en FutGO.
        </div>
    @endif

    <p class="text-center text-ink-mute text-[11px] font-mono mt-6">
        <a href="{{ route('home') }}" class="hover:underline">← FutGO</a>
    </p>

</div>
@endsection
