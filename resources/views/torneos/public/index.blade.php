@extends('layouts.app')
@section('title', 'Torneos')

@section('content')
@php
    $formatLabels = [
        'groups_and_knockout' => 'Grupos + Eliminación',
        'knockout_only'       => 'Solo eliminación',
        'round_robin'         => 'Todos contra todos',
        'liga'                => 'Liga / Abierto',
    ];

    $card = function ($t) use ($formatLabels) {
        return $t;
    };
@endphp

<div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

    <div class="mb-8">
        <p class="eyebrow">Torneos</p>
        <h1 class="font-display font-bold text-display-m text-pitch uppercase mt-1">Explorar torneos</h1>
        <p class="text-ink-soft text-[14px] mt-1">
            Descubrí torneos públicos: inscribí tu equipo en los que están abiertos o seguí los que están en juego.
        </p>
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
                    <article class="bg-white border border-line rounded-md shadow-card-2 overflow-hidden flex flex-col">
                        @if ($t->banner_url)
                            <img src="{{ $t->banner_url }}" alt="" class="h-24 w-full object-cover">
                        @endif
                        <div class="p-5 flex flex-col flex-1">
                            <div class="flex items-start gap-3">
                                @if ($t->logo_url)
                                    <img src="{{ $t->logo_url }}" alt="" class="h-12 w-12 object-cover rounded-md border border-line shrink-0">
                                @endif
                                <div class="min-w-0">
                                    <a href="{{ route('torneos.public.show', $t) }}" class="font-display font-bold text-pitch text-[16px] uppercase leading-tight hover:underline block truncate">{{ $t->name }}</a>
                                    <p class="font-mono text-[11px] text-ink-mute mt-1">
                                        {{ ucfirst($t->sport) }} · {{ $formatLabels[$t->format] ?? $t->format }}
                                    </p>
                                </div>
                                <span class="ml-auto shrink-0"><x-badge variant="win">Inscripción</x-badge></span>
                            </div>

                            <dl class="grid grid-cols-2 gap-y-1.5 gap-x-4 mt-4 text-[13px]">
                                @if ($t->city)
                                    <div class="flex justify-between col-span-2"><dt class="text-ink-mute">Ciudad</dt><dd class="font-semibold text-pitch">{{ $t->city }}</dd></div>
                                @endif
                                <div class="flex justify-between col-span-2">
                                    <dt class="text-ink-mute">Equipos</dt>
                                    <dd class="font-semibold text-pitch">{{ $t->approved_teams_count }} / {{ $t->max_teams }}</dd>
                                </div>
                                <div class="flex justify-between col-span-2">
                                    <dt class="text-ink-mute">Inscripción</dt>
                                    <dd class="font-semibold text-pitch">{{ (int) $t->registration_fee > 0 ? '$' . number_format($t->registration_fee, 0, ',', '.') : 'Gratuita' }}</dd>
                                </div>
                                @if ($t->starts_at)
                                    <div class="flex justify-between col-span-2"><dt class="text-ink-mute">Inicio</dt><dd class="font-mono text-pitch">{{ $t->starts_at->format('d/m/Y') }}</dd></div>
                                @endif
                            </dl>

                            <div class="flex flex-wrap gap-2 mt-4 pt-4 border-t border-line-soft mt-auto">
                                <x-btn :href="route('torneos.public.show', $t)" variant="ghost" size="sm">Ver detalle</x-btn>
                                @auth
                                    @if ($isCaptain)
                                        <x-btn :href="route('torneos.equipo.inscribir', $t)" variant="primary" size="sm">Inscribir mi equipo</x-btn>
                                    @else
                                        <x-btn :href="route('torneos.mis-equipos')" variant="primary" size="sm">Creá tu equipo para inscribirte</x-btn>
                                    @endif
                                @else
                                    <x-btn :href="route('login')" variant="primary" size="sm">Ingresá para inscribirte</x-btn>
                                @endauth
                            </div>
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
                    <article class="bg-white border border-line rounded-md shadow-card-2 overflow-hidden flex flex-col">
                        @if ($t->banner_url)
                            <img src="{{ $t->banner_url }}" alt="" class="h-24 w-full object-cover">
                        @endif
                        <div class="p-5 flex flex-col flex-1">
                            <div class="flex items-start gap-3">
                                @if ($t->logo_url)
                                    <img src="{{ $t->logo_url }}" alt="" class="h-12 w-12 object-cover rounded-md border border-line shrink-0">
                                @endif
                                <div class="min-w-0">
                                    <a href="{{ route('torneos.public.show', $t) }}" class="font-display font-bold text-pitch text-[16px] uppercase leading-tight hover:underline block truncate">{{ $t->name }}</a>
                                    <p class="font-mono text-[11px] text-ink-mute mt-1">
                                        {{ ucfirst($t->sport) }} · {{ $formatLabels[$t->format] ?? $t->format }}
                                    </p>
                                </div>
                                <span class="ml-auto shrink-0"><x-badge variant="live">En juego</x-badge></span>
                            </div>

                            <dl class="grid grid-cols-2 gap-y-1.5 gap-x-4 mt-4 text-[13px]">
                                @if ($t->city)
                                    <div class="flex justify-between col-span-2"><dt class="text-ink-mute">Ciudad</dt><dd class="font-semibold text-pitch">{{ $t->city }}</dd></div>
                                @endif
                                <div class="flex justify-between col-span-2">
                                    <dt class="text-ink-mute">Equipos</dt>
                                    <dd class="font-semibold text-pitch">{{ $t->approved_teams_count }}</dd>
                                </div>
                            </dl>

                            <div class="mt-4 pt-4 border-t border-line-soft mt-auto">
                                <x-btn :href="route('torneos.public.show', $t)" variant="primary" size="sm">Ver torneo en vivo</x-btn>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </section>
</div>
@endsection
