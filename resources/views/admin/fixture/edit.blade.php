@extends('layouts.app')
@section('title', 'Admin · Editar partido #' . $game->match_number)

@section('content')
@include('admin._nav')

<div class="max-w-2xl mx-auto px-4 py-8">
    <a href="{{ route('admin.fixture.index') }}" class="font-display font-bold text-[13px] uppercase tracking-wide-cta text-pitch hover:underline">← Volver al fixture</a>

    <p class="eyebrow mt-4">Editar partido</p>
    <h1 class="font-display font-bold text-display-m text-pitch uppercase mt-2 mb-6">Partido #{{ $game->match_number }}</h1>

    <div class="bg-white border border-line rounded-md shadow-card-2 p-6 sm:p-8">
        <form method="POST" action="{{ route('admin.fixture.update', $game->id) }}" class="space-y-5">
            @csrf @method('PATCH')

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="flex flex-col gap-1.5">
                    <label class="font-mono text-[11px] tracking-wide-label uppercase text-ink-soft">Equipo local</label>
                    <input type="text" name="home_team" required maxlength="60" value="{{ old('home_team', $game->home_team) }}"
                           class="h-[46px] px-3.5 bg-white border-[1.5px] {{ $errors->has('home_team') ? 'border-alerta' : 'border-line' }} rounded-md text-[15px] focus:border-pitch focus:ring-0">
                </div>
                <div class="flex flex-col gap-1.5">
                    <label class="font-mono text-[11px] tracking-wide-label uppercase text-ink-soft">Equipo visitante</label>
                    <input type="text" name="away_team" required maxlength="60" value="{{ old('away_team', $game->away_team) }}"
                           class="h-[46px] px-3.5 bg-white border-[1.5px] {{ $errors->has('away_team') ? 'border-alerta' : 'border-line' }} rounded-md text-[15px] focus:border-pitch focus:ring-0">
                </div>
                <div class="flex flex-col gap-1.5">
                    <label class="font-mono text-[11px] tracking-wide-label uppercase text-ink-soft">Bandera local</label>
                    <input type="text" name="home_flag" maxlength="10" value="{{ old('home_flag', $game->home_flag) }}"
                           class="h-[46px] px-3.5 bg-white border-[1.5px] border-line rounded-md text-2xl focus:border-pitch focus:ring-0">
                </div>
                <div class="flex flex-col gap-1.5">
                    <label class="font-mono text-[11px] tracking-wide-label uppercase text-ink-soft">Bandera visitante</label>
                    <input type="text" name="away_flag" maxlength="10" value="{{ old('away_flag', $game->away_flag) }}"
                           class="h-[46px] px-3.5 bg-white border-[1.5px] border-line rounded-md text-2xl focus:border-pitch focus:ring-0">
                </div>
                <div class="flex flex-col gap-1.5">
                    <label class="font-mono text-[11px] tracking-wide-label uppercase text-ink-soft">Fecha</label>
                    <input type="date" name="match_date" required value="{{ old('match_date', $game->match_datetime->format('Y-m-d')) }}"
                           class="h-[46px] px-3.5 bg-white border-[1.5px] border-line rounded-md text-[15px] focus:border-pitch focus:ring-0">
                </div>
                <div class="flex flex-col gap-1.5">
                    <label class="font-mono text-[11px] tracking-wide-label uppercase text-ink-soft">Hora (GMT-5)</label>
                    <input type="time" name="match_time" required value="{{ old('match_time', $game->match_datetime->format('H:i')) }}"
                           class="h-[46px] px-3.5 bg-white border-[1.5px] border-line rounded-md text-[15px] focus:border-pitch focus:ring-0">
                </div>
                <div class="md:col-span-2 flex flex-col gap-1.5">
                    <label class="font-mono text-[11px] tracking-wide-label uppercase text-ink-soft">Estadio</label>
                    <input type="text" name="venue" maxlength="100" value="{{ old('venue', $game->venue) }}"
                           class="h-[46px] px-3.5 bg-white border-[1.5px] border-line rounded-md text-[15px] focus:border-pitch focus:ring-0">
                </div>
            </div>

            <p class="font-mono text-[11px] tracking-wide-label uppercase text-ink-mute">
                ℹ lock_datetime se recalcula a fecha/hora menos 5 minutos.
            </p>

            <div class="flex justify-end gap-3 pt-2">
                <a href="{{ route('admin.fixture.index') }}" class="font-display font-bold text-[13px] uppercase tracking-wide-cta px-3.5 py-2 rounded-md bg-line text-ink hover:bg-line-soft transition-all duration-fast">Cancelar</a>
                <x-btn type="submit" variant="primary">Guardar cambios</x-btn>
            </div>
        </form>
    </div>
</div>
@endsection
