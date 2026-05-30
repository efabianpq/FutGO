@extends('layouts.app')
@section('title', $team->name . ' · ' . $tournament->name)

@section('content')
@php
    $canManage = $isCaptain && $tournament->isOpen();
    $teamStatusMeta = [
        'pending'  => ['Pendiente aprobación', 'upcoming'],
        'approved' => ['Aprobado',             'win'],
        'rejected' => ['Rechazado',            'default'],
    ];
    [$tsLabel, $tsVariant] = $teamStatusMeta[$team->status] ?? [$team->status, 'default'];

    $playerStatusMeta = [
        'active'   => ['Activo',   'win'],
        'inactive' => ['Inactivo', 'default'],
        'pending'  => ['Pendiente','upcoming'],
        'rejected' => ['Rechazado','default'],
    ];
@endphp

<div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

    {{-- Breadcrumb --}}
    <nav class="flex items-center gap-2 font-mono text-[12px] text-ink-mute mb-5">
        <a href="{{ route('torneos.hub', $tournament) }}" class="hover:text-pitch">{{ $tournament->name }}</a>
        <span>›</span>
        <span class="text-pitch font-semibold">Mi equipo</span>
    </nav>

    @if (session('status'))
        <div class="mb-4 bg-gol/20 border border-gol text-pitch-deep px-4 py-3 rounded-md font-display font-semibold">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-4 bg-alerta/15 border border-alerta text-alerta-deep px-4 py-3 rounded-md font-display font-semibold">{{ session('error') }}</div>
    @endif

    {{-- ══ 1. INFORMACIÓN GENERAL ═══════════════════════════════════════════ --}}
    <div class="bg-white border border-line rounded-md shadow-card-2 p-5 sm:p-6 mb-6">
        <div class="flex items-start gap-4">
            @if ($team->shield_url)
                <img src="{{ $team->shield_url }}" alt="{{ $team->name }}" class="w-16 h-16 rounded-full object-cover border border-line shrink-0">
            @else
                <span class="w-16 h-16 rounded-full border border-line shrink-0 flex items-center justify-center font-display font-extrabold text-pitch text-2xl"
                      style="background:{{ $team->color ?? '#E8E8E8' }}">
                    {{ mb_strtoupper(mb_substr($team->name, 0, 1)) }}
                </span>
            @endif
            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-2">
                    <h1 class="font-display font-bold text-display-m text-pitch uppercase break-words">{{ $team->name }}</h1>
                    <x-badge :variant="$tsVariant">{{ $tsLabel }}</x-badge>
                </div>
                <p class="font-mono text-[12px] text-ink-mute mt-1">
                    Capitán: <span class="text-pitch font-semibold">{{ $team->captain?->name ?? '—' }}</span>
                </p>
            </div>
        </div>
    </div>

    {{-- ══ Estadísticas ═════════════════════════════════════════════════════ --}}
    <div class="grid grid-cols-3 sm:grid-cols-6 gap-3 mb-6">
        @foreach ([
            ['PJ', $stats['played'], 'pitch'],
            ['PG', $stats['won'], 'gol'],
            ['PE', $stats['drawn'], 'pitch'],
            ['PP', $stats['lost'], 'alerta'],
            ['GF', $stats['goals_for'], 'pitch'],
            ['GC', $stats['goals_against'], 'pitch'],
        ] as [$lbl, $val, $accent])
            <div class="bg-white border border-line rounded-md shadow-card p-3 text-center border-l-4
                {{ $accent === 'gol' ? 'border-l-gol' : ($accent === 'alerta' ? 'border-l-alerta' : 'border-l-pitch') }}">
                <p class="font-mono text-[10px] uppercase tracking-wide-label text-ink-mute">{{ $lbl }}</p>
                <p class="font-display font-extrabold text-2xl mt-0.5
                    {{ $accent === 'gol' ? 'text-gol-deep' : ($accent === 'alerta' ? 'text-alerta' : 'text-pitch') }}">{{ $val }}</p>
            </div>
        @endforeach
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- ══ 2. PLANTILLA ═════════════════════════════════════════════════ --}}
        <div class="lg:col-span-2 space-y-6">

            {{-- Solicitudes pendientes (capitán) --}}
            @if ($isCaptain && $pendingPlayers->isNotEmpty())
                <section class="bg-white border border-gol/40 rounded-md shadow-card-2 overflow-hidden">
                    <div class="bg-gol/10 border-b border-line px-4 py-3">
                        <p class="font-mono text-[11px] tracking-wide-label uppercase text-pitch">
                            Solicitudes pendientes ({{ $pendingPlayers->count() }})
                        </p>
                    </div>
                    <ul class="divide-y divide-line-soft">
                        @foreach ($pendingPlayers as $tp)
                            <li class="flex items-center justify-between px-4 py-3">
                                <div>
                                    <p class="font-semibold text-[14px]">{{ $tp->user?->name ?? '—' }}</p>
                                    <p class="font-mono text-[11px] text-ink-mute">{{ $tp->position ?? 'Sin posición' }}</p>
                                </div>
                                <div class="flex items-center gap-2">
                                    <form method="POST" action="{{ route('torneos.equipo.players.approve', [$tournament, $tp]) }}">
                                        @csrf
                                        <button type="submit" class="font-display font-bold text-[12px] uppercase text-gol-deep hover:underline tracking-wide-cta">Aprobar</button>
                                    </form>
                                    <span class="text-ink-mute">·</span>
                                    <form method="POST" action="{{ route('torneos.equipo.players.reject', [$tournament, $tp]) }}">
                                        @csrf
                                        <button type="submit" class="font-display font-bold text-[12px] uppercase text-alerta hover:underline tracking-wide-cta">Rechazar</button>
                                    </form>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif

            {{-- Plantilla activa --}}
            <section x-data="{ showAddForm: false }" class="bg-white border border-line rounded-md shadow-card-2 overflow-hidden">
                <div class="bg-pitch-mist border-b border-line px-4 py-3 flex items-center justify-between">
                    <p class="font-mono text-[11px] tracking-wide-label uppercase text-pitch">
                        Plantilla ({{ $activePlayers->count() }})
                    </p>
                    @if ($canManage)
                        <button type="button" @click="showAddForm = !showAddForm"
                                class="font-display font-bold text-[13px] uppercase text-pitch hover:underline tracking-wide-cta">
                            + Agregar jugador
                        </button>
                    @endif
                </div>

                {{-- Form agregar (capitán, torneo abierto) --}}
                @if ($canManage)
                    <div x-show="showAddForm" x-cloak class="border-b border-line-soft bg-bone-soft px-4 py-4">
                        <form method="POST" action="{{ route('torneos.equipo.players.add', $tournament) }}" class="flex flex-wrap items-end gap-3">
                            @csrf
                            <div class="flex flex-col gap-1 flex-1 min-w-[200px]">
                                <label class="font-mono text-[10.5px] tracking-wide-label uppercase text-ink-mute">Email del jugador *</label>
                                <input type="email" name="email" required value="{{ old('email') }}" placeholder="jugador@email.com"
                                       class="h-[40px] px-3 bg-white border-[1.5px] {{ $errors->has('email') ? 'border-alerta' : 'border-line' }} rounded-md text-[14px] focus:border-pitch focus:ring-0">
                                @error('email') <p class="text-[12px] text-alerta">{{ $message }}</p> @enderror
                            </div>
                            <div class="flex flex-col gap-1 w-20">
                                <label class="font-mono text-[10.5px] tracking-wide-label uppercase text-ink-mute">Dorsal</label>
                                <input type="number" name="jersey_number" min="1" max="99" value="{{ old('jersey_number') }}"
                                       class="h-[40px] px-3 bg-white border-[1.5px] border-line rounded-md text-[14px] font-mono focus:border-pitch focus:ring-0">
                            </div>
                            <div class="flex flex-col gap-1 w-32">
                                <label class="font-mono text-[10.5px] tracking-wide-label uppercase text-ink-mute">Posición</label>
                                <input type="text" name="position" maxlength="30" value="{{ old('position') }}" placeholder="Delantero"
                                       class="h-[40px] px-3 bg-white border-[1.5px] border-line rounded-md text-[14px] focus:border-pitch focus:ring-0">
                            </div>
                            <x-btn type="submit" variant="primary" size="sm">Agregar</x-btn>
                        </form>
                    </div>
                @endif

                {{-- Lista --}}
                @if ($activePlayers->isEmpty())
                    <div class="p-8 text-center text-ink-soft">Sin jugadores activos.</div>
                @else
                    <ul class="divide-y divide-line-soft">
                        @foreach ($activePlayers as $tp)
                            @php [$psLabel, $psVariant] = $playerStatusMeta[$tp->status] ?? [$tp->status, 'default']; @endphp
                            <li x-data="{ confirming: false }" class="flex items-center justify-between px-4 py-3 hover:bg-bone-soft">
                                <div class="flex items-center gap-3 min-w-0">
                                    <span class="font-mono text-[13px] text-ink-mute w-8 text-right shrink-0">{{ $tp->jersey_number ? '#' . $tp->jersey_number : '—' }}</span>
                                    <div class="min-w-0">
                                        <a href="{{ route('torneos.estadisticas.jugador', [$tournament, $tp]) }}"
                                           class="font-semibold text-[14px] text-pitch hover:underline">{{ $tp->user?->name ?? '—' }}</a>
                                        <p class="font-mono text-[11px] text-ink-mute flex items-center gap-1.5">
                                            {{ $tp->position ?? '' }}
                                            @if ($tp->user_id === $team->captain_user_id)
                                                <x-badge variant="win">Capitán</x-badge>
                                            @elseif ($tp->status !== 'active')
                                                <x-badge :variant="$psVariant">{{ $psLabel }}</x-badge>
                                            @endif
                                        </p>
                                    </div>
                                </div>

                                @if ($canManage && $tp->user_id !== $team->captain_user_id)
                                    <div class="shrink-0">
                                        <template x-if="!confirming">
                                            <button type="button" @click="confirming = true"
                                                    class="font-display font-semibold text-[12px] uppercase text-alerta hover:underline tracking-wide-cta">Quitar</button>
                                        </template>
                                        <template x-if="confirming">
                                            <div class="flex items-center gap-2">
                                                <span class="text-[12px] text-ink-soft">¿Quitar?</span>
                                                <form method="POST" action="{{ route('torneos.equipo.players.remove', [$tournament, $tp]) }}">
                                                    @csrf @method('DELETE')
                                                    <button type="submit" class="font-display font-bold text-[12px] uppercase text-alerta hover:underline tracking-wide-cta">Sí</button>
                                                </form>
                                                <button type="button" @click="confirming = false"
                                                        class="font-display font-semibold text-[12px] uppercase text-pitch hover:underline tracking-wide-cta">No</button>
                                            </div>
                                        </template>
                                    </div>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

            {{-- ══ 4. ÚLTIMOS RESULTADOS ════════════════════════════════════════ --}}
            <section class="bg-white border border-line rounded-md shadow-card-2 p-5">
                <p class="font-display font-bold text-pitch uppercase text-[15px] mb-4">Últimos resultados</p>
                @if ($recentResults->isEmpty())
                    <p class="text-[13px] text-ink-mute italic">Sin partidos finalizados todavía.</p>
                @else
                    <ul class="divide-y divide-line-soft">
                        @foreach ($recentResults->take(5) as $match)
                            @php
                                $isHome = $match->home_team_id === $team->id;
                                $rival  = $isHome ? $match->awayTeam : $match->homeTeam;
                                $teamScore  = $isHome ? $match->home_score : $match->away_score;
                                $rivalScore = $isHome ? $match->away_score : $match->home_score;
                                $outcome = match (true) {
                                    $match->winner_team_id === $team->id => ['V', 'text-gol-deep'],
                                    $match->winner_team_id === null      => ['E', 'text-ink-soft'],
                                    default                              => ['D', 'text-alerta'],
                                };
                            @endphp
                            <li class="py-2.5 flex items-center gap-3">
                                <span class="font-display font-extrabold text-[13px] w-5 {{ $outcome[1] }}">{{ $outcome[0] }}</span>
                                <span class="flex-1 font-display font-semibold text-pitch text-[13px] truncate">vs {{ $rival?->name ?? 'Por definir' }}</span>
                                <span class="font-display font-extrabold text-[16px] text-pitch">{{ $teamScore }} – {{ $rivalScore }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        </div>

        {{-- ══ 3. PRÓXIMOS PARTIDOS ═════════════════════════════════════════ --}}
        <div class="space-y-6">
            <section class="bg-white border border-line rounded-md shadow-card-2 p-5">
                <p class="font-display font-bold text-pitch uppercase text-[15px] mb-4">Próximos partidos</p>
                <x-torneos.upcoming-matches :matches="$upcomingMatches" :team-id="$team->id" :limit="5" :show-phase="true" />
            </section>

            <div class="text-center">
                <x-btn :href="route('torneos.cronograma.team', [$tournament, $team])" variant="ghost" size="sm">Ver cronograma del equipo</x-btn>
            </div>
        </div>
    </div>
</div>
@endsection
