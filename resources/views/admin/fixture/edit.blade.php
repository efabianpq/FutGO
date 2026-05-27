@extends('layouts.app')
@section('title', 'Admin · Editar partido #' . $game->match_number)

@section('content')
@include('admin._nav')

<div class="max-w-2xl mx-auto px-4 py-6">
    <a href="{{ route('admin.fixture.index') }}" class="text-sm text-pachon-green hover:underline">← Volver al fixture</a>

    <h1 class="text-2xl font-bold text-pachon-green mt-3 mb-4">Editar Partido #{{ $game->match_number }}</h1>

    <div class="bg-white rounded-lg shadow p-6">
        <form method="POST" action="{{ route('admin.fixture.update', $game->id) }}" class="space-y-4">
            @csrf @method('PATCH')

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium mb-1">Equipo local</label>
                    <input type="text" name="home_team" required maxlength="60" value="{{ old('home_team', $game->home_team) }}"
                           class="w-full rounded-md border-gray-300 focus:ring-pachon-green focus:border-pachon-green">
                    @error('home_team')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Equipo visitante</label>
                    <input type="text" name="away_team" required maxlength="60" value="{{ old('away_team', $game->away_team) }}"
                           class="w-full rounded-md border-gray-300 focus:ring-pachon-green focus:border-pachon-green">
                    @error('away_team')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Bandera local (emoji)</label>
                    <input type="text" name="home_flag" maxlength="10" value="{{ old('home_flag', $game->home_flag) }}"
                           class="w-full rounded-md border-gray-300 text-2xl">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Bandera visitante (emoji)</label>
                    <input type="text" name="away_flag" maxlength="10" value="{{ old('away_flag', $game->away_flag) }}"
                           class="w-full rounded-md border-gray-300 text-2xl">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Fecha</label>
                    <input type="date" name="match_date" required value="{{ old('match_date', $game->match_datetime->format('Y-m-d')) }}"
                           class="w-full rounded-md border-gray-300">
                    @error('match_date')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Hora (Colombia GMT-5)</label>
                    <input type="time" name="match_time" required value="{{ old('match_time', $game->match_datetime->format('H:i')) }}"
                           class="w-full rounded-md border-gray-300">
                    @error('match_time')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium mb-1">Estadio</label>
                    <input type="text" name="venue" maxlength="100" value="{{ old('venue', $game->venue) }}"
                           class="w-full rounded-md border-gray-300">
                </div>
            </div>

            <p class="text-xs text-gray-500">
                ℹ️ <code>lock_datetime</code> se recalcula automáticamente a fecha/hora del partido menos 5 minutos.
            </p>

            <div class="flex justify-end gap-3 pt-2">
                <a href="{{ route('admin.fixture.index') }}" class="px-4 py-2 text-sm bg-gray-200 rounded-md">Cancelar</a>
                <button type="submit" class="px-4 py-2 text-sm bg-pachon-green hover:bg-pachon-green-dark text-white font-semibold rounded-md">
                    Guardar cambios
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
