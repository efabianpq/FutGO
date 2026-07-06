@extends('layouts.landing')

@section('title', 'FutGO · Organiza tu torneo. Juega. Conéctate.')

@section('content')

    {{-- Helpers exclusivos de la landing (no tocan estilos base de la app).
         Usan tokens del sistema, así heredan la paleta y el tema activos. --}}
    <style>
        .font-x{ font-family: var(--font-display); font-stretch:112%; }
        .chalk-underline{ position:relative; white-space:nowrap; }
        .chalk-underline::after{
            content:""; position:absolute; left:0; right:0; bottom:.02em; height:.28em;
            background:var(--color-green); opacity:.28; border-radius:2px; z-index:-1;
        }
        .chalk-band{
            height:2px; border:none; margin:0; opacity:.55;
            background:repeating-linear-gradient(90deg, var(--color-border) 0 26px, transparent 26px 44px);
        }
    </style>

    {{-- 1 · HERO --}}
    <x-landing.hero />

    <hr class="chalk-band max-w-[1120px] mx-auto">

    {{-- 2 · ORGANIZADORES --}}
    <x-landing.section id="organiza" eyebrow="Para organizadores">
        <div class="grid items-center gap-[52px] md:grid-cols-2">
            <div>
                <h2 class="font-x font-extrabold text-text m-0 mb-3.5" style="font-stretch:110%; font-size:clamp(30px,3.6vw,44px); line-height:1.04; letter-spacing:-.02em">
                    Crea y gestiona tu torneo <span class="chalk-underline">en minutos</span>
                </h2>
                <p class="text-[17.5px] text-muted m-0 leading-relaxed" style="max-width:52ch">
                    Cualquiera puede armar un torneo completo. No necesitás ser “admin” de nada ni pedir permiso: te registrás y arrancás.
                </p>

                <x-landing.feature-list>
                    <x-landing.feature title="Fixture automático">
                        <x-slot:icon><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-5 h-5"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 10h18M8 4v16"/></svg></x-slot:icon>
                        Cargás los equipos y FutGO genera el calendario, los grupos y las llaves solo.
                    </x-landing.feature>
                    <x-landing.feature title="Resultados y tabla en vivo">
                        <x-slot:icon><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-5 h-5"><path d="M4 20V10M10 20V4M16 20V14M22 20H2"/></svg></x-slot:icon>
                        Cargás el marcador y la tabla de posiciones y las estadísticas se actualizan al instante.
                    </x-landing.feature>
                    <x-landing.feature title="Tarjetas para compartir">
                        <x-slot:icon><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-5 h-5"><path d="M4 12v7a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-7"/><path d="M12 3v13M8 7l4-4 4 4"/></svg></x-slot:icon>
                        Cada resultado genera una imagen lista para mandar al grupo de WhatsApp.
                    </x-landing.feature>
                </x-landing.feature-list>

                <a href="{{ route('register') }}" class="btn btn-primary mt-6">Crear mi torneo gratis</a>
            </div>

            <div>
                <x-mockup.standings />
            </div>
        </div>
    </x-landing.section>

    {{-- 3 · JUGADORES --}}
    <x-landing.section id="juega" variant="tint" eyebrow="Para jugadores y capitanes">
        <div class="grid items-center gap-[52px] md:grid-cols-2">
            <div class="md:order-1 order-2">
                <x-mockup.credential />
            </div>

            <div class="md:order-2 order-1">
                <h2 class="font-x font-extrabold text-text m-0 mb-3.5" style="font-stretch:110%; font-size:clamp(30px,3.6vw,44px); line-height:1.04; letter-spacing:-.02em">
                    Tu carrera deportiva, <span class="chalk-underline">digitalizada</span>
                </h2>
                <p class="text-[17.5px] text-muted m-0 leading-relaxed" style="max-width:52ch">
                    Cada gol, cada partido y cada MVP suma a tu historial. Tu reputación de fair play y confiabilidad te representa dentro y fuera de la cancha.
                </p>

                <x-landing.feature-list>
                    <x-landing.feature title="Credencial con QR">
                        <x-slot:icon><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-5 h-5"><rect x="4" y="4" width="7" height="7" rx="1"/><rect x="13" y="4" width="7" height="7" rx="1"/><rect x="4" y="13" width="7" height="7" rx="1"/><path d="M13 13h3v3M20 20v.01M20 16v.01M16 20v.01"/></svg></x-slot:icon>
                        Una identidad deportiva verificable que mostrás con tu teléfono en cualquier cancha.
                    </x-landing.feature>
                    <x-landing.feature title="Goles, MVP e historial">
                        <x-slot:icon><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-5 h-5"><path d="M12 2l2.4 7.4H22l-6 4.6 2.3 7L12 16.8 5.7 21 8 14 2 9.4h7.6z"/></svg></x-slot:icon>
                        Todos tus partidos y logros en un solo lugar, temporada tras temporada.
                    </x-landing.feature>
                    <x-landing.feature title="Reputación y fair play">
                        <x-slot:icon><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-5 h-5"><path d="M12 3l7 3v6c0 4-3 7-7 9-4-2-7-5-7-9V6z"/><path d="M9 12l2 2 4-4"/></svg></x-slot:icon>
                        Tu comportamiento y confiabilidad te construyen una reputación que otros pueden ver.
                    </x-landing.feature>
                </x-landing.feature-list>

                <a href="{{ route('register') }}" class="btn btn-primary mt-6">Crear mi perfil de jugador</a>
            </div>
        </div>
    </x-landing.section>

    {{-- 4 · COMUNIDAD --}}
    <x-landing.section id="comunidad" eyebrow="La comunidad">
        <div class="max-w-[640px]">
            <h2 class="font-x font-extrabold text-text m-0 mb-3.5" style="font-stretch:110%; font-size:clamp(30px,3.6vw,44px); line-height:1.04; letter-spacing:-.02em">
                Encontrá rivales, armá amistosos y descubrí quién juega <span class="chalk-underline">cerca tuyo</span>
            </h2>
            <p class="text-[17.5px] text-muted m-0 leading-relaxed" style="max-width:52ch">
                No hace falta estar en un torneo para usar FutGO. Es el lugar donde vive el fútbol amateur de tu ciudad — conectate cuando quieras jugar.
            </p>
        </div>

        <div class="grid gap-4 mt-[30px] md:grid-cols-3">
            @foreach([
                ['Canchas cerca', 'Descubrí canchas disponibles en tu zona y dónde se está jugando hoy.', '<path d="M12 21s-7-4.5-7-10a7 7 0 0 1 14 0c0 5.5-7 10-7 10Z"/><circle cx="12" cy="11" r="2.5"/>'],
                ['Rivales y jugadores', 'Buscá equipos para un amistoso o jugadores para completar el tuyo.', '<circle cx="9" cy="8" r="3"/><circle cx="17" cy="9" r="2.4"/><path d="M3 20c0-3 2.7-5 6-5s6 2 6 5M15 20c0-2 1-3.5 3-3.5s3 1.5 3 3.5"/>'],
                ['Amistosos abiertos', 'Publicá un partido y sumá gente, sin necesidad de un torneo formal.', '<circle cx="12" cy="12" r="9"/><path d="M12 3a14 14 0 0 0 0 18M12 3a14 14 0 0 1 0 18M3.5 9h17M3.5 15h17"/>'],
            ] as [$t, $d, $svg])
                <div class="bg-surface border border-border rounded-lg p-[22px]">
                    <div class="w-11 h-11 rounded-md flex items-center justify-center mb-3.5 bg-green-tint text-green">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-[22px] h-[22px]">{!! $svg !!}</svg>
                    </div>
                    <h4 class="font-x font-bold text-[19px] text-text m-0 mb-1.5" style="font-stretch:110%">{{ $t }}</h4>
                    <p class="text-[14px] text-muted m-0 leading-normal">{{ $d }}</p>
                    <span class="inline-block mt-3 font-mono text-[10px] uppercase text-subtle border border-border rounded-pill px-2 py-0.5" style="letter-spacing:.1em">Próximamente</span>
                </div>
            @endforeach
        </div>
    </x-landing.section>

    {{-- 5 · CONFIANZA / SIMPLICIDAD --}}
    <x-landing.section variant="dark" eyebrow="Sin fricción, sin letra chica">
        <div class="max-w-[640px] mb-9">
            <h2 class="font-x font-extrabold m-0 mb-3.5 text-white" style="font-stretch:110%; font-size:clamp(30px,3.6vw,44px); line-height:1.04; letter-spacing:-.02em">
                Empezar es tan fácil como salir a la cancha
            </h2>
            <p class="text-[17.5px] m-0 leading-relaxed text-white/75" style="max-width:52ch">
                Creemos que jugar no debería tener trámites. Por eso FutGO arranca gratis y sin barreras.
            </p>
        </div>

        <div class="grid gap-5 md:grid-cols-3">
            @foreach([
                ['Registro libre e inmediato', 'Te registrás y ya estás adentro. Nada de esperar la aprobación de un administrador.', '<path d="M13 2 3 14h8l-1 8 10-12h-8z"/>'],
                ['Sin cuotas ocultas', 'Empezás gratis. Lo que ves es lo que hay: nada de costos sorpresa para organizar o jugar.', '<circle cx="12" cy="12" r="9"/><path d="M15 9.5C15 8 13.7 7 12 7s-3 1-3 2.5S10.5 12 12 12s3 .8 3 2.5S13.7 17 12 17s-3-1-3-2.5M12 5.5v1.5M12 17v1.5"/>'],
                ['Cualquiera puede organizar', 'No necesitás ser “admin” de nada. Si querés armar un torneo, lo armás vos mismo.', '<path d="M12 3l7 3v6c0 4-3 7-7 9-4-2-7-5-7-9V6z"/>'],
            ] as [$t, $d, $svg])
                <div>
                    <div class="w-[46px] h-[46px] rounded-md flex items-center justify-center bg-green/20 text-green">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-6 h-6">{!! $svg !!}</svg>
                    </div>
                    <div class="font-x font-extrabold text-[22px] text-white mt-3 mb-1.5" style="font-stretch:115%; letter-spacing:-.01em">{{ $t }}</div>
                    <p class="text-[14px] m-0 leading-normal text-white/70">{{ $d }}</p>
                </div>
            @endforeach
        </div>
    </x-landing.section>

    {{-- 6 · CTA FINAL --}}
    <x-landing.cta-final />

@endsection
