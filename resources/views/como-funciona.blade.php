@extends('layouts.app')

@section('title', '¿Cómo funciona?')

@php
    $videoEmbed = \App\Support\Settings::videoEmbedUrl();
    $whatsapp = 'https://wa.me/573013966515';
    $cupoCop = 30000;

    // Índice de navegación rápida
    $sections = [
        ['id' => 'video',         'icon' => '▶️', 'label' => 'Video explicativo'],
        ['id' => 'puntos',        'icon' => '🏆', 'label' => 'Cómo se acumulan los puntos'],
        ['id' => 'premio',        'icon' => '💰', 'label' => 'Cómo se reparte el premio'],
        ['id' => 'pronosticos',   'icon' => '⚽', 'label' => 'Cómo ingreso mis pronósticos'],
        ['id' => 'ranking',       'icon' => '📊', 'label' => 'Cómo funciona el ranking'],
        ['id' => 'inscripcion',   'icon' => '📝', 'label' => 'Cómo me inscribo'],
    ];
@endphp

@section('content')
<div class="max-w-5xl mx-auto px-4 py-8 sm:py-12">

    {{-- ─────────────── HERO + ÍNDICE ─────────────── --}}
    <div class="text-center mb-8 sm:mb-10">
        <p class="eyebrow justify-center">Guía para nuevos jugadores</p>
        <h1 class="font-display font-bold text-display-l sm:text-[80px] text-pitch uppercase mt-3 leading-[0.92]"
            style="font-size: clamp(40px, 8vw, 80px);">
            ¿Cómo<br><span class="text-gol">funciona</span>?
        </h1>
        <p class="font-body text-body sm:text-body-l text-ink-soft mt-4 max-w-2xl mx-auto">
            Todo lo que necesitás saber para participar en la polla del Mundial 2026.
            Saltá directo a la sección que te interesa.
        </p>
    </div>

    {{-- Índice navegación rápida --}}
    <nav class="grid grid-cols-2 md:grid-cols-3 gap-2 sm:gap-3 mb-10 sm:mb-16">
        @foreach ($sections as $i => $s)
            <a href="#{{ $s['id'] }}"
               class="group bg-white border border-line rounded-md shadow-card px-3 sm:px-4 py-3 sm:py-4 flex items-center gap-3 hover:border-pitch hover:bg-bone-soft transition-all duration-fast">
                <span class="text-2xl shrink-0">{{ $s['icon'] }}</span>
                <span class="font-display font-bold text-[12px] sm:text-[13px] uppercase tracking-wide-cta text-pitch leading-tight">
                    {{ $s['label'] }}
                </span>
                <span class="ml-auto text-pitch font-display font-bold opacity-0 group-hover:opacity-100 transition-opacity duration-fast">→</span>
            </a>
        @endforeach
    </nav>

    {{-- ═════════════════ SECCIÓN 1: VIDEO ═════════════════ --}}
    <section id="video" class="py-8 sm:py-12 border-b border-line">
        <p class="eyebrow">Sección 01 · Video</p>
        <h2 class="font-display font-bold text-display-m sm:text-display-l text-pitch uppercase mt-2 mb-2 leading-[0.96]"
            style="font-size: clamp(28px, 4vw, 48px);">
            Mira cómo funciona en <span class="text-gol">60 segundos</span>
        </h2>
        <p class="text-body text-ink-soft mb-6">Una vista rápida en video — ideal si preferís ver antes de leer.</p>

        @if ($videoEmbed)
            <div class="aspect-video w-full rounded-md overflow-hidden bg-black shadow-card-2 border border-line">
                <iframe src="{{ $videoEmbed }}"
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
                <p class="font-mono text-[11px] tracking-wide-label uppercase text-ink-mute mt-2">El administrador puede configurarlo desde el panel</p>
            </div>
        @endif
    </section>

    {{-- ═════════════════ SECCIÓN 2: PUNTOS ═════════════════ --}}
    <section id="puntos" class="py-8 sm:py-12 border-b border-line">
        <p class="eyebrow">Sección 02 · Puntos</p>
        <h2 class="font-display font-bold text-display-m sm:text-display-l text-pitch uppercase mt-2 mb-2 leading-[0.96]"
            style="font-size: clamp(28px, 4vw, 48px);">
            ¿Cómo se <span class="text-gol">acumulan los puntos</span>?
        </h2>
        <p class="text-body text-ink-soft mb-6 max-w-2xl">
            Cada partido puede sumarte entre 0 y 5 puntos según qué tan certero estuvo tu pronóstico.
        </p>

        {{-- Tabla de puntos --}}
        <div class="space-y-3">
            @php
                $tablaPuntos = [
                    ['pts' => 5, 'stars' => '⭐⭐⭐⭐⭐', 'cond' => 'Marcador exacto',
                     'ej' => 'Pronosticaste 2-1 → Resultado 2-1', 'class' => 'bg-gol text-on-green border-gol-deep'],
                    ['pts' => 3, 'stars' => '⭐⭐⭐', 'cond' => 'Ganador correcto + un gol exacto',
                     'ej' => 'Pronosticaste 2-1 → Resultado 3-1 (acertaste al visitante)', 'class' => 'bg-pitch text-bone border-pitch-deep'],
                    ['pts' => 2, 'stars' => '⭐⭐', 'cond' => 'Solo el ganador correcto',
                     'ej' => 'Pronosticaste 2-0 → Resultado 3-1', 'class' => 'bg-pitch-mist text-pitch border-pitch'],
                    ['pts' => 1, 'stars' => '⭐', 'cond' => 'Un gol exacto pero ganador incorrecto',
                     'ej' => 'Pronosticaste 1-1 → Resultado 1-2 (acertaste local)', 'class' => 'bg-gol/30 text-pitch-deep border-gol/50'],
                    ['pts' => 0, 'stars' => '—', 'cond' => 'Ningún acierto o sin pronóstico',
                     'ej' => '—', 'class' => 'bg-line-soft text-ink-mute border-line'],
                ];
            @endphp

            @foreach ($tablaPuntos as $row)
                <div class="bg-white border border-line rounded-md shadow-card overflow-hidden">
                    <div class="grid grid-cols-[80px_1fr] sm:grid-cols-[120px_1fr] items-stretch">
                        {{-- Columna de puntos coloreada --}}
                        <div class="{{ $row['class'] }} border-r flex flex-col items-center justify-center p-3 text-center">
                            <p class="font-display font-extrabold text-display-m leading-none">{{ $row['pts'] }}</p>
                            <p class="font-mono text-[10px] tracking-wide-label uppercase mt-1">pts</p>
                            <p class="text-[11px] mt-1">{{ $row['stars'] }}</p>
                        </div>
                        {{-- Condición + ejemplo --}}
                        <div class="p-4">
                            <p class="font-display font-bold text-body uppercase tracking-[.02em] text-pitch">{{ $row['cond'] }}</p>
                            <p class="text-body-s text-ink-soft mt-1">{{ $row['ej'] }}</p>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Notas aclaratorias --}}
        <div class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-3">
            <div class="bg-bone-soft border border-line rounded-md p-4">
                <p class="font-display font-bold text-[12px] uppercase tracking-wide-cta text-pitch">⚠ Importante</p>
                <p class="text-body-s text-ink-soft mt-1">Solo aplica para <strong>tiempo reglamentario</strong>. Los penales y prórrogas <strong>NO cuentan</strong>.</p>
            </div>
            <div class="bg-bone-soft border border-line rounded-md p-4">
                <p class="font-display font-bold text-[12px] uppercase tracking-wide-cta text-pitch">🎯 Desempate</p>
                <p class="text-body-s text-ink-soft mt-1">Si dos usuarios empatan en puntos, gana quien haya acertado <strong>más marcadores exactos</strong>.</p>
            </div>
        </div>
    </section>

    {{-- ═════════════════ SECCIÓN 3: PREMIO ═════════════════ --}}
    <section id="premio" class="py-8 sm:py-12 border-b border-line">
        <p class="eyebrow">Sección 03 · Premio</p>
        <h2 class="font-display font-bold text-display-m sm:text-display-l text-pitch uppercase mt-2 mb-2 leading-[0.96]"
            style="font-size: clamp(28px, 4vw, 48px);">
            ¿Cómo se <span class="text-gol">reparte el premio</span>?
        </h2>
        <p class="text-body text-ink-soft mb-6 max-w-2xl">
            El premio depende del número de participantes. Cada cupo tiene un valor de
            <strong class="text-pitch">${{ number_format($cupoCop, 0, ',', '.') }} COP</strong>.
            El total recaudado se reparte 100% entre los tres primeros puestos así:
        </p>

        {{-- Tabla de distribución --}}
        <div class="bg-white border border-line rounded-md shadow-card overflow-hidden mb-6">
            <table class="w-full">
                <thead class="bg-pitch text-bone">
                    <tr class="font-mono text-[10.5px] tracking-wide-label uppercase text-left">
                        <th class="px-4 py-2.5">Posición</th>
                        <th class="px-4 py-2.5 text-right">Porcentaje</th>
                        <th class="px-4 py-2.5 text-right">Ejemplo (20 participantes — ${{ number_format(20*$cupoCop, 0, ',', '.') }})</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line-soft">
                    <tr class="bg-gol/5">
                        <td class="px-4 py-3 font-display font-bold text-display-s text-pitch">🥇 1er lugar</td>
                        <td class="px-4 py-3 text-right font-display font-extrabold text-display-s text-gol-deep">60%</td>
                        <td class="px-4 py-3 text-right font-mono font-bold text-pitch">${{ number_format(20*$cupoCop*0.60, 0, ',', '.') }}</td>
                    </tr>
                    <tr>
                        <td class="px-4 py-3 font-display font-bold text-display-s text-pitch">🥈 2do lugar</td>
                        <td class="px-4 py-3 text-right font-display font-extrabold text-display-s text-ink-soft">25%</td>
                        <td class="px-4 py-3 text-right font-mono font-bold text-pitch">${{ number_format(20*$cupoCop*0.25, 0, ',', '.') }}</td>
                    </tr>
                    <tr>
                        <td class="px-4 py-3 font-display font-bold text-display-s text-pitch">🥉 3er lugar</td>
                        <td class="px-4 py-3 text-right font-display font-extrabold text-display-s text-[#b87333]">15%</td>
                        <td class="px-4 py-3 text-right font-mono font-bold text-pitch">${{ number_format(20*$cupoCop*0.15, 0, ',', '.') }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        {{-- ───── CALCULADORA ───── --}}
        <div class="bg-pitch text-bone rounded-md shadow-card-2 p-5 sm:p-6"
             x-data="prizeCalculator({{ $cupoCop }})">
            <p class="eyebrow !text-gol mb-2"><span>—</span> Calculadora</p>
            <h3 class="font-display font-bold text-display-s sm:text-display-m text-bone uppercase mb-4 leading-tight">
                Probá con cuántos participantes querés:
            </h3>

            <div class="flex flex-col sm:flex-row sm:items-center gap-4 mb-5">
                <input type="range" min="2" max="200" x-model.number="participants"
                       class="flex-1 accent-gol h-2 rounded-pill bg-pitch-deep cursor-pointer">
                <input type="number" min="2" max="200" x-model.number="participants"
                       class="w-24 h-10 text-center font-display font-bold text-[20px] text-pitch bg-bone border-2 border-gol rounded-md focus:ring-0 focus:border-gol">
            </div>

            <div class="bg-pitch-deep rounded-md p-4 mb-4">
                <p class="font-mono text-[10.5px] tracking-wide-label uppercase opacity-70 mb-1">Acumulado total</p>
                <p class="font-display font-extrabold text-display-l leading-none text-gol"
                   x-text="formatMoney(participants * cupo)"></p>
            </div>

            <div class="grid grid-cols-3 gap-2 sm:gap-3">
                <div class="bg-gol text-on-green rounded-md p-3 text-center">
                    <p class="font-display font-bold text-[20px] sm:text-[28px] leading-none">🥇</p>
                    <p class="font-mono text-[10px] tracking-wide-label uppercase mt-1">60%</p>
                    <p class="font-display font-extrabold text-[15px] sm:text-[20px] mt-1 leading-none"
                       x-text="formatMoney(participants * cupo * 0.60)"></p>
                </div>
                <div class="bg-pitch-mist text-pitch rounded-md p-3 text-center">
                    <p class="font-display font-bold text-[20px] sm:text-[28px] leading-none">🥈</p>
                    <p class="font-mono text-[10px] tracking-wide-label uppercase mt-1">25%</p>
                    <p class="font-display font-extrabold text-[15px] sm:text-[20px] mt-1 leading-none"
                       x-text="formatMoney(participants * cupo * 0.25)"></p>
                </div>
                <div class="bg-gol/30 text-pitch-deep rounded-md p-3 text-center">
                    <p class="font-display font-bold text-[20px] sm:text-[28px] leading-none">🥉</p>
                    <p class="font-mono text-[10px] tracking-wide-label uppercase mt-1">15%</p>
                    <p class="font-display font-extrabold text-[15px] sm:text-[20px] mt-1 leading-none"
                       x-text="formatMoney(participants * cupo * 0.15)"></p>
                </div>
            </div>

            <p class="font-mono text-[10.5px] tracking-wide-label uppercase opacity-70 mt-4 text-center">
                Estos valores son simulados — el premio real se define con el número final de inscritos
            </p>
        </div>

        {{-- Notas --}}
        <div class="mt-6 bg-bone-soft border border-line rounded-md p-4">
            <p class="font-display font-bold text-[12px] uppercase tracking-wide-cta text-pitch">🎯 Desempate</p>
            <p class="text-body-s text-ink-soft mt-1">
                En caso de empate en puntos totales, el criterio de desempate es el número de
                <strong>marcadores exactos acertados</strong> (pronósticos de 5 puntos).
            </p>
        </div>

        <script>
            function prizeCalculator(cupo) {
                return {
                    participants: 20,
                    cupo: cupo,
                    formatMoney(n) {
                        try {
                            return new Intl.NumberFormat('es-CO', {
                                style: 'currency', currency: 'COP', maximumFractionDigits: 0,
                            }).format(n);
                        } catch (e) { return '$ ' + n; }
                    },
                };
            }
        </script>
    </section>

    {{-- ═════════════════ SECCIÓN 4: PRONÓSTICOS ═════════════════ --}}
    <section id="pronosticos" class="py-8 sm:py-12 border-b border-line">
        <p class="eyebrow">Sección 04 · Pronósticos</p>
        <h2 class="font-display font-bold text-display-m sm:text-display-l text-pitch uppercase mt-2 mb-2 leading-[0.96]"
            style="font-size: clamp(28px, 4vw, 48px);">
            ¿Cómo <span class="text-gol">ingreso</span> mis pronósticos?
        </h2>
        <p class="text-body text-ink-soft mb-6 max-w-2xl">5 pasos simples — todo desde el celular.</p>

        <ol class="space-y-3">
            @php
                $pasos = [
                    ['icon' => '📋', 'title' => 'Paso 1 — Abrí "Mis Pronósticos"', 'desc' => 'Verás todos los partidos del Mundial organizados por fase y fecha.'],
                    ['icon' => '✏️', 'title' => 'Paso 2 — Ingresá el marcador',     'desc' => 'Para cada partido, escribí el resultado que creés que será el final (solo tiempo reglamentario).'],
                    ['icon' => '⏰', 'title' => 'Paso 3 — Tenés tiempo hasta el cierre', 'desc' => 'Hasta 5 minutos antes de que empiece el partido podés ingresar o modificar tu pronóstico.'],
                    ['icon' => '🔒', 'title' => 'Paso 4 — El bloqueo es automático',  'desc' => 'Pasado ese tiempo, el campo se bloquea. Si no ingresaste nada, ese partido suma 0 puntos.'],
                    ['icon' => '✅', 'title' => 'Paso 5 — Los puntos se calculan solos', 'desc' => 'Al finalizar el partido, el sistema actualiza automáticamente tu posición en el ranking.'],
                ];
            @endphp
            @foreach ($pasos as $p)
                <li class="bg-white border border-line rounded-md shadow-card p-4 flex items-start gap-4">
                    <span class="text-3xl sm:text-4xl shrink-0">{{ $p['icon'] }}</span>
                    <div class="min-w-0">
                        <p class="font-display font-bold text-display-s text-pitch uppercase tracking-[.01em]">{{ $p['title'] }}</p>
                        <p class="text-body text-ink-soft mt-1">{{ $p['desc'] }}</p>
                    </div>
                </li>
            @endforeach
        </ol>

        {{-- Nota destacada en gol --}}
        <div class="mt-6 bg-gol border-2 border-gol-deep rounded-md p-5 text-center">
            <p class="font-display font-extrabold text-display-s sm:text-display-m text-pitch uppercase leading-tight">
                ¡No esperes al último momento!
            </p>
            <p class="text-body text-pitch-deep mt-2">
                Ingresá tus pronósticos con anticipación para no quedarte sin puntos.
            </p>
        </div>
    </section>

    {{-- ═════════════════ SECCIÓN 5: RANKING ═════════════════ --}}
    <section id="ranking" class="py-8 sm:py-12 border-b border-line">
        <p class="eyebrow">Sección 05 · Ranking</p>
        <h2 class="font-display font-bold text-display-m sm:text-display-l text-pitch uppercase mt-2 mb-2 leading-[0.96]"
            style="font-size: clamp(28px, 4vw, 48px);">
            ¿Cómo funciona el <span class="text-gol">ranking</span>?
        </h2>
        <p class="text-body text-ink-soft mb-6 max-w-2xl">
            Es visible en todo momento para todos los participantes activos.
            La transparencia es total — cualquiera puede ver los pronósticos de cualquiera.
        </p>

        {{-- Tabla ejemplo --}}
        <div class="bg-white border border-line rounded-md overflow-hidden shadow-card mb-6">
            <div class="grid grid-cols-[40px_1fr_60px_60px] sm:grid-cols-[56px_1fr_72px_72px] gap-2 sm:gap-3 px-3 sm:px-4 py-2.5 bg-pitch text-bone font-mono text-[10.5px] tracking-wide-label uppercase">
                <span>Pos</span>
                <span>Nombre</span>
                <span class="hidden sm:block text-right">Exactos</span>
                <span class="text-right">Puntos</span>
            </div>
            @php
                $ejemploRanking = [
                    [1, 'Carlos M.',   8, 47, 'medal' => '🥇', 'color' => 'text-gol-deep'],
                    [2, 'María G.',    6, 41, 'medal' => '🥈', 'color' => 'text-[#8a8a8a]'],
                    [3, 'Andrés P.',   5, 35, 'medal' => '🥉', 'color' => 'text-[#b87333]'],
                    [4, 'Laura R.',    4, 28, 'medal' => null, 'color' => 'text-ink-soft'],
                    [5, 'Juan R.',     3, 22, 'medal' => null, 'color' => 'text-ink-soft'],
                ];
            @endphp
            @foreach ($ejemploRanking as $r)
                <div class="grid grid-cols-[40px_1fr_60px_60px] sm:grid-cols-[56px_1fr_72px_72px] gap-2 sm:gap-3 px-3 sm:px-4 py-3 items-center border-b border-line-soft last:border-b-0">
                    <span class="font-display font-extrabold text-[20px] {{ $r['color'] }}">
                        {{ $r['medal'] ?? $r[0] }}
                    </span>
                    <p class="font-semibold text-body-s text-ink truncate">{{ $r[1] }}</p>
                    <span class="hidden sm:block text-right font-mono text-[13px] text-gol-deep">{{ $r[2] }}</span>
                    <span class="text-right font-display font-extrabold text-[20px] text-pitch">{{ $r[3] }}</span>
                </div>
            @endforeach
        </div>

        {{-- Detalles --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <div class="bg-bone-soft border border-line rounded-md p-4">
                <p class="font-display font-bold text-[12px] uppercase tracking-wide-cta text-pitch">📊 Las columnas</p>
                <ul class="text-body-s text-ink-soft mt-2 space-y-1">
                    <li><strong>Pos:</strong> Tu posición actual</li>
                    <li><strong>Nombre:</strong> Click para ver sus pronósticos</li>
                    <li><strong>Exactos:</strong> Cantidad de partidos donde acertaste 5 pts</li>
                    <li><strong>Puntos:</strong> Total acumulado en el torneo</li>
                </ul>
            </div>
            <div class="bg-bone-soft border border-line rounded-md p-4">
                <p class="font-display font-bold text-[12px] uppercase tracking-wide-cta text-pitch">🏆 El ganador</p>
                <p class="text-body-s text-ink-soft mt-2">
                    Se define al finalizar el último partido del Mundial — la Final, el 19 de julio de 2026.
                </p>
                <p class="text-body-s text-ink-soft mt-2">
                    El ranking se actualiza automáticamente cada vez que el administrador ingresa
                    el resultado oficial de un partido.
                </p>
            </div>
        </div>
    </section>

    {{-- ═════════════════ SECCIÓN 6: INSCRIPCIÓN ═════════════════ --}}
    <section id="inscripcion" class="py-8 sm:py-12 border-b border-line">
        <p class="eyebrow">Sección 06 · Inscripción</p>
        <h2 class="font-display font-bold text-display-m sm:text-display-l text-pitch uppercase mt-2 mb-2 leading-[0.96]"
            style="font-size: clamp(28px, 4vw, 48px);">
            ¿Cómo me <span class="text-gol">inscribo</span>?
        </h2>
        <p class="text-body text-ink-soft mb-6 max-w-2xl">4 pasos. Empezás en menos de 10 minutos.</p>

        <ol class="space-y-3">
            <li class="bg-white border border-line rounded-md shadow-card p-5 flex items-start gap-4">
                <span class="text-4xl sm:text-5xl shrink-0">👤</span>
                <div class="min-w-0">
                    <p class="font-display font-bold text-display-s text-pitch uppercase">Paso 1 — Creá tu cuenta</p>
                    <p class="text-body text-ink-soft mt-1">
                        Entrá a <strong>soypachonmundial.online</strong>, hacé clic en
                        <a href="{{ route('register') }}" class="font-bold text-pitch underline hover:text-pitch-deep">Crear cuenta</a>
                        e ingresá tus datos: nombre, apellido, correo electrónico, contraseña y número de teléfono.
                    </p>
                </div>
            </li>

            <li class="bg-white border border-line rounded-md shadow-card p-5 flex items-start gap-4">
                <span class="text-4xl sm:text-5xl shrink-0">💸</span>
                <div class="min-w-0">
                    <p class="font-display font-bold text-display-s text-pitch uppercase">Paso 2 — Realizá tu pago</p>
                    <p class="text-body text-ink-soft mt-1">
                        Transferí <strong class="text-pitch">${{ number_format($cupoCop, 0, ',', '.') }} COP</strong> al número
                        <strong class="text-pitch font-mono">3013966515</strong> (Nequi o Daviplata).
                    </p>
                    <p class="text-body-s text-ink-mute mt-1">Este es el número del administrador de la plataforma.</p>
                </div>
            </li>

            <li class="bg-white border border-line rounded-md shadow-card p-5 flex items-start gap-4">
                <span class="text-4xl sm:text-5xl shrink-0">📲</span>
                <div class="min-w-0">
                    <p class="font-display font-bold text-display-s text-pitch uppercase">Paso 3 — Enviá tu comprobante</p>
                    <p class="text-body text-ink-soft mt-1">
                        Mandá el comprobante de pago por WhatsApp al mismo número.
                        Incluí tu <strong>nombre completo</strong> y el <strong>correo</strong> con el que te registraste.
                    </p>
                    <a href="{{ $whatsapp }}" target="_blank" rel="noopener"
                       class="inline-flex items-center gap-2 mt-3 font-display font-bold text-[13px] uppercase tracking-wide-cta px-4 py-2.5 rounded-md text-white transition-all duration-fast hover:opacity-90"
                       style="background-color: #25D366;">
                        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.149-.173.198-.297.297-.495.099-.198.05-.371-.025-.520-.074-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51l-.57-.01c-.198 0-.52.074-.792.371-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413z"/>
                        </svg>
                        Enviar comprobante por WhatsApp
                    </a>
                </div>
            </li>

            <li class="bg-white border border-line rounded-md shadow-card p-5 flex items-start gap-4">
                <span class="text-4xl sm:text-5xl shrink-0">🔑</span>
                <div class="min-w-0">
                    <p class="font-display font-bold text-display-s text-pitch uppercase">Paso 4 — Activá tu código</p>
                    <p class="text-body text-ink-soft mt-1">
                        Una vez verificado tu pago, recibirás un <strong>código de activación</strong> por WhatsApp.
                        Iniciá sesión en la plataforma e ingresá el código.
                    </p>
                    <p class="text-body font-display font-bold text-pitch mt-2">¡Listo, ya podés pronosticar!</p>
                </div>
            </li>
        </ol>

        {{-- Nota de soporte --}}
        <div class="mt-6 bg-alerta/10 border-2 border-alerta/40 rounded-md p-5">
            <p class="font-display font-bold text-[12px] uppercase tracking-wide-cta text-alerta">⚠ ¿Te quedaste en la pantalla de código?</p>
            <p class="text-body text-ink mt-2">
                Si al iniciar sesión <strong>siempre aparece la pantalla "Ingresá tu código de activación"</strong>,
                significa que tu pago aún no fue verificado o que aún no te enviaron el código.
            </p>
            <p class="text-body text-ink mt-2">
                Escribinos al WhatsApp y te ayudamos a resolverlo en minutos.
            </p>
        </div>
    </section>

    {{-- ═════════════════ CONTACTO FINAL ═════════════════ --}}
    <section class="py-10 sm:py-14 text-center">
        <p class="eyebrow justify-center">¿Más preguntas?</p>
        <h2 class="font-display font-bold text-display-m text-pitch uppercase mt-3 mb-4 leading-tight"
            style="font-size: clamp(28px, 4vw, 44px);">
            Estamos a un mensaje de distancia
        </h2>
        <p class="text-body sm:text-body-l text-ink-soft max-w-prose mx-auto mb-6">
            Si tenés alguna duda adicional, escribinos directamente por WhatsApp.
            Te respondemos lo más pronto posible.
        </p>
        <a href="{{ $whatsapp }}" target="_blank" rel="noopener"
           class="inline-flex items-center gap-3 font-display font-bold text-[15px] sm:text-[18px] uppercase tracking-wide-cta px-6 py-3.5 rounded-md text-white shadow-card-2 transition-all duration-fast hover:opacity-90"
           style="background-color: #25D366;">
            <svg class="w-6 h-6" viewBox="0 0 24 24" fill="currentColor">
                <path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.149-.173.198-.297.297-.495.099-.198.05-.371-.025-.520-.074-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51l-.57-.01c-.198 0-.52.074-.792.371-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413z"/>
            </svg>
            Contactar por WhatsApp
        </a>
        <p class="font-mono text-[11px] tracking-wide-label uppercase text-ink-mute mt-4">
            +57 301 396 6515
        </p>
    </section>
</div>
@endsection
