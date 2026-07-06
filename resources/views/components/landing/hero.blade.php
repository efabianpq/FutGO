{{--
    Hero de la landing · mensaje central + CTA único + visual con foto real
    (placeholder local) y dos tarjetas flotantes (resultado + reputación).
--}}
<section class="pt-14 pb-10 relative" id="top">
    <div class="max-w-[1120px] mx-auto px-6">
        <div class="grid items-center gap-[52px] md:[grid-template-columns:1.04fr_.96fr]">

            {{-- Columna de texto --}}
            <div>
                <span class="inline-flex items-center gap-2 font-mono text-[12px] uppercase rounded-pill px-3 py-1.5 mb-[22px]"
                      style="letter-spacing:.1em; color:var(--color-green-strong); background:var(--color-green-tint)">
                    El fútbol amateur de tu ciudad, en un solo lugar
                </span>

                <h1 class="font-x font-extrabold text-text m-0"
                    style="font-stretch:112%; font-size:clamp(40px,5.4vw,68px); line-height:1.02; letter-spacing:-.025em">
                    Organiza tu torneo. <em class="not-italic text-green">Juega.</em> Conéctate con tu comunidad.
                </h1>

                <p class="text-[19px] leading-relaxed text-muted mt-[22px]" style="max-width:46ch">
                    Crea y gestiona torneos completos, lleva tu carrera como jugador y encuentra con quién jugar
                    — todo gratis y sin esperar la aprobación de nadie.
                </p>

                <div class="flex items-center gap-4 mt-8 flex-wrap">
                    <a href="{{ route('register') }}" class="btn btn-primary" style="height:56px; padding:0 30px; font-size:17px; border-radius:12px; box-shadow:var(--glow)">Crear cuenta gratis</a>
                    <a href="#organiza" class="inline-flex items-center gap-1.5 text-[15px] font-semibold text-green hover:text-green-strong transition-colors">
                        Ver cómo funciona
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-4 h-4"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                    </a>
                </div>

                <div class="flex gap-5 mt-[22px] flex-wrap">
                    @foreach(['Registro inmediato', 'Sin aprobaciones', 'Gratis para empezar'] as $r)
                        <span class="flex items-center gap-2 text-[13.5px] text-muted">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" class="w-4 h-4 text-green shrink-0"><path d="M20 6 9 17l-5-5"/></svg>
                            {{ $r }}
                        </span>
                    @endforeach
                </div>
            </div>

            {{-- Columna visual --}}
            <div class="relative mx-auto md:mx-0 max-w-[420px] w-full">
                {{-- Foto real (reemplazar el placeholder por una foto de cancha/equipo/barrio) --}}
                <div class="w-full rounded-[22px] overflow-hidden shadow-card-2" style="aspect-ratio:4/5; background:var(--color-surface-2)">
                    <img src="/FutGO/landing/hero-placeholder.svg" alt="Comunidad de fútbol amateur jugando en la cancha del barrio"
                         class="w-full h-full object-cover">
                </div>

                {{-- Tarjeta flotante: resultado --}}
                <div class="absolute -bottom-6 -left-4 sm:-left-6 w-[238px] bg-surface border border-border rounded-lg shadow-card-2 p-3.5">
                    <x-mockup.result-card radius="8px" />
                </div>

                {{-- Chip flotante: reputación --}}
                <div class="absolute top-6 -right-3 sm:-right-6 w-[190px] bg-surface border border-border rounded-lg shadow-card-2 px-4 py-3.5">
                    <div class="font-mono text-[10px] uppercase text-subtle mb-1.5" style="letter-spacing:.1em">Reputación</div>
                    <div class="flex items-center gap-2">
                        <div class="font-x font-extrabold text-[30px] text-green" style="font-stretch:118%">9.2</div>
                        <div class="text-[11px] text-muted leading-tight">Fair play<br><b class="text-text">excelente</b></div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</section>
