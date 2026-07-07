@extends('layouts.app')
@section('title', $term !== '' ? 'Buscar: ' . $term : 'Buscar')

@section('content')
<div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

    {{-- Encabezado + caja de búsqueda --}}
    <div class="mb-6">
        <p class="font-mono text-[11px] tracking-[.14em] uppercase text-muted">FutGO</p>
        <div class="flex items-center gap-2 mt-1">
            <h1 class="font-display font-bold text-2xl text-text">Buscar en FutGO</h1>
            <x-help-hint topic="social.search" />
        </div>
        <form method="GET" action="{{ route('social.search') }}" class="mt-4 flex items-center gap-2">
            <div class="relative flex-1">
                <svg class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-muted" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"/>
                </svg>
                <input type="search" name="q" value="{{ $term }}" autofocus placeholder="Jugadores, clubes, torneos o canchas…"
                       class="w-full pl-9 pr-3 py-2.5 rounded-md bg-surface border border-border text-text text-[14px] placeholder:text-muted focus:outline-none focus:border-green">
            </div>
            <button type="submit" class="btn btn-primary btn-sm">Buscar</button>
        </form>
    </div>

    @if ($term !== '' && mb_strlen($term) < 2)
        <p class="text-muted text-[14px]">Escribe al menos 2 caracteres.</p>
    @elseif ($term !== '' && $total === 0)
        <div class="bg-surface border border-border rounded-md p-8 text-center">
            <p class="font-display font-semibold text-text text-[16px]">Sin resultados para «{{ $term }}».</p>
            <p class="text-muted text-[13px] mt-2">Prueba con otro nombre, ciudad o identificador FUTGO.</p>
        </div>
    @elseif ($term !== '')
        <div class="flex flex-col gap-6">

            {{-- Jugadores --}}
            @if ($players->isNotEmpty())
                <section>
                    <p class="font-mono text-[11px] tracking-wide-label uppercase text-muted mb-2 pl-1">Jugadores</p>
                    <div class="bg-surface border border-border rounded-md overflow-hidden divide-y divide-border">
                        @foreach ($players as $player)
                            <a href="{{ route('social.player.show', $player->futgo_id) }}" class="flex items-center gap-3 px-4 py-3 hover:bg-surface-2 transition-colors">
                                <x-avatar :user="$player" size="sm" />
                                <div class="min-w-0 flex-1">
                                    <p class="font-display font-semibold text-text text-[14px] truncate">{{ $player->name }}</p>
                                    <p class="font-mono text-[11px] text-muted truncate">
                                        {{ $player->futgo_id }}@if ($player->city) · {{ $player->city }}@endif
                                    </p>
                                </div>
                                <svg class="w-4 h-4 text-muted shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                            </a>
                        @endforeach
                    </div>
                </section>
            @endif

            {{-- Clubes --}}
            @if ($clubs->isNotEmpty())
                <section>
                    <p class="font-mono text-[11px] tracking-wide-label uppercase text-muted mb-2 pl-1">Clubes</p>
                    <div class="bg-surface border border-border rounded-md overflow-hidden divide-y divide-border">
                        @foreach ($clubs as $club)
                            <a href="{{ route('torneos.clubes.show', $club) }}" class="flex items-center gap-3 px-4 py-3 hover:bg-surface-2 transition-colors">
                                @if ($club->shield_url)
                                    <img src="{{ $club->shield_url }}" alt="" class="w-9 h-9 rounded-full object-cover bg-surface-2 shrink-0">
                                @else
                                    <span class="w-9 h-9 rounded-full bg-surface-2 flex items-center justify-center text-muted shrink-0"><x-icon name="ball" class="w-4 h-4" /></span>
                                @endif
                                <div class="min-w-0 flex-1">
                                    <p class="font-display font-semibold text-text text-[14px] truncate">{{ $club->name }}</p>
                                    <p class="font-mono text-[11px] text-muted truncate">Club</p>
                                </div>
                                <svg class="w-4 h-4 text-muted shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                            </a>
                        @endforeach
                    </div>
                </section>
            @endif

            {{-- Torneos --}}
            @if ($tournaments->isNotEmpty())
                <section>
                    <p class="font-mono text-[11px] tracking-wide-label uppercase text-muted mb-2 pl-1">Torneos</p>
                    <div class="bg-surface border border-border rounded-md overflow-hidden divide-y divide-border">
                        @foreach ($tournaments as $tournament)
                            <a href="{{ route('torneos.public.show', $tournament) }}" class="flex items-center gap-3 px-4 py-3 hover:bg-surface-2 transition-colors">
                                <span class="w-9 h-9 rounded-full bg-surface-2 flex items-center justify-center text-muted shrink-0"><x-icon name="trophy" class="w-4 h-4" /></span>
                                <div class="min-w-0 flex-1">
                                    <p class="font-display font-semibold text-text text-[14px] truncate">{{ $tournament->name }}</p>
                                    <p class="font-mono text-[11px] text-muted truncate">Torneo · {{ ucfirst($tournament->status) }}</p>
                                </div>
                                <svg class="w-4 h-4 text-muted shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                            </a>
                        @endforeach
                    </div>
                </section>
            @endif

            {{-- Canchas --}}
            @if ($venues->isNotEmpty())
                <section>
                    <p class="font-mono text-[11px] tracking-wide-label uppercase text-muted mb-2 pl-1">Canchas</p>
                    <div class="bg-surface border border-border rounded-md overflow-hidden divide-y divide-border">
                        @foreach ($venues as $venue)
                            <a href="{{ route('social.canchas.show', $venue) }}" class="flex items-center gap-3 px-4 py-3 hover:bg-surface-2 transition-colors">
                                <span class="w-9 h-9 rounded-full bg-surface-2 flex items-center justify-center text-muted shrink-0"><x-icon name="map-pin" class="w-4 h-4" /></span>
                                <div class="min-w-0 flex-1">
                                    <p class="font-display font-semibold text-text text-[14px] truncate">{{ $venue->name }}</p>
                                    <p class="font-mono text-[11px] text-muted truncate">Cancha{{ $venue->city ? ' · ' . $venue->city : '' }}</p>
                                </div>
                                <svg class="w-4 h-4 text-muted shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                            </a>
                        @endforeach
                    </div>
                </section>
            @endif

        </div>
    @else
        <p class="text-muted text-[14px]">Encuentra jugadores por su nombre o identificador FUTGO, clubes, torneos públicos y canchas.</p>
    @endif

</div>
@endsection
