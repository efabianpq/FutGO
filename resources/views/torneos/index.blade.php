@extends('layouts.app')
@section('title', 'Mis Torneos')

@section('content')
@php
    $statusMeta = [
        'draft'       => ['Borrador',    'upcoming'],
        'open'        => ['Inscripción', 'win'],
        'in_progress' => ['En juego',    'live'],
        'finished'    => ['Finalizado',  'default'],
        'cancelled'   => ['Cancelado',   'default'],
    ];
    $formatLabels = [
        'groups_and_knockout' => 'Grupos + Eliminación',
        'knockout_only'       => 'Solo eliminación',
        'round_robin'         => 'Todos contra todos',
    ];
@endphp

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8"
     x-data="{ q: '', match(s){ return this.q === '' || s.toLowerCase().includes(this.q.toLowerCase()); } }">

    <div class="flex flex-wrap items-end justify-between gap-4 mb-6">
        <div>
            <p class="eyebrow">Torneos</p>
            <h1 class="font-display font-bold text-display-s text-pitch uppercase mt-1">Mis Torneos</h1>
            <p class="text-ink-soft text-[13px] mt-1">Los torneos donde participás como administrador, capitán o jugador.</p>
        </div>
        @if ($isTorneoAdmin)
            <div class="flex gap-3">
                <x-btn :href="route('admin.torneos.create')" variant="primary">+ Nuevo torneo</x-btn>
            </div>
        @endif
    </div>

    @if ($cards->isNotEmpty())
        <div class="mb-5"><x-search-input placeholder="Buscar torneo por nombre…" /></div>
    @endif

    @if ($cards->isEmpty())
        <div class="bg-white border border-line rounded-md shadow-card-2 p-10 text-center">
            <p class="text-ink-soft text-lg">Todavía no participás en ningún torneo.</p>
            @if ($isTorneoAdmin)
                <p class="text-[13px] text-ink-mute mt-2">Creá tu primer torneo para empezar a gestionarlo.</p>
                <div class="mt-4">
                    <x-btn :href="route('admin.torneos.create')" variant="accent">Crear mi primer torneo</x-btn>
                </div>
            @else
                <p class="text-[13px] text-ink-mute mt-2">Cuando un organizador te sume a un equipo, lo vas a ver acá.</p>
            @endif
        </div>
    @else
        <div class="grid grid-cols-1 gap-6">
            @foreach ($cards as $card)
                @php
                    $t = $card['tournament'];
                    [$label, $variant] = $statusMeta[$t->status] ?? [$t->status, 'default'];
                    $next = $card['next_match'];
                @endphp

                <article class="bg-white border border-line rounded-md shadow-card-2 overflow-hidden flex flex-col"
                         x-show="match(@js($t->name))" x-cloak>
                    {{-- Encabezado --}}
                    <div class="p-5 border-b border-line-soft">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <a href="{{ route('torneos.hub', $t) }}"
                                   class="font-display font-bold text-pitch text-[16px] uppercase leading-tight hover:underline block truncate">
                                    {{ $t->name }}
                                </a>
                                <p class="font-mono text-[11px] text-ink-mute mt-1">
                                    {{ ucfirst($t->sport) }} · {{ $formatLabels[$t->format] ?? $t->format }}
                                </p>
                            </div>
                            <div class="flex flex-col items-end gap-1 shrink-0">
                                <x-badge :variant="$variant">{{ $label }}</x-badge>
                                @if ($card['manages'])
                                    <span class="font-mono text-[10px] uppercase tracking-wide-label text-gol-deep">Administrás</span>
                                @elseif ($card['is_captain'])
                                    <span class="font-mono text-[10px] uppercase tracking-wide-label text-pitch">Capitán</span>
                                @elseif ($card['my_team'])
                                    <span class="font-mono text-[10px] uppercase tracking-wide-label text-ink-mute">Jugador</span>
                                @endif
                            </div>
                        </div>
                    </div>

                    {{-- Datos rápidos --}}
                    <div class="grid grid-cols-3 divide-x divide-line-soft border-b border-line-soft text-center">
                        <div class="px-3 py-3">
                            <p class="font-display font-extrabold text-2xl text-pitch">{{ $t->approved_teams_count }}</p>
                            <p class="font-mono text-[10px] uppercase tracking-wide-label text-ink-mute">Equipos</p>
                        </div>
                        <div class="px-3 py-3">
                            <p class="font-display font-bold text-[13px] text-pitch leading-tight mt-1 truncate" title="{{ $card['active_phase']?->name }}">
                                {{ $card['active_phase']?->name ?? '—' }}
                            </p>
                            <p class="font-mono text-[10px] uppercase tracking-wide-label text-ink-mute mt-1">Fase activa</p>
                        </div>
                        <div class="px-3 py-3">
                            @if ($next)
                                <p class="font-display font-semibold text-[12px] text-pitch leading-tight truncate">
                                    {{ $next->homeTeam?->name ?? 'Por definir' }} vs {{ $next->awayTeam?->name ?? 'Por definir' }}
                                </p>
                                <p class="font-mono text-[10px] text-ink-mute mt-0.5">
                                    {{ $next->scheduled_at ? $next->scheduled_at->format('d/m H:i') : 'Sin fecha' }}
                                </p>
                            @else
                                <p class="font-display font-bold text-[13px] text-ink-mute mt-1">—</p>
                                <p class="font-mono text-[10px] uppercase tracking-wide-label text-ink-mute mt-1">Próximo partido</p>
                            @endif
                        </div>
                    </div>

                    {{-- Accesos directos (v2.0 H8/H11: tarjeta simplificada) --}}
                    <div class="p-5 mt-auto">
                        <div class="flex flex-wrap gap-2">
                            @if ($card['manages'])
                                {{-- Admin del torneo: Resumen (hub) + Panel de Control (gestión). --}}
                                <a href="{{ route('torneos.hub', $t) }}"
                                   class="px-3 py-1.5 rounded-md border border-pitch text-pitch font-display font-semibold text-[12px] uppercase tracking-wide-label hover:bg-pitch hover:text-bone transition-all duration-fast">
                                    Resumen
                                </a>
                                <a href="{{ route('admin.torneos.show', $t) }}"
                                   class="px-3 py-1.5 rounded-md bg-pitch text-bone font-display font-semibold text-[12px] uppercase tracking-wide-label hover:bg-pitch-deep transition-all duration-fast">
                                    Panel de Control
                                </a>
                            @else
                                {{-- Jugador / capitán: un único acceso al torneo. --}}
                                <a href="{{ route('torneos.hub', $t) }}"
                                   class="px-3 py-1.5 rounded-md bg-pitch text-bone font-display font-semibold text-[12px] uppercase tracking-wide-label hover:bg-pitch-deep transition-all duration-fast">
                                    Ver torneo
                                </a>
                            @endif
                        </div>
                    </div>
                </article>
            @endforeach
        </div>
    @endif
</div>
@endsection
