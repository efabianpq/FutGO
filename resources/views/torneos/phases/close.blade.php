@extends('layouts.app')
@section('title', 'Admin · Cerrar fase · ' . $tournament->name)

@section('content')
@include('admin.torneos._nav')

<div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

    {{-- Breadcrumb --}}
    <nav class="flex items-center gap-2 font-mono text-[12px] text-ink-mute mb-5">
        <a href="{{ route('admin.torneos.show', $tournament) }}" class="hover:text-pitch">{{ $tournament->name }}</a>
        <span>›</span>
        <span class="text-pitch font-semibold">Cerrar fase</span>
    </nav>

    @if (session('error'))
        <div class="mb-4 bg-alerta/15 border border-alerta text-alerta-deep px-4 py-3 rounded-md font-display font-semibold">
            {{ session('error') }}
        </div>
    @endif
    @if (session('status'))
        <div class="mb-4 bg-gol/20 border border-gol text-pitch-deep px-4 py-3 rounded-md font-display font-semibold">
            {{ session('status') }}
        </div>
    @endif

    <div class="flex flex-wrap items-start justify-between gap-4 mb-6">
        <div>
            <p class="eyebrow">{{ $tournament->name }}</p>
            <h1 class="font-display font-bold text-display-m text-pitch uppercase mt-1">Cerrar {{ $phase->name }}</h1>
            <p class="font-mono text-[12px] text-ink-mute mt-1">
                Cerrar la fase de grupos genera la eliminatoria con los clasificados.
            </p>
        </div>
        <x-btn :href="route('admin.torneos.show', $tournament)" variant="link" size="sm">← Dashboard</x-btn>
    </div>

    {{-- Estado de la fase --}}
    @if ($phase->isCompleted())
        <div class="mb-6 bg-bone-soft border border-line rounded-md p-5 flex items-center gap-3">
            <x-badge variant="default">Fase cerrada</x-badge>
            <p class="text-[13px] text-ink-soft">
                Esta fase ya fue cerrada. Sus resultados quedaron congelados y la eliminatoria ya fue generada.
            </p>
        </div>
    @endif

    {{-- Resumen de partidos --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-8">
        <div class="bg-white border border-line rounded-md shadow-card-2 p-5">
            <p class="font-mono text-[11px] tracking-wide-label uppercase text-ink-mute">Partidos de la fase</p>
            <p class="font-display font-extrabold text-4xl text-pitch mt-1">{{ $total }}</p>
        </div>
        <div class="bg-white border border-line rounded-md shadow-card-2 p-5">
            <p class="font-mono text-[11px] tracking-wide-label uppercase text-ink-mute">Finalizados</p>
            <p class="font-display font-extrabold text-4xl text-gol-deep mt-1">{{ $finished }}</p>
        </div>
        <div class="bg-white border {{ $pending > 0 ? 'border-alerta/50' : 'border-line' }} rounded-md shadow-card-2 p-5">
            <p class="font-mono text-[11px] tracking-wide-label uppercase text-ink-mute">Pendientes</p>
            <p class="font-display font-extrabold text-4xl {{ $pending > 0 ? 'text-alerta' : 'text-pitch' }} mt-1">{{ $pending }}</p>
        </div>
    </div>

    @if ($pending > 0)
        <div class="mb-8 bg-alerta/10 border border-alerta/40 rounded-md p-5">
            <p class="font-display font-bold text-alerta-deep uppercase text-[14px] mb-1">No se puede cerrar todavía</p>
            <p class="text-[13px] text-ink-soft">
                Quedan <strong>{{ $pending }}</strong> partido(s) sin finalizar. El cierre debe ser total:
                cargá todos los resultados antes de cerrar la fase.
            </p>
            <x-btn :href="route('admin.torneos.partidos.index', $tournament)" variant="ghost" size="sm" class="mt-3">
                Ir a resultados
            </x-btn>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Clasificados proyectados --}}
        <div class="bg-white border border-line rounded-md shadow-card-2 p-5">
            <p class="font-display font-bold text-pitch uppercase text-[15px] mb-4">Clasificados proyectados</p>
            <p class="font-mono text-[11px] text-ink-mute mb-4">
                Primeros {{ $tournament->classifies_per_group }} de cada grupo, según la tabla de posiciones.
            </p>

            <div class="space-y-4">
                @foreach ($qualifiers as $row)
                    <div>
                        <p class="font-mono text-[12px] uppercase tracking-wide-label text-ink-mute mb-2">
                            Grupo {{ $row['group']->name }}
                        </p>
                        @if ($row['qualifiers']->isEmpty())
                            <p class="text-[13px] text-ink-mute italic">Sin posiciones calculadas.</p>
                        @else
                            <ol class="space-y-1">
                                @foreach ($row['qualifiers'] as $standing)
                                    <li class="flex items-center gap-2 bg-gol/5 border-l-2 border-gol rounded-r-md px-3 py-2">
                                        <span class="font-mono text-[12px] text-gol-deep font-bold">{{ $standing->position }}º</span>
                                        @if ($standing->team?->color)
                                            <span class="w-3 h-3 rounded-full border border-line/50 shrink-0"
                                                  style="background:{{ $standing->team->color }}"></span>
                                        @endif
                                        <span class="font-display font-semibold text-pitch text-[14px]">
                                            {{ $standing->team?->name ?? '—' }}
                                        </span>
                                        <span class="ml-auto font-mono text-[12px] text-ink-mute">{{ $standing->points }} pts</span>
                                    </li>
                                @endforeach
                            </ol>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Fase siguiente + acción --}}
        <div class="space-y-6">
            <div class="bg-white border border-line rounded-md shadow-card-2 p-5">
                <p class="font-display font-bold text-pitch uppercase text-[15px] mb-3">Fase siguiente</p>
                @if ($nextPhase)
                    <p class="text-[14px] text-ink-soft">
                        Los clasificados se cruzarán en
                        <strong class="text-pitch">{{ $nextPhase->name }}</strong>
                        ({{ $nextPhase->matches()->count() }} partido(s)).
                    </p>
                    <p class="font-mono text-[11px] text-ink-mute mt-2">
                        Cruce estándar: 1º de un grupo contra 2º del grupo vecino.
                    </p>
                    @if ($tournament->third_place_match)
                        <p class="font-mono text-[11px] text-ink-mute mt-1">
                            El tercer puesto se definirá entre los perdedores de la semifinal.
                        </p>
                    @endif
                @else
                    <p class="text-[13px] text-alerta-deep">
                        No existe una fase de eliminatoria posterior. No se puede cerrar esta fase.
                    </p>
                @endif
            </div>

            {{-- Acción de cierre con confirmación --}}
            <div class="bg-white border border-pitch rounded-md shadow-card-2 p-5" x-data="{ confirming: false }">
                <p class="font-display font-bold text-pitch uppercase text-[15px] mb-2">Cerrar fase</p>
                <p class="text-[12px] text-ink-mute mb-4">
                    Al cerrar, los resultados de esta fase quedan congelados y se genera la eliminatoria.
                    Esta acción no se puede revertir.
                </p>

                @if ($canClose)
                    <template x-if="!confirming">
                        <button type="button" @click="confirming = true"
                                class="w-full inline-flex items-center justify-center gap-2 rounded-md font-display font-bold uppercase tracking-wide-cta px-[22px] py-3 text-[15px] bg-pitch text-bone hover:bg-pitch-deep transition-all duration-fast">
                            Cerrar fase y generar eliminatoria
                        </button>
                    </template>
                    <template x-if="confirming">
                        <div class="space-y-3">
                            <p class="text-[13px] text-ink">¿Confirmás el cierre de <strong>{{ $phase->name }}</strong>?</p>
                            <form method="POST" action="{{ route('admin.torneos.phases.close.execute', [$tournament, $phase]) }}" class="flex gap-2">
                                @csrf
                                <x-btn type="submit" variant="primary" size="sm">Sí, cerrar fase</x-btn>
                                <button type="button" @click="confirming = false"
                                        class="px-3.5 py-2 text-[13px] font-display font-bold uppercase tracking-wide-cta text-pitch border border-pitch rounded-md hover:bg-pitch hover:text-bone transition-all duration-fast">
                                    Cancelar
                                </button>
                            </form>
                        </div>
                    </template>
                @else
                    <p class="text-[12px] text-ink-mute font-mono uppercase tracking-wide-label">
                        @if ($phase->isCompleted())
                            La fase ya está cerrada.
                        @elseif ($pending > 0)
                            Faltan partidos por finalizar.
                        @elseif (! $nextPhase)
                            No hay fase de eliminatoria siguiente.
                        @else
                            Cierre no disponible.
                        @endif
                    </p>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
