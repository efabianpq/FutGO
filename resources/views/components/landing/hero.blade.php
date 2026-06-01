{{--
    Hero A · Split
    Izquierda: eyebrow → H1 → subtítulo → CTAs → trust bar
    Derecha  : mock browser con partido en vivo + stats
--}}
<section class="relative overflow-hidden py-16 lg:py-20">

    {{-- Glow de fondo --}}
    <div class="pointer-events-none absolute inset-0 -z-10"
         style="background: radial-gradient(70% 90% at 80% -10%, rgba(0,230,118,.16), transparent 55%);"></div>

    <div class="max-w-[1200px] mx-auto px-6
                grid grid-cols-1 lg:grid-cols-[1.05fr_0.95fr]
                gap-12 items-center">

        {{-- ── IZQUIERDA ───────────────────────────── --}}
        <div>
            <span class="eyebrow">El sistema operativo del fútbol amateur</span>

            <h1 class="font-display-x font-black text-text leading-[0.92] tracking-[-0.03em] mt-5"
                style="font-size: clamp(48px, 6.5vw, 84px)">
                Organiza torneos<br>
                como un <em class="not-italic text-green">profesional.</em>
            </h1>

            <p class="text-[19px] text-muted mt-6 max-w-[46ch] leading-relaxed">
                FutGO reúne torneos, equipos, estadísticas y reservas de cancha
                en un solo lugar. Vos organizás; nosotros calculamos tablas,
                calendarios y goleo en automático.
            </p>

            {{-- CTAs --}}
            <div class="flex flex-wrap gap-3 mt-8">
                @guest
                    <a href="{{ route('register') }}"   class="btn btn-primary btn-lg">Crear mi torneo</a>
                    <a href="{{ route('how-it-works') }}" class="btn btn-secondary btn-lg">Ver demo</a>
                @else
                    <a href="{{ route('admin.torneos.index') }}" class="btn btn-primary btn-lg">Mis torneos</a>
                    <a href="{{ route('how-it-works') }}"        class="btn btn-secondary btn-lg">Ver demo</a>
                @endguest
            </div>

            {{-- Trust bar --}}
            <div class="flex flex-wrap gap-7 mt-9 pt-6 border-t border-border">
                <div>
                    <div class="font-display-x font-extrabold text-[26px] tabular-nums text-text">2,400+</div>
                    <div class="text-[12.5px] text-subtle mt-0.5">torneos creados</div>
                </div>
                <div>
                    <div class="font-display-x font-extrabold text-[26px] tabular-nums text-text">38K</div>
                    <div class="text-[12.5px] text-subtle mt-0.5">jugadores activos</div>
                </div>
                <div>
                    <div class="font-display-x font-extrabold text-[26px] tabular-nums text-text">94%</div>
                    <div class="text-[12.5px] text-subtle mt-0.5">renuevan temporada</div>
                </div>
            </div>
        </div>

        {{-- ── DERECHA: mock browser ────────────────── --}}
        <div class="bg-surface border border-border rounded-2xl p-4 shadow-float">

            {{-- Chrome del navegador --}}
            <div class="flex items-center gap-2 px-1.5 pb-3.5">
                <span class="w-2.5 h-2.5 rounded-full bg-border"></span>
                <span class="w-2.5 h-2.5 rounded-full bg-border"></span>
                <span class="w-2.5 h-2.5 rounded-full bg-border"></span>
                <span class="ml-auto font-mono text-[11px] text-subtle">futgo.app / copa-pachon</span>
            </div>

            {{-- Partido en vivo --}}
            <div class="match" style="background: var(--color-surface-2)">
                <div class="top">
                    <span class="league">Copa Pachón · MD07</span>
                    <span class="badge badge-live">67'</span>
                </div>
                <div class="teams">
                    <div class="side">
                        <span class="crest">RR</span>
                        <span class="tn">Real Roma</span>
                    </div>
                    <div class="score">2<span class="x">:</span>1</div>
                    <div class="side away">
                        <span class="crest">DV</span>
                        <span class="tn">Dep. Valle</span>
                    </div>
                </div>
            </div>

            {{-- Mini stats --}}
            <div class="grid grid-cols-2 gap-3.5 mt-3.5">
                <div class="stat" style="background: var(--color-surface-2)">
                    <div class="lbl">Goleo líder</div>
                    <div class="val tabular-nums" style="font-size: 30px">12</div>
                    <div class="text-[12px] text-subtle mt-1">A. Soto · #9</div>
                </div>
                <div class="stat" style="background: var(--color-surface-2)">
                    <div class="lbl">Inscripción</div>
                    <div class="val tabular-nums" style="font-size: 30px">68%</div>
                    <div class="progress mt-2">
                        <i style="width: 68%"></i>
                    </div>
                </div>
            </div>
        </div>

    </div>
</section>
