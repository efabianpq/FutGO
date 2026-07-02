@extends('layouts.app')
@section('title', '500 · Error del servidor')

@section('content')
<div class="flex flex-col items-center justify-center min-h-[60vh] px-4 py-16 text-center">

    <p class="font-display font-extrabold leading-none select-none
              text-[72px] sm:text-[100px] md:text-[120px]
              text-error opacity-50 mb-2">
        500
    </p>

    <p class="eyebrow muted mb-3">Error interno</p>

    <h1 class="font-display font-bold text-2xl sm:text-display-s text-text uppercase mb-4">
        Algo salió mal
    </h1>

    <p class="text-muted text-[15px] leading-relaxed max-w-sm mb-5">
        El servidor encontró un problema al procesar tu solicitud.
        Nuestro equipo ya fue notificado automáticamente.
    </p>

    <ul class="text-left space-y-2.5 text-[14px] text-muted max-w-xs mx-auto mb-8">
        <li class="flex items-start gap-2.5">
            <span class="text-error font-bold shrink-0 mt-0.5">·</span>
            <span>Intenta recargar la página en unos segundos</span>
        </li>
        <li class="flex items-start gap-2.5">
            <span class="text-error font-bold shrink-0 mt-0.5">·</span>
            <span>Si el problema persiste, vuelve más tarde</span>
        </li>
    </ul>

    <div class="flex flex-wrap items-center justify-center gap-3">
        <x-btn :href="route('home')" variant="primary">
            Volver al inicio
        </x-btn>
        <x-btn onclick="location.reload()" variant="ghost">
            Reintentar
        </x-btn>
    </div>

</div>
@endsection
