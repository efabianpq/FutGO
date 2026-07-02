@extends('layouts.app')
@section('title', $venue->name)

@section('content')
<div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

    {{-- Cabecera --}}
    <div class="bg-white border border-line rounded-md shadow-card p-6 mb-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="eyebrow">Cancha</p>
                <h1 class="font-display font-bold text-display-s text-pitch uppercase mt-1">{{ $venue->name }}</h1>
                <p class="text-ink-soft text-[14px] mt-1">{{ $venue->city }}{{ $venue->address ? ' · ' . $venue->address : '' }}</p>
            </div>
            @auth
                @if ($venue->canBeEditedBy(auth()->user()))
                    <a href="{{ route('social.canchas.edit', $venue->slug) }}" class="btn btn-secondary btn-sm">Editar</a>
                @endif
            @endauth
        </div>

        {{-- Datos descriptivos --}}
        <dl class="mt-4 grid grid-cols-2 sm:grid-cols-4 gap-4">
            @if ($venue->surface_type)
                <div>
                    <dt class="font-mono text-[10px] tracking-wide-label uppercase text-ink-mute">Superficie</dt>
                    <dd class="text-[14px] text-pitch-deep font-semibold mt-0.5">{{ $venue->surfaceLabel() }}</dd>
                </div>
            @endif
            @if ($venue->approx_capacity)
                <div>
                    <dt class="font-mono text-[10px] tracking-wide-label uppercase text-ink-mute">Capacidad aprox.</dt>
                    <dd class="text-[14px] text-pitch-deep font-semibold mt-0.5">{{ number_format($venue->approx_capacity) }}</dd>
                </div>
            @endif
            <div>
                <dt class="font-mono text-[10px] tracking-wide-label uppercase text-ink-mute">Disponibilidad</dt>
                <dd class="mt-0.5">
                    @if ($isOccupied)
                        <span class="text-[13px] font-semibold text-amber-700">Ocupada próximamente</span>
                    @else
                        <span class="text-[13px] font-semibold text-gol">Disponible</span>
                    @endif
                </dd>
            </div>
            <div>
                <dt class="font-mono text-[10px] tracking-wide-label uppercase text-ink-mute">Registrada por</dt>
                <dd class="text-[14px] text-ink-soft mt-0.5">{{ $venue->registeredBy->name }}</dd>
            </div>
        </dl>

        @if ($venue->maps_url)
            <a href="{{ $venue->maps_url }}" target="_blank" rel="noopener noreferrer"
               class="mt-4 inline-flex items-center gap-1.5 text-[13px] text-pitch hover:underline">
                <x-icon name="map-pin" class="w-4 h-4" /> Ver ubicación en el mapa
            </a>
        @endif
    </div>

    {{-- Próximos partidos (disponibilidad) --}}
    @if ($upcoming->isNotEmpty())
        <div class="bg-white border border-line rounded-md shadow-card p-5 mb-6">
            <h2 class="font-display font-semibold text-[15px] text-pitch uppercase mb-3">Próximos partidos aquí</h2>
            <ul class="divide-y divide-line">
                @foreach ($upcoming as $match)
                    <li class="py-2.5 flex items-center justify-between gap-2">
                        <div>
                            <span class="font-semibold text-[14px]">{{ $match->homeClub->name }}</span>
                            <span class="text-ink-mute mx-1">vs</span>
                            <span class="font-semibold text-[14px]">{{ $match->awayClub->name }}</span>
                        </div>
                        <span class="text-ink-soft text-[12px] shrink-0">{{ $match->scheduled_at?->format('d/m/Y H:i') }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Historial de partidos jugados --}}
    <div class="bg-white border border-line rounded-md shadow-card p-5">
        <h2 class="font-display font-semibold text-[15px] text-pitch uppercase mb-3">Partidos jugados aquí</h2>
        @if ($played->isEmpty())
            <p class="text-ink-mute text-[13px]">Todavía no se jugaron amistosos registrados en esta cancha.</p>
        @else
            <ul class="divide-y divide-line">
                @foreach ($played as $match)
                    <li class="py-2.5 flex items-center justify-between gap-2">
                        <div>
                            <span class="font-semibold text-[14px]">{{ $match->homeClub->name }}</span>
                            <span class="text-ink-mute mx-1">
                                {{ $match->final_home_score }} – {{ $match->final_away_score }}
                            </span>
                            <span class="font-semibold text-[14px]">{{ $match->awayClub->name }}</span>
                        </div>
                        <span class="text-ink-soft text-[12px] shrink-0">{{ $match->scheduled_at?->format('d/m/Y') }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

</div>
@endsection
