@extends('layouts.app')
@section('title', 'Inicio')

@section('content')
@php
    $user = auth()->user();
    $tournamentStatusMeta = [
        'draft'       => ['Borrador',    'upcoming'],
        'open'        => ['Inscripción', 'win'],
        'in_progress' => ['En juego',    'live'],
        'finished'    => ['Finalizado',  'default'],
        'cancelled'   => ['Cancelado',   'default'],
    ];
@endphp

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

    <div class="mb-8">
        <p class="eyebrow">Inicio</p>
        <h1 class="font-display font-bold text-display-m text-pitch uppercase mt-1">
            Hola, {{ explode(' ', $user->name)[0] }}
        </h1>
        <p class="text-ink-soft text-[14px] mt-1">Estos son tus accesos disponibles.</p>
    </div>

    @if (! $pollaAccess && ! $torneosAccess)
        <div class="bg-white border border-line rounded-md shadow-card-2 p-10 text-center">
            <p class="text-ink-soft text-lg">No tenés módulos habilitados.</p>
            <p class="text-[13px] text-ink-mute mt-2">Contactá al organizador para obtener acceso.</p>
        </div>
    @endif

    {{-- ══ Tarjetas de módulos ══════════════════════════════════════════════ --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5 mb-8">

        @if ($pollaAccess)
            <a href="{{ route('predictions.index') }}"
               class="group bg-white border border-pitch rounded-md shadow-card-2 p-6 hover:bg-pitch transition-colors duration-fast">
                <div class="flex items-center justify-between">
                    <p class="font-display font-bold text-pitch uppercase text-display-s group-hover:text-bone">⚽ Polla Mundial</p>
                    <span class="text-pitch group-hover:text-gol text-2xl">→</span>
                </div>
                <p class="text-[13px] text-ink-soft mt-2 group-hover:text-bone/80">
                    Pronósticos, ranking y resultados del Mundial 2026.
                </p>
                <div class="flex gap-4 mt-4 font-mono text-[11px] uppercase tracking-wide-label text-pitch group-hover:text-gol">
                    <span>Pronósticos</span><span>Ranking</span>
                </div>
            </a>
        @endif

        @if ($torneosAccess)
            <a href="{{ route('torneos.index') }}"
               class="group bg-white border border-pitch rounded-md shadow-card-2 p-6 hover:bg-pitch transition-colors duration-fast">
                <div class="flex items-center justify-between">
                    <p class="font-display font-bold text-pitch uppercase text-display-s group-hover:text-bone">🏆 Torneos</p>
                    <span class="text-pitch group-hover:text-gol text-2xl">→</span>
                </div>
                <p class="text-[13px] text-ink-soft mt-2 group-hover:text-bone/80">
                    Gestioná tus equipos, mirá el cronograma y las estadísticas.
                </p>
                <div class="flex gap-4 mt-4 font-mono text-[11px] uppercase tracking-wide-label text-pitch group-hover:text-gol">
                    <span>{{ $myTournaments->count() }} torneo{{ $myTournaments->count() !== 1 ? 's' : '' }}</span>
                </div>
            </a>
        @endif
    </div>

    {{-- ══ Accesos rápidos del módulo Torneos (según rol) ═══════════════════ --}}
    @if ($torneosAccess)
        <div class="flex flex-wrap gap-2 mb-6">
            <x-btn :href="route('torneos.index')" variant="primary" size="sm">Mis Torneos</x-btn>
            <x-btn :href="route('torneos.mis-equipos')" variant="ghost" size="sm">Mis Equipos</x-btn>
            <x-btn :href="route('torneos.public.index')" variant="ghost" size="sm">Torneos</x-btn>
            @if ($user->isTorneoPlayerAnywhere())
                <x-btn :href="route('torneos.mi-carrera')" variant="ghost" size="sm">Mi Carrera</x-btn>
            @endif
            @if ($user->isTorneoAdmin())
                <x-btn :href="route('torneos.index')" variant="ghost" size="sm">Gestión Torneos</x-btn>
            @endif
        </div>
    @endif

    {{-- ══ Bloques de Torneos ═══════════════════════════════════════════════ --}}
    @if ($torneosAccess)
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

            {{-- Torneos activos --}}
            <section class="bg-white border border-line rounded-md shadow-card-2 p-5">
                <p class="font-display font-bold text-pitch uppercase text-[15px] mb-4">Tus torneos</p>

                @if ($myTournaments->isEmpty())
                    <p class="text-[13px] text-ink-mute italic">
                        Todavía no participás en ningún torneo.
                        <a href="{{ route('torneos.index') }}" class="text-pitch hover:underline">Explorar torneos →</a>
                    </p>
                @else
                    <ul class="divide-y divide-line-soft">
                        @foreach ($myTournaments as $t)
                            @php [$tl, $tv] = $tournamentStatusMeta[$t->status] ?? [$t->status, 'default']; @endphp
                            <li class="py-3">
                                <a href="{{ route('torneos.hub', $t) }}"
                                   class="flex items-center justify-between gap-3 group">
                                    <div class="min-w-0">
                                        <p class="font-display font-semibold text-pitch text-[14px] truncate group-hover:underline">{{ $t->name }}</p>
                                        <p class="font-mono text-[11px] text-ink-mute">{{ $t->teams_count }} equipos · {{ ucfirst($t->sport) }}</p>
                                    </div>
                                    <x-badge :variant="$tv">{{ $tl }}</x-badge>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

            {{-- Próximos partidos --}}
            <section class="bg-white border border-line rounded-md shadow-card-2 p-5">
                <p class="font-display font-bold text-pitch uppercase text-[15px] mb-4">Próximos partidos</p>
                <x-torneos.upcoming-matches :matches="$upcomingMatches" :limit="5" :show-phase="true" />
            </section>
        </div>
    @endif
</div>
@endsection
