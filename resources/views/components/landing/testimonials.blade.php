{{-- 3 testimonios --}}
@php
$testimonials = [
    [
        'quote'  => '"Pasé de tres grupos de WhatsApp y un Excel a tener todo en FutGO. La tabla se actualiza sola y nadie discute los puntos."',
        'name'   => 'Javier Ramos',
        'role'   => 'Liga Pachón · CDMX',
        'initials' => 'JR',
        'green'  => true,
    ],
    [
        'quote'  => '"Los jugadores aman ver su rating y goleo. Subió muchísimo el compromiso: ahora todos quieren aparecer en las estadísticas."',
        'name'   => 'Mariana Cano',
        'role'   => 'UrbanFutbol · GDL',
        'initials' => 'MC',
        'green'  => false,
    ],
    [
        'quote'  => '"Conecté mis cuatro canchas y la ocupación subió 30%. El calendario inteligente eliminó los choques de horario."',
        'name'   => 'Luis Fierro',
        'role'   => 'CanchaPro · MTY',
        'initials' => 'LF',
        'green'  => false,
    ],
];
@endphp

<section class="py-20 border-b border-border" id="testimonios">
    <div class="max-w-[1200px] mx-auto px-6">

        {{-- Section head --}}
        <div class="max-w-2xl mb-10">
            <span class="eyebrow">Testimonios</span>
            <h2 class="font-display-x font-black leading-none tracking-[-0.02em] mt-3.5 text-text"
                style="font-size: clamp(34px, 4.5vw, 52px)">
                Organizadores que ya<br>no vuelven atrás
            </h2>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
            @foreach($testimonials as $t)
                <div class="bg-surface border border-border rounded-lg p-6 flex flex-col">
                    <div class="text-green text-[14px] tracking-[2px] mb-3.5">★★★★★</div>
                    <p class="text-[16px] leading-relaxed flex-1 mb-5 text-text">{{ $t['quote'] }}</p>
                    <div class="flex items-center gap-3 mt-auto">
                        <span class="avatar {{ $t['green'] ? 'green' : '' }}">{{ $t['initials'] }}</span>
                        <div>
                            <div class="font-semibold text-[14px] text-text">{{ $t['name'] }}</div>
                            <div class="text-[12px] text-subtle">{{ $t['role'] }}</div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

    </div>
</section>
