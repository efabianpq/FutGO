{{--
    Hero B · Centrado
    Eyebrow centrado → H1 display grande → subtítulo → CTAs centrados
    → banda de 4 métricas → mock ancho con tabla de posiciones
--}}
<section class="relative overflow-hidden py-20 pb-16 text-center">

    {{-- Glow superior centrado --}}
    <div class="pointer-events-none absolute inset-0 -z-10"
         style="background: radial-gradient(60% 60% at 50% 0%, rgba(0,230,118,.14), transparent 60%);"></div>

    <div class="max-w-[1200px] mx-auto px-6">

        {{-- Eyebrow centrado --}}
        <span class="eyebrow" style="justify-content:center">
            Donde crece el fútbol amateur
        </span>

        {{-- H1 display --}}
        <h1 class="font-display-x font-black text-text mx-auto mt-5"
            style="font-size: clamp(52px, 8.5vw, 116px); line-height: .86; letter-spacing: -.035em; max-width: 14ch;">
            El fútbol amateur<br>
            tiene un nuevo <em class="not-italic text-green">hogar.</em>
        </h1>

        {{-- Subtítulo --}}
        <p class="text-[20px] text-muted mx-auto mt-6 leading-relaxed"
           style="max-width: 54ch">
            Una sola plataforma para gestionar torneos, equipos, estadísticas
            y canchas. FutGO se está convirtiendo en el sistema operativo
            del fútbol base.
        </p>

        {{-- CTAs --}}
        <div class="flex flex-wrap gap-3 mt-9 justify-center">
            @guest
                <a href="{{ route('register') }}"     class="btn btn-primary btn-lg">Empezar gratis</a>
                <a href="{{ route('how-it-works') }}" class="btn btn-outline btn-lg">Hablar con ventas</a>
            @else
                <a href="{{ route('inicio') }}"       class="btn btn-primary btn-lg">Ir a mi panel</a>
                <a href="{{ route('how-it-works') }}" class="btn btn-outline btn-lg">Ver demo</a>
            @endguest
        </div>

        {{-- Banda de métricas --}}
        <div class="flex justify-center mt-12
                    border border-border rounded-lg overflow-hidden bg-surface
                    flex-wrap">
            @foreach([
                ['2,400+', 'torneos'],
                ['38K',    'jugadores'],
                ['540',    'canchas conectadas'],
                ['11',     'países'],
            ] as [$n, $l])
                <div class="flex-1 min-w-[120px] px-8 py-5
                            border-r border-border-soft last:border-r-0">
                    <div class="font-display-x font-extrabold text-[32px] tabular-nums text-text leading-none">
                        {{ $n }}
                    </div>
                    <div class="text-[12.5px] text-subtle mt-1">{{ $l }}</div>
                </div>
            @endforeach
        </div>

        {{-- Mock ancho: tabla de posiciones --}}
        <div class="mt-12 mx-auto bg-surface border border-border rounded-2xl p-4 shadow-float text-left"
             style="max-width: 880px">

            {{-- Chrome del navegador --}}
            <div class="flex items-center gap-2 px-1.5 pb-3.5">
                <span class="w-2.5 h-2.5 rounded-full bg-border"></span>
                <span class="w-2.5 h-2.5 rounded-full bg-border"></span>
                <span class="w-2.5 h-2.5 rounded-full bg-border"></span>
                <span class="ml-auto font-mono text-[11px] text-subtle">futgo.app / liga-nocturna / tabla</span>
            </div>

            {{-- Tabla de posiciones --}}
            <table class="fg-table" style="border:none">
                <thead>
                    <tr>
                        <th style="text-align:center">#</th>
                        <th>Equipo</th>
                        <th style="text-align:center">PJ</th>
                        <th>Forma</th>
                        <th style="text-align:right">Pts</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="me">
                        <td class="num r1">1</td>
                        <td>
                            <div class="team-cell">
                                <span class="crest"
                                      style="background:var(--color-green-tint);color:var(--color-green);border-color:transparent">RR</span>
                                Real Roma
                            </div>
                        </td>
                        <td style="text-align:center" class="tabular-nums">7</td>
                        <td>
                            <span class="form">
                                <i class="w">G</i><i class="w">G</i><i class="d">E</i><i class="w">G</i>
                            </span>
                        </td>
                        <td class="pts">15</td>
                    </tr>
                    <tr>
                        <td class="num">2</td>
                        <td>
                            <div class="team-cell">
                                <span class="crest">LU</span>
                                Lobos FC
                            </div>
                        </td>
                        <td style="text-align:center" class="tabular-nums">7</td>
                        <td>
                            <span class="form">
                                <i class="w">G</i><i class="l">P</i><i class="w">G</i><i class="w">G</i>
                            </span>
                        </td>
                        <td class="pts">13</td>
                    </tr>
                    <tr>
                        <td class="num">3</td>
                        <td>
                            <div class="team-cell">
                                <span class="crest">AT</span>
                                Atlético 22
                            </div>
                        </td>
                        <td style="text-align:center" class="tabular-nums">7</td>
                        <td>
                            <span class="form">
                                <i class="d">E</i><i class="w">G</i><i class="d">E</i><i class="w">G</i>
                            </span>
                        </td>
                        <td class="pts">11</td>
                    </tr>
                </tbody>
            </table>

        </div>

    </div>
</section>
