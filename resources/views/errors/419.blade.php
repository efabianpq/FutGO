@extends('layouts.app')
@section('title', '419 · Sesión expirada')

@section('content')
<div class="flex flex-col items-center justify-center min-h-[60vh] px-4 py-16 text-center">

    {{-- Código de error decorativo --}}
    <p class="font-display font-extrabold leading-none select-none
              text-[72px] sm:text-[100px] md:text-[120px]
              text-warning opacity-50 mb-2">
        419
    </p>

    {{-- Eyebrow --}}
    <p class="eyebrow muted mb-3">Sesión expirada</p>

    {{-- Título principal --}}
    <h1 class="font-display font-bold text-2xl sm:text-display-s text-text uppercase mb-4">
        La página caducó
    </h1>

    {{-- Explicación --}}
    <p class="text-muted text-[15px] leading-relaxed max-w-sm mb-5">
        El token de seguridad de este formulario ya no es válido.
        Esto suele ocurrir cuando:
    </p>

    <ul class="text-left space-y-2.5 text-[14px] text-muted max-w-xs mx-auto mb-8">
        <li class="flex items-start gap-2.5">
            <span class="text-warning font-bold shrink-0 mt-0.5">·</span>
            <span>La pestaña estuvo abierta mucho tiempo sin actividad</span>
        </li>
        <li class="flex items-start gap-2.5">
            <span class="text-warning font-bold shrink-0 mt-0.5">·</span>
            <span>Usaste un formulario guardado o de una pestaña antigua</span>
        </li>
        <li class="flex items-start gap-2.5">
            <span class="text-warning font-bold shrink-0 mt-0.5">·</span>
            <span>Accediste desde otro dispositivo o navegador</span>
        </li>
    </ul>

    {{-- Acciones --}}
    <div class="flex flex-wrap items-center justify-center gap-3">
        <x-btn :href="route('home')" variant="primary">
            Volver al inicio
        </x-btn>
        <x-btn :href="route('login')" variant="ghost">
            Iniciar sesión
        </x-btn>
    </div>

</div>
@endsection
