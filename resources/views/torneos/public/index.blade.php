@extends('layouts.app')
@section('title', 'Buscar Torneo')

@section('content')
@php
    $formatLabels = [
        'groups_and_knockout' => 'Grupos + Eliminación',
        'knockout_only'       => 'Solo eliminación',
        'round_robin'         => 'Todos contra todos',
        'liga'                => 'Liga / Abierto',
    ];
@endphp

<div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8"
     x-data="{ q: '', city: '', match(name, city) {
        const okName = this.q === '' || name.toLowerCase().includes(this.q.toLowerCase());
        const okCity = this.city === '' || city === this.city;
        return okName && okCity;
     } }">

    <div class="mb-6">
        <p class="eyebrow">Torneos</p>
        <h1 class="font-display font-bold text-display-s sm:text-display-m text-pitch uppercase mt-1">Buscar Torneo</h1>
        <p class="text-ink-soft text-[14px] mt-1">
            Descubrí torneos públicos: inscribí tu equipo en los que están abiertos o seguí los que están en juego.
        </p>
    </div>

    {{-- Herramienta de búsqueda y filtro --}}
    <div class="bg-white border border-line rounded-md shadow-card p-4 mb-8 flex flex-col sm:flex-row gap-3">
        <div class="relative flex-1">
            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-ink-mute pointer-events-none">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="M21 21l-4.3-4.3"/></svg>
            </span>
            <input type="text" x-model="q" placeholder="Buscar por nombre del torneo…"
                   class="w-full h-[44px] pl-10 pr-3 bg-white border-[1.5px] border-line rounded-md text-[15px] focus:border-pitch focus:ring-0">
        </div>
        @php $cities = $open->merge($inProgress)->pluck('city')->filter()->unique()->sort()->values(); @endphp
        @if ($cities->isNotEmpty())
            <select x-model="city" class="h-[44px] px-3 bg-white border-[1.5px] border-line rounded-md text-[15px] focus:border-pitch focus:ring-0 sm:w-52">
                <option value="">Todas las ciudades</option>
                @foreach ($cities as $c)
                    <option value="{{ $c }}">{{ $c }}</option>
                @endforeach
            </select>
        @endif
    </div>

    {{-- ── Inscripciones abiertas ──────────────────────────────────────────── --}}
    <section class="mb-10">
        <div class="flex items-center gap-2 mb-4">
            <span class="w-2.5 h-2.5 rounded-full bg-gol"></span>
            <h2 class="font-display font-bold text-pitch uppercase text-[16px]">Inscripciones abiertas</h2>
            <span class="font-mono text-[12px] text-ink-mute">({{ $open->count() }})</span>
        </div>

        @if ($open->isEmpty())
            <div class="bg-white border border-line rounded-md shadow-card p-8 text-center text-ink-soft text-[14px]">
                No hay torneos con inscripciones abiertas en este momento.
            </div>
        @else
            <div class="grid grid-cols-1 gap-5">
                @foreach ($open as $t)
                    @php
                        $fee = (int) $t->registration_fee > 0
                            ? '$' . number_format($t->registration_fee, 0, ',', '.')
                            : 'Gratuita';
                        $pct = $t->max_teams > 0
                            ? min(100, ($t->approved_teams_count / $t->max_teams) * 100)
                            : 0;
                    @endphp
                    <article class="bg-white border border-line rounded-md shadow-card-2 overflow-hidden"
                             x-show="match(@js($t->name), @js($t->city ?? ''))" x-cloak>
                        @if ($t->banner_url)
                            <img src="{{ $t->banner_url }}" alt="" class="h-24 w-full object-cover">
                        @endif

                        {{-- Cabecera: logo · nombre · badge --}}
                        <div class="px-5 pt-5 pb-3 flex items-start justify-between gap-3">
                            <div class="flex items-start gap-3 min-w-0 flex-1">
                                @if ($t->logo_url)
                                    <img src="{{ $t->logo_url }}" alt="" class="h-10 w-10 object-cover rounded-md border border-line shrink-0">
                                @endif
                                <div class="min-w-0">
                                    <a href="{{ route('torneos.public.show', $t) }}"
                                       class="font-display font-bold text-pitch text-[16px] uppercase leading-tight hover:underline block">{{ $t->name }}</a>
                                    <p class="font-mono text-[11px] text-ink-mute mt-0.5">
                                        {{ ucfirst($t->sport) }} · {{ $formatLabels[$t->format] ?? $t->format }}
                                    </p>
                                </div>
                            </div>
                            <span class="shrink-0 flex flex-col items-end gap-1">
                                @if ($isPlatformAdmin && $t->visibility !== 'public')
                                    <x-badge variant="default">Privado</x-badge>
                                @endif
                                <x-badge variant="win">Inscripción</x-badge>
                            </span>
                        </div>

                        {{-- Franja de datos: 4 col en desktop, 2×2 en móvil --}}
                        <div class="grid grid-cols-2 sm:grid-cols-4 border-t border-b border-line-soft">
                            <div class="px-4 py-3 border-r border-line-soft">
                                <p class="font-mono text-[10px] uppercase tracking-wide-label text-ink-mute">Ciudad</p>
                                <p class="font-display font-semibold text-pitch text-[14px] mt-0.5 truncate">{{ $t->city ?: '—' }}</p>
                            </div>
                            <div class="px-4 py-3 sm:border-r border-line-soft">
                                <p class="font-mono text-[10px] uppercase tracking-wide-label text-ink-mute">Equipos</p>
                                <p class="font-display font-semibold text-pitch text-[14px] mt-0.5">{{ $t->approved_teams_count }} / {{ $t->max_teams }}</p>
                            </div>
                            <div class="px-4 py-3 border-t sm:border-t-0 border-r border-line-soft">
                                <p class="font-mono text-[10px] uppercase tracking-wide-label text-ink-mute">Inscripción</p>
                                <p class="font-display font-semibold text-pitch text-[14px] mt-0.5">{{ $fee }}</p>
                            </div>
                            <div class="px-4 py-3 border-t sm:border-t-0 border-line-soft">
                                <p class="font-mono text-[10px] uppercase tracking-wide-label text-ink-mute">Inicio</p>
                                <p class="font-display font-semibold text-pitch text-[14px] mt-0.5">
                                    {{ $t->starts_at ? $t->starts_at->format('d/m/Y') : '—' }}
                                </p>
                            </div>
                        </div>

                        {{-- Footer: botones + barra de cupos --}}
                        <div class="px-5 py-4 flex flex-wrap items-center gap-3">
                            <div class="flex flex-wrap gap-2">
                                <x-btn :href="route('torneos.public.show', $t)" variant="ghost" size="sm">Ver detalle</x-btn>
                                @auth
                                    @if ($isCaptain)
                                        <x-btn :href="route('torneos.equipo.inscribir', $t)" variant="primary" size="sm">Inscribir equipo</x-btn>
                                    @else
                                        <x-btn :href="route('torneos.mis-equipos')" variant="primary" size="sm">Creá tu equipo para inscribirte</x-btn>
                                    @endif
                                @else
                                    <x-btn :href="route('login')" variant="primary" size="sm">Ingresá para inscribirte</x-btn>
                                @endauth
                            </div>
                            @if ($t->max_teams > 0)
                                <div class="ml-auto flex items-center gap-2 shrink-0">
                                    <div class="w-20 h-1.5 bg-line rounded-full overflow-hidden">
                                        <div class="h-full bg-gol rounded-full" style="width:{{ $pct }}%"></div>
                                    </div>
                                    <span class="font-mono text-[11px] text-ink-mute whitespace-nowrap">{{ $t->approved_teams_count }} de {{ $t->max_teams }} cupos</span>
                                </div>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </section>

    {{-- ── En juego ─────────────────────────────────────────────────────────── --}}
    <section>
        <div class="flex items-center gap-2 mb-4">
            <span class="w-2.5 h-2.5 rounded-full bg-alerta animate-pulse"></span>
            <h2 class="font-display font-bold text-pitch uppercase text-[16px]">En juego</h2>
            <span class="font-mono text-[12px] text-ink-mute">({{ $inProgress->count() }})</span>
        </div>

        @if ($inProgress->isEmpty())
            <div class="bg-white border border-line rounded-md shadow-card p-8 text-center text-ink-soft text-[14px]">
                No hay torneos en juego públicos en este momento.
            </div>
        @else
            <div class="grid grid-cols-1 gap-5">
                @foreach ($inProgress as $t)
                    @php
                        $activePhase = $t->phases->first();
                        $matchPct = $t->total_matches_count > 0
                            ? min(100, ($t->finished_matches_count / $t->total_matches_count) * 100)
                            : 0;
                    @endphp
                    <article class="bg-white border border-line rounded-md shadow-card-2 overflow-hidden"
                             x-show="match(@js($t->name), @js($t->city ?? ''))" x-cloak>
                        @if ($t->banner_url)
                            <img src="{{ $t->banner_url }}" alt="" class="h-24 w-full object-cover">
                        @endif

                        {{-- Cabecera: logo · nombre · badge --}}
                        <div class="px-5 pt-5 pb-3 flex items-start justify-between gap-3">
                            <div class="flex items-start gap-3 min-w-0 flex-1">
                                @if ($t->logo_url)
                                    <img src="{{ $t->logo_url }}" alt="" class="h-10 w-10 object-cover rounded-md border border-line shrink-0">
                                @endif
                                <div class="min-w-0">
                                    <a href="{{ route('torneos.public.show', $t) }}"
                                       class="font-display font-bold text-pitch text-[16px] uppercase leading-tight hover:underline block">{{ $t->name }}</a>
                                    <p class="font-mono text-[11px] text-ink-mute mt-0.5">
                                        {{ ucfirst($t->sport) }} · {{ $formatLabels[$t->format] ?? $t->format }}
                                    </p>
                                </div>
                            </div>
                            <span class="shrink-0 flex flex-col items-end gap-1">
                                @if ($isPlatformAdmin && $t->visibility !== 'public')
                                    <x-badge variant="default">Privado</x-badge>
                                @endif
                                <x-badge variant="live">En juego</x-badge>
                            </span>
                        </div>

                        {{-- Franja de datos: 4 col en desktop, 2×2 en móvil --}}
                        <div class="grid grid-cols-2 sm:grid-cols-4 border-t border-b border-line-soft">
                            <div class="px-4 py-3 border-r border-line-soft">
                                <p class="font-mono text-[10px] uppercase tracking-wide-label text-ink-mute">Ciudad</p>
                                <p class="font-display font-semibold text-pitch text-[14px] mt-0.5 truncate">{{ $t->city ?: '—' }}</p>
                            </div>
                            <div class="px-4 py-3 sm:border-r border-line-soft">
                                <p class="font-mono text-[10px] uppercase tracking-wide-label text-ink-mute">Equipos</p>
                                <p class="font-display font-semibold text-pitch text-[14px] mt-0.5">{{ $t->approved_teams_count }}</p>
                            </div>
                            <div class="px-4 py-3 border-t sm:border-t-0 border-r border-line-soft">
                                <p class="font-mono text-[10px] uppercase tracking-wide-label text-ink-mute">Partidos</p>
                                <p class="font-display font-semibold text-pitch text-[14px] mt-0.5">
                                    {{ $t->finished_matches_count }} / {{ $t->total_matches_count }}
                                </p>
                            </div>
                            <div class="px-4 py-3 border-t sm:border-t-0 border-line-soft">
                                <p class="font-mono text-[10px] uppercase tracking-wide-label text-ink-mute">Fase</p>
                                <p class="font-display font-semibold text-pitch text-[14px] mt-0.5 truncate">
                                    {{ $activePhase?->name ?? '—' }}
                                </p>
                            </div>
                        </div>

                        {{-- Footer: botón + barra de progreso --}}
                        <div class="px-5 py-4 flex flex-wrap items-center gap-3">
                            <x-btn :href="route('torneos.public.show', $t)" variant="primary" size="sm">Ver torneo en vivo</x-btn>
                            @if ($t->total_matches_count > 0)
                                <div class="ml-auto flex items-center gap-2 shrink-0">
                                    <div class="w-20 h-1.5 bg-line rounded-full overflow-hidden">
                                        <div class="h-full bg-alerta rounded-full" style="width:{{ $matchPct }}%"></div>
                                    </div>
                                    <span class="font-mono text-[11px] text-ink-mute whitespace-nowrap">
                                        {{ $t->finished_matches_count }} de {{ $t->total_matches_count }} jugados
                                    </span>
                                </div>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </section>
</div>
@endsection
