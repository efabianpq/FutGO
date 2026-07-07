@extends('layouts.app')
@section('title', 'Configuración de privacidad')

@section('content')
@php
    $toggles = [
        'public_profile' => ['Perfil público', 'Tu ficha de jugador es visible para otros.'],
        'searchable'     => ['Aparecer en búsquedas', 'Puedes aparecer en el buscador de FutGO.'],
        'indexable_by_search_engines' => ['Indexable por Google', 'Los buscadores externos pueden mostrar tu ficha.'],
        'show_photo'     => ['Mostrar foto', 'Tu foto de perfil es pública.'],
        'show_city'      => ['Mostrar ciudad', 'Tu ciudad aparece en tu ficha.'],
        'show_stats'     => ['Mostrar estadísticas', 'Goles, asistencias y partidos.'],
        'show_teams'     => ['Mostrar equipos', 'Los clubes en los que juegas.'],
        'show_history'   => ['Mostrar historial', 'Tu historial de temporadas.'],
        'show_email'     => ['Mostrar correo', 'No recomendado: tu correo sería público.'],
        'show_phone'     => ['Mostrar teléfono', 'No recomendado: tu teléfono sería público.'],
        'show_birthdate' => ['Mostrar fecha de nacimiento', 'Tu edad sería pública.'],
    ];
@endphp
<div class="max-w-3xl mx-auto px-4 sm:px-6 py-8">
    <div class="flex items-center gap-2 mb-1">
        <h1 class="text-2xl font-bold text-text">Centro de Privacidad</h1>
        <x-help-hint topic="privacidad.configuracion" />
    </div>
    <p class="text-[14px] text-muted mb-6">Elige qué información se muestra públicamente.</p>

    @include('privacy.center._tabs')

    <form method="POST" action="{{ route('privacidad.configuracion.update') }}">
        @csrf
        @method('PATCH')

        <div class="bg-surface border border-border rounded-md divide-y divide-border">
            @foreach($toggles as $key => [$label, $desc])
                <label class="flex items-center justify-between gap-4 p-4 cursor-pointer">
                    <span>
                        <span class="block font-semibold text-text text-[14px]">{{ $label }}</span>
                        <span class="block text-[12px] text-muted">{{ $desc }}</span>
                    </span>
                    <input type="checkbox" name="{{ $key }}" value="1" @checked($settings->{$key})
                           class="h-5 w-5 rounded border-border text-primary focus:ring-primary shrink-0">
                </label>
            @endforeach

            <div class="p-4">
                <span class="block font-semibold text-text text-[14px] mb-2">Quién puede enviarme mensajes</span>
                <select name="allow_messages" class="input">
                    <option value="nadie"      @selected($settings->allow_messages === 'nadie')>Nadie</option>
                    <option value="companeros" @selected($settings->allow_messages === 'companeros')>Solo compañeros</option>
                    <option value="todos"      @selected($settings->allow_messages === 'todos')>Todos</option>
                </select>
            </div>
        </div>

        <button type="submit" class="btn btn-primary mt-5">Guardar cambios</button>
    </form>
</div>
@endsection
