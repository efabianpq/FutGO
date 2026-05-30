@extends('layouts.app')
@section('title', 'Inscribir equipo · ' . $tournament->name)

@section('content')
<div class="max-w-xl mx-auto px-4 py-10">

    <div class="mb-6">
        <p class="eyebrow">{{ $tournament->name }}</p>
        <h1 class="font-display font-bold text-display-m text-pitch uppercase mt-1">Inscribir mi equipo</h1>
        <p class="text-ink-soft text-[14px] mt-2">
            Vas a quedar registrado como capitán y jugador del equipo.
            El organizador deberá aprobar la inscripción.
        </p>
    </div>

    @if (session('error'))
        <div class="mb-4 bg-alerta/15 border border-alerta text-alerta-deep px-4 py-3 rounded-md font-display font-semibold">
            {{ session('error') }}
        </div>
    @endif

    <div class="bg-white border border-line rounded-md shadow-card-2 p-6">
        <form method="POST" action="{{ route('torneos.equipo.store', $tournament) }}" class="space-y-5">
            @csrf

            <div class="flex flex-col gap-1.5">
                <label for="name" class="font-mono text-[11px] tracking-wide-label uppercase text-ink-soft">
                    Nombre del equipo *
                </label>
                <input type="text" name="name" id="name" maxlength="80" required
                       value="{{ old('name') }}"
                       class="h-[46px] px-3.5 bg-white border-[1.5px] {{ $errors->has('name') ? 'border-alerta' : 'border-line' }} rounded-md text-[15px] focus:border-pitch focus:ring-0">
                @error('name')
                    <p class="text-[12px] text-alerta">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex flex-col gap-1.5">
                <label for="color" class="font-mono text-[11px] tracking-wide-label uppercase text-ink-soft">
                    Color del equipo <span class="normal-case text-ink-mute">(opcional, formato #RRGGBB)</span>
                </label>
                <div class="flex items-center gap-3" x-data="{ color: '{{ old('color', '#1a1a2e') }}' }">
                    <input type="color"
                           x-model="color"
                           class="w-12 h-12 rounded-md border border-line cursor-pointer p-1">
                    <input type="text" name="color" id="color"
                           x-model="color"
                           placeholder="#RRGGBB"
                           class="h-[46px] px-3.5 bg-white border-[1.5px] {{ $errors->has('color') ? 'border-alerta' : 'border-line' }} rounded-md text-[15px] font-mono focus:border-pitch focus:ring-0 flex-1">
                </div>
                @error('color')
                    <p class="text-[12px] text-alerta">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex justify-end gap-3 pt-2">
                <x-btn :href="route('torneos.index')" variant="ghost">Cancelar</x-btn>
                <x-btn type="submit" variant="primary">Inscribir equipo</x-btn>
            </div>
        </form>
    </div>
</div>
@endsection
