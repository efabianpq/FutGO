@extends('layouts.app')

@section('title', 'Soy Pachón Mundial')

@section('content')
{{-- ───────────────── HERO COVER ───────────────── --}}
<section class="bg-pitch text-bone relative overflow-hidden">
    <div class="max-w-7xl mx-auto px-6 sm:px-8 lg:px-12 py-16 sm:py-20 lg:py-24">
        <div class="flex items-center justify-between border-b border-bone/15 pb-10 mb-10">
            <p class="font-display font-extrabold text-[16px] sm:text-[18px] uppercase tracking-[.04em]">
                Pachón<span class="text-gol">·</span>Mundial
            </p>
            <p class="font-mono text-[10px] sm:text-[11px] tracking-wide-label uppercase text-bone/60 hidden sm:flex gap-7">
                <span>FIFA 2026</span>
                <span>v1.0</span>
            </p>
        </div>

        <h1 class="font-display font-extrabold uppercase leading-[0.82] tracking-tight-display"
            style="font-size: clamp(56px, 11vw, 168px);">
            POLLA<br>
            DEL <span class="text-gol">MUNDIAL</span><br>
            <span class="font-medium text-bone/80 block mt-2" style="font-size: .42em; letter-spacing: .04em;">
                hecha para los que ven cada partido
            </span>
        </h1>

        <div class="grid sm:grid-cols-[1.4fr_1fr_1fr] gap-6 sm:gap-8 mt-12 sm:mt-16 pt-8 border-t border-bone/15">
            <p class="text-body sm:text-body-l text-bone/85 max-w-prose">
                {{ \App\Support\Settings::welcomeMessage() }}
            </p>
            <div>
                <dt class="font-mono text-[11px] tracking-wide-label uppercase text-bone/55">Torneo</dt>
                <dd class="text-body mt-1">{{ \App\Support\Settings::tournamentName() }}</dd>
                <dt class="font-mono text-[11px] tracking-wide-label uppercase text-bone/55 mt-5">Partidos</dt>
                <dd class="text-body mt-1">104 totales</dd>
            </div>
            <div class="flex flex-col gap-3">
                @guest
                    <x-btn href="{{ route('register') }}" variant="accent" size="lg">Crear cuenta</x-btn>
                    <x-btn href="{{ route('login') }}" variant="ghost" size="lg" class="!text-bone !border-bone hover:!bg-bone hover:!text-pitch">Iniciar sesión</x-btn>
                @else
                    <x-btn href="{{ route('predictions.index') }}" variant="accent" size="lg">Mis Pronósticos</x-btn>
                @endguest
            </div>
        </div>
    </div>
</section>

{{-- ───────────────── VIDEO EXPLICATIVO ───────────────── --}}
<section class="py-16 sm:py-20 border-b border-line">
    <div class="max-w-4xl mx-auto px-6 sm:px-8 lg:px-12">
        <div class="section-head mb-10">
            <span class="eyebrow">VIDEO EXPLICATIVO</span>
            <h2 class="font-display font-bold uppercase leading-[0.96] -tracking-[.01em] mt-4"
                style="font-size: clamp(32px, 5vw, 56px);">
                Cómo funciona <span class="text-pitch">la polla</span>
            </h2>
        </div>

        @php $embed = \App\Support\Settings::videoEmbedUrl(); @endphp
        @if ($embed)
            <div class="aspect-video w-full rounded-md overflow-hidden bg-black shadow-card-2 border border-line">
                <iframe src="{{ $embed }}"
                        title="Video explicativo"
                        class="w-full h-full"
                        frameborder="0"
                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                        referrerpolicy="strict-origin-when-cross-origin"
                        allowfullscreen></iframe>
            </div>
        @else
            <div class="aspect-video w-full rounded-md bg-bone-soft border-2 border-dashed border-line flex flex-col items-center justify-center text-center p-6">
                <div class="text-5xl mb-3">🎬</div>
                <p class="font-display font-bold text-display-s uppercase text-pitch">Video explicativo próximamente</p>
                <p class="font-mono text-[11px] tracking-wide-label uppercase text-ink-mute mt-2">El administrador puede configurar la URL del video</p>
            </div>
        @endif
    </div>
</section>

{{-- ───────────────── CÓMO SE PUNTÚA ───────────────── --}}
<section class="py-16 sm:py-20 border-b border-line bg-bone-soft">
    <div class="max-w-6xl mx-auto px-6 sm:px-8 lg:px-12">
        <div class="section-head mb-12">
            <span class="eyebrow">PUNTAJE</span>
            <h2 class="font-display font-bold uppercase leading-[0.96] -tracking-[.01em] mt-4"
                style="font-size: clamp(32px, 5vw, 56px);">
                Tabla de <span class="text-pitch">puntos</span>
            </h2>
            <p class="text-body text-ink-soft max-w-prose mt-3">Solo cuenta el tiempo reglamentario — los penales no entran. Cada partido te puede sumar de 0 a 5 puntos.</p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-px bg-line border border-line rounded-md overflow-hidden">
            @foreach ([
                ['pts'=>5, 'label'=>'Exacto',          'desc'=>'Ambos marcadores correctos', 'bg'=>'bg-gol'],
                ['pts'=>3, 'label'=>'Ganador + 1',     'desc'=>'Ganador correcto + un marcador exacto en su lado'],
                ['pts'=>2, 'label'=>'Solo ganador',    'desc'=>'Acierta el ganador (sin marcador exacto)'],
                ['pts'=>1, 'label'=>'Casi',            'desc'=>'Ganador incorrecto pero acierta un marcador en su lado'],
                ['pts'=>0, 'label'=>'Falla',           'desc'=>'Ningún criterio cumplido'],
            ] as $row)
                <div class="bg-bone p-6 {{ $row['bg'] ?? '' }}">
                    <p class="font-display font-extrabold text-display-l leading-none {{ ($row['bg'] ?? '') ? 'text-pitch' : 'text-pitch' }}">{{ $row['pts'] }}<span class="text-display-s opacity-60">pts</span></p>
                    <p class="font-display font-bold text-display-s uppercase mt-4 {{ ($row['bg'] ?? '') ? 'text-pitch' : 'text-ink' }}">{{ $row['label'] }}</p>
                    <p class="font-body text-body-s {{ ($row['bg'] ?? '') ? 'text-pitch-deep' : 'text-ink-soft' }} mt-2">{{ $row['desc'] }}</p>
                </div>
            @endforeach
        </div>

        <p class="font-mono text-[11px] tracking-wide-label uppercase text-ink-mute mt-8">
            Desempate: en igualdad de puntos, gana quien tenga más marcadores exactos acertados.
        </p>
    </div>
</section>

{{-- ───────────────── CTA FINAL ───────────────── --}}
@guest
<section class="py-16 sm:py-20">
    <div class="max-w-4xl mx-auto px-6 sm:px-8 lg:px-12 text-center">
        <h2 class="font-display font-bold uppercase leading-[0.96] -tracking-[.01em]"
            style="font-size: clamp(32px, 5vw, 56px);">
            ¿Listo para <span class="text-pitch">jugar</span>?
        </h2>
        <p class="text-body-l text-ink-soft max-w-prose mx-auto mt-4">
            Creá tu cuenta con un código de invitación. Pronosticá los 104 partidos. Competí por el {{ \App\Support\Settings::prizePool() ? '$' . number_format(\App\Support\Settings::prizePool(), 0, ',', '.') . ' COP del' : '' }} acumulado.
        </p>
        <div class="flex flex-col sm:flex-row gap-3 justify-center mt-8">
            <x-btn href="{{ route('register') }}" variant="primary" size="lg">Crear cuenta</x-btn>
            <x-btn href="{{ route('login') }}" variant="ghost" size="lg">Ya tengo cuenta</x-btn>
        </div>
    </div>
</section>
@endguest
@endsection
