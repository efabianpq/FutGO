@extends('layouts.app')
@section('title', 'Admin · Configuración')

@section('content')
@include('admin._nav')

<div class="max-w-2xl mx-auto px-4 py-6">
    <h1 class="text-2xl font-bold text-pachon-green mb-4">⚙️ Configuración General</h1>

    <div class="bg-white rounded-lg shadow p-6">
        <form method="POST" action="{{ route('admin.settings.update') }}" class="space-y-5">
            @csrf

            <div>
                <label for="prize_pool" class="block text-sm font-medium mb-1">Acumulado total (COP)</label>
                <input type="number" name="prize_pool" id="prize_pool" min="0" step="1"
                       value="{{ old('prize_pool', $prize_pool) }}"
                       placeholder="Dejá vacío para 'Por definir'"
                       class="w-full rounded-md border-gray-300 focus:ring-pachon-green focus:border-pachon-green">
                @error('prize_pool')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                <p class="text-xs text-gray-500 mt-1">
                    Este valor alimenta el desglose de premios (60% / 20% / 10%) del ranking público.
                    Dejalo vacío para que la columna "Premio Est." muestre <em>Por definir</em>.
                </p>
            </div>

            <div>
                <label for="tournament_name" class="block text-sm font-medium mb-1">Nombre del torneo</label>
                <input type="text" name="tournament_name" id="tournament_name" maxlength="100" required
                       value="{{ old('tournament_name', $tournament_name) }}"
                       class="w-full rounded-md border-gray-300 focus:ring-pachon-green focus:border-pachon-green">
                @error('tournament_name')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="welcome_message" class="block text-sm font-medium mb-1">Mensaje de bienvenida</label>
                <textarea name="welcome_message" id="welcome_message" rows="3" maxlength="500"
                          class="w-full rounded-md border-gray-300 focus:ring-pachon-green focus:border-pachon-green">{{ old('welcome_message', $welcome_message) }}</textarea>
                <p class="text-xs text-gray-500 mt-1">Aparece en la portada pública (welcome).</p>
            </div>

            <div>
                <label for="video_url" class="block text-sm font-medium mb-1">URL del video explicativo (YouTube)</label>
                <input type="url" name="video_url" id="video_url" maxlength="255"
                       value="{{ old('video_url', $video_url) }}"
                       placeholder="https://www.youtube.com/watch?v=XXXX  o  https://youtu.be/XXXX"
                       class="w-full rounded-md border-gray-300 focus:ring-pachon-green focus:border-pachon-green">
                @error('video_url')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                <p class="text-xs text-gray-500 mt-1">Acepta enlaces normales de YouTube (watch, youtu.be o embed). Dejá vacío para mostrar placeholder.</p>
            </div>

            <div class="flex justify-end pt-2">
                <button type="submit" class="bg-pachon-green hover:bg-pachon-green-dark text-white px-4 py-2 rounded-md text-sm font-semibold">
                    Guardar configuración
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
