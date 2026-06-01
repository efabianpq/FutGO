{{-- Sección ecosistema: descripción a la izquierda, diagrama circular a la derecha --}}
<section class="py-20 border-b border-border" id="eco">
    <div class="max-w-[1200px] mx-auto px-6">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">

            {{-- ── TEXTO ──────────────────────────────── --}}
            <div>
                <span class="eyebrow">La visión</span>
                <h2 class="font-display-x font-black leading-none tracking-[-0.02em] mt-3.5 mb-4 text-text"
                    style="font-size: clamp(34px, 4.5vw, 52px)">
                    Un ecosistema,<br>no una app suelta
                </h2>
                <p class="text-[17px] text-muted max-w-[46ch] leading-relaxed">
                    FutGO conecta a todos los actores del fútbol amateur en una sola capa:
                    cuando el organizador programa, el jugador recibe; cuando termina el
                    partido, la estadística y el patrocinador se actualizan.
                    Eso es un sistema operativo.
                </p>

                <div class="mt-6 space-y-3">
                    <div class="flex items-center gap-3">
                        <span class="badge badge-green">Organizadores</span>
                        <span class="text-[14px] text-muted">gestionan torneos y canchas</span>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="badge badge-green">Jugadores</span>
                        <span class="text-[14px] text-muted">construyen su historial y rating</span>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="badge badge-green">Patrocinadores</span>
                        <span class="text-[14px] text-muted">llegan a una audiencia medible</span>
                    </div>
                </div>
            </div>

            {{-- ── DIAGRAMA ────────────────────────────── --}}
            <div class="relative aspect-square max-w-[440px] mx-auto w-full">

                {{-- Líneas SVG hacia los nodos --}}
                <svg class="absolute inset-0 w-full h-full" viewBox="0 0 100 100"
                     fill="none" stroke="var(--color-border)" stroke-width="1">
                    <line x1="50" y1="50" x2="50" y2="14"/>
                    <line x1="50" y1="50" x2="84" y2="68"/>
                    <line x1="50" y1="50" x2="16" y2="68"/>
                    <line x1="50" y1="50" x2="50" y2="86"/>
                </svg>

                {{-- Centro --}}
                <div class="absolute inset-[34%] rounded-full bg-green text-on-green shadow-glow z-10
                            flex items-center justify-center
                            font-display-x font-black text-[22px]">
                    FutGO
                </div>

                {{-- Nodos --}}
                <div class="absolute w-[30%] top-0 left-[35%] bg-surface border border-border rounded-md
                            p-2.5 text-center font-semibold text-[13px] text-text z-10">
                    <span class="block font-mono text-[10px] tracking-[.1em] uppercase text-green mb-0.5">Hub</span>
                    Organizador
                </div>
                <div class="absolute w-[30%] top-[56%] right-0 bg-surface border border-border rounded-md
                            p-2.5 text-center font-semibold text-[13px] text-text z-10">
                    <span class="block font-mono text-[10px] tracking-[.1em] uppercase text-green mb-0.5">App</span>
                    Jugadores
                </div>
                <div class="absolute w-[30%] top-[56%] left-0 bg-surface border border-border rounded-md
                            p-2.5 text-center font-semibold text-[13px] text-text z-10">
                    <span class="block font-mono text-[10px] tracking-[.1em] uppercase text-green mb-0.5">Red</span>
                    Canchas
                </div>
                <div class="absolute w-[30%] bottom-0 left-[35%] bg-surface border border-border rounded-md
                            p-2.5 text-center font-semibold text-[13px] text-text z-10">
                    <span class="block font-mono text-[10px] tracking-[.1em] uppercase text-green mb-0.5">B2B</span>
                    Patrocinio
                </div>

            </div>
        </div>
    </div>
</section>
