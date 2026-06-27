@extends('layouts.app')
@section('title', '404 · Página no encontrada')

@section('content')
<div class="flex flex-col items-center justify-center min-h-[60vh] px-4 py-16 text-center">

    <p class="font-display font-extrabold leading-none select-none
              text-[72px] sm:text-[100px] md:text-[120px]
              text-accent opacity-50 mb-2">
        404
    </p>

    <p class="eyebrow muted mb-3">Página no encontrada</p>

    <h1 class="font-display font-bold text-2xl sm:text-display-s text-text uppercase mb-4">
        Esta página no existe
    </h1>

    <p class="text-muted text-[15px] leading-relaxed max-w-sm mb-8">
        La URL que ingresaste no corresponde a ninguna sección de la plataforma.
        Puede que el enlace esté desactualizado o que la dirección tenga un error tipográfico.
    </p>

    <div class="flex flex-wrap items-center justify-center gap-3">
        <x-btn :href="route('home')" variant="primary">
            Volver al inicio
        </x-btn>
        <x-btn onclick="history.back()" variant="ghost">
            Página anterior
        </x-btn>
    </div>

</div>
@endsection
