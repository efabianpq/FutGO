{{-- Sección de conversión final --}}
<section class="relative overflow-hidden py-24 text-center">

    {{-- Glow inferior --}}
    <div class="pointer-events-none absolute inset-0 -z-10"
         style="background: radial-gradient(60% 80% at 50% 120%, rgba(0,230,118,.18), transparent 60%);"></div>

    <div class="max-w-[1200px] mx-auto px-6">

        <span class="eyebrow" style="justify-content:center">
            Donde crece el fútbol amateur
        </span>

        <h2 class="font-display-x font-black leading-[0.9] tracking-[-0.03em] mt-5 mb-5 text-text"
            style="font-size: clamp(40px, 6vw, 80px)">
            Tu próxima temporada<br>empieza en FutGO.
        </h2>

        <p class="text-[18px] text-muted max-w-[48ch] mx-auto mb-8 leading-relaxed">
            Crea tu primer torneo gratis. Sin tarjeta, sin instalar nada.
            Listo para tu próxima jornada.
        </p>

        <div class="flex flex-wrap gap-3 justify-center">
            @guest
                <a href="{{ route('register') }}"     class="btn btn-primary btn-lg">Empezar gratis</a>
                <a href="{{ route('torneos.public.index') }}" class="btn btn-secondary btn-lg">Ver torneos públicos</a>
            @else
                <a href="{{ route('inicio') }}" class="btn btn-primary btn-lg">Ir a mi panel</a>
            @endguest
        </div>

    </div>
</section>
