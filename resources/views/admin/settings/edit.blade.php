@extends('layouts.app')
@section('title', 'Admin · Configuración')

@section('content')
@include('admin._nav')

<div class="max-w-2xl mx-auto px-4 py-8">
    <p class="eyebrow">Parámetros globales</p>
    <h1 class="font-display font-bold text-display-m text-pitch uppercase mt-2 mb-6">Configuración</h1>

    <div class="bg-white border border-line rounded-md shadow-card-2 p-6 sm:p-8">
        <form method="POST" action="{{ route('admin.settings.update') }}" class="space-y-6">
            @csrf

            <div class="flex flex-col gap-1.5">
                <label for="prize_pool" class="font-mono text-[11px] tracking-wide-label uppercase text-ink-soft">Acumulado total (COP)</label>
                <input type="number" name="prize_pool" id="prize_pool" min="0" step="1"
                       value="{{ old('prize_pool', $prize_pool) }}"
                       placeholder="Dejá vacío para 'Por definir'"
                       class="h-[46px] px-3.5 bg-white border-[1.5px] {{ $errors->has('prize_pool') ? 'border-alerta' : 'border-line' }} rounded-md text-[15px] font-mono focus:border-pitch focus:ring-0">
                <p class="text-[12px] text-ink-mute">Alimenta el desglose 60/20/10 del ranking público.</p>
                @error('prize_pool')<p class="text-[12px] text-alerta">{{ $message }}</p>@enderror
            </div>

            <div class="flex flex-col gap-1.5">
                <label for="tournament_name" class="font-mono text-[11px] tracking-wide-label uppercase text-ink-soft">Nombre del torneo</label>
                <input type="text" name="tournament_name" id="tournament_name" maxlength="100" required
                       value="{{ old('tournament_name', $tournament_name) }}"
                       class="h-[46px] px-3.5 bg-white border-[1.5px] {{ $errors->has('tournament_name') ? 'border-alerta' : 'border-line' }} rounded-md text-[15px] focus:border-pitch focus:ring-0">
                @error('tournament_name')<p class="text-[12px] text-alerta">{{ $message }}</p>@enderror
            </div>

            <div class="flex flex-col gap-1.5">
                <label for="welcome_message" class="font-mono text-[11px] tracking-wide-label uppercase text-ink-soft">Mensaje de bienvenida</label>
                <textarea name="welcome_message" id="welcome_message" rows="3" maxlength="500"
                          class="px-3.5 py-2 bg-white border-[1.5px] border-line rounded-md text-[15px] focus:border-pitch focus:ring-0">{{ old('welcome_message', $welcome_message) }}</textarea>
                <p class="text-[12px] text-ink-mute">Aparece en la portada pública.</p>
            </div>

            <div class="flex flex-col gap-1.5">
                <label for="video_url" class="font-mono text-[11px] tracking-wide-label uppercase text-ink-soft">URL del video (YouTube)</label>
                <input type="url" name="video_url" id="video_url" maxlength="255"
                       value="{{ old('video_url', $video_url) }}"
                       placeholder="https://www.youtube.com/watch?v=XXXX"
                       class="h-[46px] px-3.5 bg-white border-[1.5px] {{ $errors->has('video_url') ? 'border-alerta' : 'border-line' }} rounded-md text-[15px] font-mono focus:border-pitch focus:ring-0">
                <p class="text-[12px] text-ink-mute">Acepta watch / youtu.be / embed. Dejá vacío para mostrar placeholder.</p>
                @error('video_url')<p class="text-[12px] text-alerta">{{ $message }}</p>@enderror
            </div>

            <div class="flex justify-end pt-2">
                <x-btn type="submit" variant="primary">Guardar configuración</x-btn>
            </div>
        </form>
    </div>
</div>
@endsection
