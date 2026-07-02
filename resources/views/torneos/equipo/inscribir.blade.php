@extends('layouts.app')
@section('title', 'Inscribir equipo · ' . $tournament->name)

@section('content')
<div class="max-w-xl mx-auto px-4 py-10">

    <div class="mb-6">
        <p class="eyebrow">{{ $tournament->name }}</p>
        <h1 class="font-display font-bold text-display-s sm:text-display-m text-pitch uppercase mt-1">Inscribir un equipo</h1>
        <p class="text-ink-soft text-[14px] mt-2">
            Elige uno de tus equipos para inscribirlo en este torneo. Su plantilla actual
            se copia al torneo y el organizador deberá aprobar la inscripción.
        </p>
    </div>

    @if (session('error'))
        <div class="mb-4 bg-alerta/15 border border-alerta text-alerta-deep px-4 py-3 rounded-md font-display font-semibold">{{ session('error') }}</div>
    @endif
    @error('club_id')
        <div class="mb-4 bg-alerta/15 border border-alerta text-alerta-deep px-4 py-3 rounded-md font-display font-semibold">{{ $message }}</div>
    @enderror

    @if ($clubs->isEmpty())
        <div class="bg-white border border-line rounded-md shadow-card-2 p-6 text-center">
            <p class="text-ink-soft">No tienes equipos disponibles para inscribir.</p>
            <p class="text-[13px] text-ink-mute mt-2">Crea un equipo en «Mis Equipos» y después vuelve a inscribirlo.</p>
            <div class="mt-4 flex justify-center gap-3">
                <x-btn :href="route('torneos.mis-equipos')" variant="accent">Ir a Mis Equipos</x-btn>
                <x-btn :href="route('torneos.index')" variant="ghost">Cancelar</x-btn>
            </div>
        </div>
    @else
        <div class="bg-white border border-line rounded-md shadow-card-2 p-6">
            <form method="POST" action="{{ route('torneos.equipo.store', $tournament) }}" class="space-y-4"
                  x-data="{ selected: '{{ $clubs->first()->id }}' }">
                @csrf
                <div class="space-y-3">
                    @foreach ($clubs as $club)
                        <label class="flex items-center gap-3 p-3 border-[1.5px] rounded-md cursor-pointer transition-all duration-fast"
                               :class="selected === '{{ $club->id }}' ? 'border-pitch bg-pitch-mist' : 'border-line hover:bg-bone-soft'">
                            <input type="radio" name="club_id" value="{{ $club->id }}" x-model="selected" class="accent-pitch">
                            <x-avatar :name="$club->name" :src="$club->shield_url" size="sm" />
                            <span class="min-w-0 flex-1">
                                <span class="font-display font-semibold text-pitch text-[15px] block truncate">{{ $club->name }}</span>
                                <span class="font-mono text-[11px] text-ink-mute">{{ $club->players_count }} jugadores en plantilla</span>
                            </span>
                        </label>
                    @endforeach
                </div>

                <div class="flex justify-end gap-3 pt-2">
                    <x-btn :href="route('torneos.index')" variant="ghost">Cancelar</x-btn>
                    <x-btn type="submit" variant="primary">Inscribir equipo</x-btn>
                </div>
            </form>
        </div>
    @endif
</div>
@endsection
