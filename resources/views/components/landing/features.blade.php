{{-- 6 feature cards --}}
@php
$features = [
    [
        'title' => 'Torneos automáticos',
        'desc'  => 'Crea ligas, copas o grupos+eliminatoria. FutGO genera fixture, descansos y tabla en segundos.',
        'icon'  => '<path d="M8 21V11M16 21V7M12 21V3"/>',
    ],
    [
        'title' => 'Estadísticas vivas',
        'desc'  => 'Goles, asistencias, tarjetas, MVP y rating por jugador — calculados partido a partido, sin discusiones.',
        'icon'  => '<path d="M4 20V10M10 20V4M16 20V14M22 20H2"/>',
    ],
    [
        'title' => 'Reserva de canchas',
        'desc'  => 'Conecta instalaciones, asigna horarios y evitá choques de agenda con disponibilidad en tiempo real.',
        'icon'  => '<path d="M12 21s-7-4.5-7-10a7 7 0 0 1 14 0c0 5.5-7 10-7 10Z"/><circle cx="12" cy="11" r="2.5"/>',
    ],
    [
        'title' => 'Equipos y plantillas',
        'desc'  => 'Roster, dorsales, elegibilidad y sanciones. Cada jugador con su perfil e historial verificable.',
        'icon'  => '<circle cx="9" cy="8" r="3"/><circle cx="17" cy="9" r="2.4"/><path d="M3 20c0-3 2.7-5 6-5s6 2 6 5"/>',
    ],
    [
        'title' => 'Calendario inteligente',
        'desc'  => 'Reprogramá con un clic, notificá a los equipos y mantenelos sincronizados automáticamente.',
        'icon'  => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 9h18M8 3v4M16 3v4"/>',
    ],
    [
        'title' => 'Patrocinios',
        'desc'  => 'Espacios de marca, métricas de audiencia y reportes listos para mostrar a tus patrocinadores.',
        'icon'  => '<path d="M12 2 4 6v6c0 5 3.5 8 8 10 4.5-2 8-5 8-10V6l-8-4Z"/>',
    ],
];
@endphp

<section class="py-20 border-b border-border" id="features">
    <div class="max-w-[1200px] mx-auto px-6">

        {{-- Section head --}}
        <div class="max-w-2xl mb-10">
            <span class="eyebrow">Producto</span>
            <h2 class="font-display-x font-black leading-none tracking-[-0.02em] mt-3.5 mb-3 text-text"
                style="font-size: clamp(34px, 4.5vw, 52px)">
                Todo el torneo, bajo control
            </h2>
            <p class="text-[17px] text-muted">
                Dejá las hojas de cálculo y los grupos de chat. FutGO automatiza el
                trabajo aburrido para que te dediques al juego.
            </p>
        </div>

        {{-- Grid 3 cols --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
            @foreach($features as $f)
                <div class="bg-surface border border-border rounded-lg p-6
                            hover:border-green hover:-translate-y-0.5 transition-all duration-[180ms]">
                    {{-- Icon box --}}
                    <div class="w-11 h-11 rounded-md bg-green-tint text-green
                                flex items-center justify-center mb-4 shrink-0">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                             style="width:22px;height:22px">{!! $f['icon'] !!}</svg>
                    </div>
                    <h3 class="font-display-x font-bold text-[21px] mb-2 text-text">{{ $f['title'] }}</h3>
                    <p class="text-[14.5px] text-muted leading-relaxed">{{ $f['desc'] }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>
