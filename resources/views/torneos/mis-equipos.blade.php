@extends('layouts.app')
@section('title', 'Mis Equipos')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8"
     x-data="{ showCreate: {{ $errors->any() ? 'true' : 'false' }}, q: '', match(s){ return this.q === '' || s.toLowerCase().includes(this.q.toLowerCase()); } }">

    <div class="flex flex-wrap items-end justify-between gap-4 mb-8">
        <div>
            <p class="eyebrow">Equipos</p>
            <h1 class="font-display font-bold text-display-s sm:text-display-m text-pitch uppercase mt-1">Mis Equipos</h1>
            @if ($isPlatformAdmin)
                <p class="text-ink-soft text-[14px] mt-1">Como administrador de la plataforma ves todos los equipos registrados.</p>
            @else
                <p class="text-ink-soft text-[14px] mt-1">Tus equipos permanentes: los que dirigís como capitán y aquellos donde jugás. Un equipo es transversal a los torneos.</p>
            @endif
        </div>
        <x-btn type="button" variant="primary" x-on:click="showCreate = !showCreate">+ Crear equipo</x-btn>
    </div>

    @if (session('status'))
        <div class="mb-4 bg-gol/20 border border-gol text-pitch-deep px-4 py-3 rounded-md font-display font-semibold">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-4 bg-alerta/15 border border-alerta text-alerta-deep px-4 py-3 rounded-md font-display font-semibold">{{ session('error') }}</div>
    @endif

    {{-- Crear equipo --}}
    <div x-show="showCreate" x-cloak class="bg-white border border-line rounded-md shadow-card-2 p-5 sm:p-6 mb-6">
        <p class="font-mono text-[11px] tracking-wide-label uppercase text-ink-mute mb-3">Nuevo equipo permanente</p>
        <form method="POST" action="{{ route('torneos.equipos.store') }}" class="flex flex-wrap items-end gap-3">
            @csrf
            <div class="flex flex-col gap-1 flex-1 min-w-[220px]">
                <label class="font-mono text-[10.5px] tracking-wide-label uppercase text-ink-mute">Nombre del equipo *</label>
                <input type="text" name="name" value="{{ old('name') }}" required minlength="3" maxlength="80" placeholder="Los Halcones"
                       class="h-[42px] px-3 bg-white border-[1.5px] {{ $errors->has('name') ? 'border-alerta' : 'border-line' }} rounded-md text-[14px] focus:border-pitch focus:ring-0">
                @error('name') <p class="text-[12px] text-alerta">{{ $message }}</p> @enderror
            </div>
            <div class="flex flex-col gap-1 w-32">
                <label class="font-mono text-[10.5px] tracking-wide-label uppercase text-ink-mute">Color</label>
                <input type="color" name="color" value="{{ old('color', '#1f6f43') }}"
                       class="h-[42px] w-full bg-white border-[1.5px] border-line rounded-md cursor-pointer">
            </div>
            <x-btn type="submit" variant="accent">Crear y ser capitán</x-btn>
        </form>
        <p class="font-mono text-[11px] text-ink-mute mt-2">Al crear el equipo quedás como su capitán. Después lo podés inscribir en los torneos que quieras.</p>
    </div>

    @if ($captainClubs->isNotEmpty() || $memberClubs->isNotEmpty())
        <div class="mb-5"><x-search-input placeholder="Buscar equipo por nombre…" /></div>
    @endif

    {{-- Equipos que dirijo (o todos, si es admin de plataforma) --}}
    <p class="font-mono text-[11px] tracking-wide-label uppercase text-ink-mute mb-3">
        {{ $isPlatformAdmin ? 'Todos los equipos' : 'Dirijo como capitán' }} ({{ $captainClubs->count() }})
    </p>
    @if ($captainClubs->isEmpty())
        <div class="bg-white border border-line rounded-md shadow-card p-8 text-center mb-8">
            <p class="text-ink-soft">{{ $isPlatformAdmin ? 'Todavía no hay equipos en la plataforma.' : 'Todavía no dirigís ningún equipo. Creá uno para empezar.' }}</p>
        </div>
    @else
        <div class="grid grid-cols-1 gap-6 mb-8">
            @foreach ($captainClubs as $club)
                <article class="bg-white border border-line rounded-md shadow-card-2 overflow-hidden flex flex-col"
                         x-show="match(@js($club->name))" x-cloak>
                    <div class="p-5 border-b border-line-soft flex items-center gap-4">
                        <x-avatar :name="$club->name" :src="$club->shield_url" size="lg" />
                        <div class="min-w-0 flex-1">
                            <h2 class="font-display font-bold text-pitch text-display-s uppercase leading-tight break-words">{{ $club->name }}</h2>
                            <p class="font-mono text-[11px] text-ink-mute mt-1">Capitán · {{ $club->players_count }} jugadores · {{ $club->tournaments_count }} torneo(s)</p>
                        </div>
                    </div>
                    <div class="p-5 mt-auto flex flex-wrap gap-2">
                        <a href="{{ route('torneos.clubes.manage', $club) }}"
                           class="px-3 py-1.5 rounded-md bg-pitch text-bone font-display font-semibold text-[12px] uppercase tracking-wide-label hover:bg-pitch-deep transition-all duration-fast">Gestionar plantilla</a>
                        <a href="{{ route('torneos.clubes.show', $club) }}"
                           class="px-3 py-1.5 rounded-md border border-line text-pitch font-display font-semibold text-[12px] uppercase tracking-wide-label hover:bg-bone-soft transition-all duration-fast">Perfil del equipo</a>
                    </div>
                </article>
            @endforeach
        </div>
    @endif

    {{-- Equipos donde juego --}}
    @if ($memberClubs->isNotEmpty())
        <p class="font-mono text-[11px] tracking-wide-label uppercase text-ink-mute mb-3">Juego en ({{ $memberClubs->count() }})</p>
        <div class="grid grid-cols-1 gap-6">
            @foreach ($memberClubs as $club)
                <article class="bg-white border border-line rounded-md shadow-card-2 overflow-hidden flex flex-col"
                         x-show="match(@js($club->name))" x-cloak>
                    <div class="p-5 border-b border-line-soft flex items-center gap-4">
                        <x-avatar :name="$club->name" :src="$club->shield_url" size="lg" />
                        <div class="min-w-0 flex-1">
                            <h2 class="font-display font-bold text-pitch text-display-s uppercase leading-tight break-words">{{ $club->name }}</h2>
                            <p class="font-mono text-[11px] text-ink-mute mt-1">Jugador · Capitán: {{ $club->captain?->name ?? '—' }}</p>
                        </div>
                    </div>
                    <div class="p-5 mt-auto">
                        <a href="{{ route('torneos.clubes.show', $club) }}"
                           class="px-3 py-1.5 rounded-md border border-line text-pitch font-display font-semibold text-[12px] uppercase tracking-wide-label hover:bg-bone-soft transition-all duration-fast">Perfil del equipo</a>
                    </div>
                </article>
            @endforeach
        </div>
    @endif
</div>
@endsection
