@extends('layouts.app')

@section('title', 'Polla del Mundial 2026')

@section('content')
{{-- ───────────────── HERO ÚNICO ───────────────── --}}
<section class="relative overflow-hidden min-h-[calc(100vh-4rem)] flex items-center bg-bg">
    {{-- Glow de marca de fondo --}}
    <div class="pointer-events-none absolute inset-0 opacity-70"
         style="background: radial-gradient(60% 80% at 80% -10%, rgba(0,230,118,.16), transparent 60%);"></div>

    <div class="max-w-7xl mx-auto px-6 sm:px-8 lg:px-12 py-12 sm:py-16 lg:py-20 w-full relative">

        {{-- Cabecera con brand y meta --}}
        <div class="flex items-center justify-between border-b border-border pb-6 sm:pb-8 mb-8 sm:mb-12">
            <x-logo size="sm" />
            <p class="font-mono text-[10px] sm:text-[11px] tracking-[.12em] uppercase text-subtle hidden sm:flex gap-7">
                <span>FIFA 2026</span>
                <span>Polla del Mundial</span>
            </p>
        </div>

        {{-- Titular --}}
        <h1 class="font-display font-display-x font-black uppercase leading-[0.85] tracking-tight-display text-text"
            style="font-size: clamp(48px, 11vw, 144px);">
            La polla del<br>
            <span class="text-green">Mundial 2026</span><br>
            <span class="font-medium text-muted block mt-3" style="font-size: .36em; letter-spacing: .04em;">
                hecha para los que ven cada partido
            </span>
        </h1>

        {{-- Mensaje de bienvenida + CTA hacia ¿Cómo funciona? --}}
        <div class="grid sm:grid-cols-[1.5fr_1fr] gap-6 sm:gap-10 mt-12 sm:mt-16">
            <div>
                <p class="text-body-l sm:text-[22px] text-text leading-relaxed">
                    @guest
                        <strong class="text-green">Bienvenido.</strong>
                        Acá pronosticás los 104 partidos del Mundial, competís contra
                        tus amigos y te llevás premios reales en efectivo. Pago único
                        de <strong class="text-green">$30.000 COP</strong> por cupo, todo
                        se reparte entre los tres primeros.
                    @else
                        <strong class="text-green">Hola, {{ explode(' ', auth()->user()->name)[0] }}.</strong>
                        Bienvenido de nuevo. Si recién entrás, conocé primero cómo
                        funciona la polla; si ya sabés, andá directo a ingresar tus
                        pronósticos del próximo partido.
                    @endguest
                </p>
                <p class="text-body text-muted mt-4 max-w-prose">
                    ¿Es tu primera vez? Te explicamos en detalle el sistema de puntos,
                    cómo se reparte el acumulado, cómo ingresar pronósticos y cómo
                    inscribirte — todo en una sola página.
                </p>
            </div>

            {{-- Bloque de CTAs --}}
            <div class="flex flex-col gap-3 sm:gap-4">
                {{-- CTA principal: a ¿Cómo funciona? --}}
                <a href="{{ route('how-it-works') }}"
                   class="group bg-green text-on-green rounded-md px-5 py-5 sm:px-6 sm:py-6 shadow-glow hover:bg-green-strong transition-all duration-fast">
                    <p class="font-mono text-[10.5px] tracking-[.12em] uppercase opacity-70">Empezá acá</p>
                    <p class="font-display font-display-x font-extrabold text-display-s sm:text-display-m uppercase mt-1 leading-tight">
                        ¿Cómo funciona?
                    </p>
                    <p class="text-body-s mt-2 opacity-80">
                        Puntos, premios, inscripción y todo lo que necesitás saber.
                    </p>
                    <p class="font-display font-bold text-[13px] uppercase tracking-wide-cta mt-3 inline-flex items-center gap-2">
                        Ver guía completa
                        <span class="transition-transform duration-fast group-hover:translate-x-1">→</span>
                    </p>
                </a>

                {{-- CTAs secundarias --}}
                @guest
                    <div class="grid grid-cols-2 gap-2 sm:gap-3">
                        <a href="{{ route('login') }}" class="btn btn-secondary btn-block">Iniciar sesión</a>
                        <a href="{{ route('register') }}" class="btn btn-outline btn-block">Crear cuenta</a>
                    </div>
                @else
                    <a href="{{ route('predictions.index') }}" class="btn btn-secondary btn-block">
                        Ir a Mis Pronósticos →
                    </a>
                @endguest
            </div>
        </div>

    </div>
</section>
@endsection
