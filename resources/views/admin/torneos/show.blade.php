@extends('layouts.app')
@section('title', 'Admin · ' . $tournament->name)

@section('content')
@include('admin.torneos._nav')

@php
    $statusMeta = [
        'draft'       => ['Borrador',    'upcoming'],
        'open'        => ['Inscripción', 'win'],
        'in_progress' => ['En juego',    'live'],
        'finished'    => ['Finalizado',  'default'],
        'cancelled'   => ['Cancelado',   'default'],
    ];
    $formatLabels = [
        'groups_and_knockout' => 'Grupos + Eliminación',
        'knockout_only'       => 'Solo eliminación',
        'round_robin'         => 'Todos contra todos',
    ];
    $statusLabels = [
        'draft' => 'Borrador', 'open' => 'Inscripción',
        'in_progress' => 'En juego', 'finished' => 'Finalizado', 'cancelled' => 'Cancelado',
    ];
    [$label, $variant] = $statusMeta[$tournament->status] ?? [$tournament->status, 'default'];
@endphp

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

    @if ($errors->any())
        <div class="mb-4 bg-alerta/15 border border-alerta text-alerta-deep px-4 py-3 rounded-md font-display font-semibold">
            {{ $errors->first() }}
        </div>
    @endif
    @if (session('error'))
        <div class="mb-4 bg-alerta/15 border border-alerta text-alerta-deep px-4 py-3 rounded-md font-display font-semibold">
            {{ session('error') }}
        </div>
    @endif

    {{-- Encabezado --}}
    <div class="flex flex-wrap items-start justify-between gap-4 mb-6">
        <div>
            <div class="flex items-center gap-3">
                <h1 class="font-display font-bold text-display-m text-pitch uppercase">{{ $tournament->name }}</h1>
                <x-badge :variant="$variant">{{ $label }}</x-badge>
            </div>
            <p class="font-mono text-[12px] text-ink-mute mt-1">{{ $tournament->slug }} · {{ $formatLabels[$tournament->format] ?? $tournament->format }}</p>
        </div>
        <div class="flex gap-3">
            <x-btn :href="route('admin.torneos.edit', $tournament)" variant="ghost">Editar</x-btn>
            <x-btn :href="route('admin.torneos.index')" variant="link">← Volver</x-btn>
        </div>
    </div>

    {{-- Estadísticas rápidas --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-8">
        <div class="bg-white border border-line rounded-md shadow-card-2 p-5">
            <p class="font-mono text-[11px] tracking-wide-label uppercase text-ink-mute">Equipos inscritos</p>
            <p class="font-display font-extrabold text-4xl text-pitch mt-1">{{ $stats['teams_count'] }}</p>
        </div>
        <div class="bg-white border border-line rounded-md shadow-card-2 p-5">
            <p class="font-mono text-[11px] tracking-wide-label uppercase text-ink-mute">Partidos programados</p>
            <p class="font-display font-extrabold text-4xl text-pitch mt-1">{{ $stats['matches_scheduled'] }}</p>
        </div>
        <div class="bg-white border border-line rounded-md shadow-card-2 p-5">
            <p class="font-mono text-[11px] tracking-wide-label uppercase text-ink-mute">Partidos jugados</p>
            <p class="font-display font-extrabold text-4xl text-pitch mt-1">{{ $stats['matches_played'] }}</p>
        </div>
    </div>

    {{-- Resumen de fase de grupos: cierre y eliminatoria --}}
    @if ($phaseSummary)
        @php $ps = $phaseSummary; @endphp
        <div class="bg-white border {{ $ps['can_close'] ? 'border-pitch' : 'border-line' }} rounded-md shadow-card-2 p-5 mb-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <div class="flex items-center gap-2">
                        <p class="font-display font-bold text-pitch uppercase text-[15px]">{{ $ps['phase']->name }}</p>
                        @if ($ps['can_close'])
                            <x-badge variant="win">Lista para cerrar</x-badge>
                        @elseif ($ps['pending'] > 0)
                            <x-badge variant="live">En curso</x-badge>
                        @else
                            <x-badge variant="default">Pendiente</x-badge>
                        @endif
                    </div>
                    <div class="flex flex-wrap gap-x-6 gap-y-1 mt-3 font-mono text-[12px] text-ink-soft">
                        <span><strong class="text-pitch">{{ $ps['finished'] }}/{{ $ps['total'] }}</strong> partidos finalizados</span>
                        <span><strong class="{{ $ps['pending'] > 0 ? 'text-alerta' : 'text-pitch' }}">{{ $ps['pending'] }}</strong> pendientes</span>
                        <span><strong class="text-pitch">{{ $ps['qualifiers'] }}</strong> clasificados proyectados</span>
                        @if ($ps['next_phase'])
                            <span>Siguiente: <strong class="text-pitch">{{ $ps['next_phase']->name }}</strong></span>
                        @endif
                    </div>
                </div>

                <div class="self-center">
                    @if ($ps['can_close'])
                        <x-btn :href="route('admin.torneos.phases.close', [$tournament, $ps['phase']])" variant="primary" size="sm">
                            Cerrar fase y generar eliminatoria
                        </x-btn>
                    @elseif (! $ps['next_phase'])
                        <p class="font-mono text-[11px] uppercase tracking-wide-label text-ink-mute">Sin eliminatoria siguiente</p>
                    @else
                        <span title="Faltan partidos por finalizar">
                            <x-btn variant="primary" size="sm" :disabled="true">Cerrar fase</x-btn>
                        </span>
                    @endif
                </div>
            </div>

            @if ($ps['pending'] > 0 && $ps['next_phase'])
                <div class="mt-4 bg-alerta/10 border border-alerta/30 rounded-md px-4 py-2.5">
                    <p class="text-[12px] text-ink-soft">
                        Quedan <strong>{{ $ps['pending'] }}</strong> partido(s) por finalizar antes de poder cerrar la fase.
                    </p>
                </div>
            @endif
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Accesos rápidos --}}
        <div class="lg:col-span-2">
            <p class="font-display font-bold text-pitch uppercase text-[15px] mb-3">Gestión del torneo</p>
            <div class="grid grid-cols-2 gap-3">
                {{-- Equipos: activo --}}
                <a href="{{ route('admin.torneos.equipos.index', $tournament) }}"
                   class="bg-white border border-pitch rounded-md p-4 hover:bg-pitch hover:text-bone transition-colors duration-fast group">
                    <p class="font-display font-bold text-pitch uppercase text-[14px] group-hover:text-bone">Equipos</p>
                    <p class="text-[12px] text-ink-mute mt-1 group-hover:text-bone/70">Inscripción y aprobación de equipos</p>
                    <p class="font-mono text-[10px] uppercase tracking-wide-label text-gol mt-2 group-hover:text-gol">
                        {{ $stats['teams_count'] }} inscripto{{ $stats['teams_count'] !== 1 ? 's' : '' }}
                    </p>
                </a>

                {{-- Fixture --}}
                <div class="bg-white border {{ $hasFixture ? 'border-pitch' : 'border-line' }} rounded-md p-4">
                    <p class="font-display font-bold text-pitch uppercase text-[14px]">Fixture</p>
                    <p class="text-[12px] text-ink-mute mt-1">Fases, grupos y calendario de partidos</p>
                    @if ($hasFixture)
                        <p class="font-mono text-[10px] uppercase tracking-wide-label text-gol mt-2">Generado</p>
                    @elseif ($canGenerate)
                        <form method="POST" action="{{ route('admin.torneos.fixture.generate', $tournament) }}"
                              x-data @submit="if (!confirm('¿Generar el fixture? Esta acción crea todas las fases y partidos.')) $event.preventDefault()"
                              class="mt-3">
                            @csrf
                            <button type="submit"
                                    class="w-full px-3 py-2 text-[12px] font-display font-bold uppercase tracking-wide-cta bg-pitch text-bone rounded-md hover:bg-pitch-deep transition-all duration-fast">
                                Generar fixture
                            </button>
                        </form>
                    @else
                        <p class="font-mono text-[10px] uppercase tracking-wide-label text-ink-mute mt-2">
                            @if (! $tournament->isOpen())
                                Requiere torneo en inscripción
                            @else
                                Faltan equipos aprobados
                            @endif
                        </p>
                    @endif
                </div>

                {{-- Resultados: activo si hay fixture --}}
                @if ($hasFixture)
                    <a href="{{ route('admin.torneos.partidos.index', $tournament) }}"
                       class="bg-white border border-pitch rounded-md p-4 hover:bg-pitch hover:text-bone transition-colors duration-fast group">
                        <p class="font-display font-bold text-pitch uppercase text-[14px] group-hover:text-bone">Resultados</p>
                        <p class="text-[12px] text-ink-mute mt-1 group-hover:text-bone/70">Carga de marcadores y eventos</p>
                        <p class="font-mono text-[10px] uppercase tracking-wide-label text-gol mt-2 group-hover:text-gol">
                            {{ $stats['matches_played'] }} jugado{{ $stats['matches_played'] !== 1 ? 's' : '' }}
                        </p>
                    </a>
                @else
                    <div class="bg-bone-soft border border-line rounded-md p-4 opacity-60">
                        <p class="font-display font-bold text-pitch uppercase text-[14px]">Resultados</p>
                        <p class="text-[12px] text-ink-mute mt-1">Carga de marcadores y eventos</p>
                        <p class="font-mono text-[10px] uppercase tracking-wide-label text-ink-mute mt-2">Requiere fixture</p>
                    </div>
                @endif

                {{-- Standings: activo si hay fixture con grupos --}}
                @if ($hasFixture && in_array($tournament->format, ['groups_and_knockout', 'round_robin']))
                    <a href="{{ route('admin.torneos.standings.index', $tournament) }}"
                       class="bg-white border border-pitch rounded-md p-4 hover:bg-pitch hover:text-bone transition-colors duration-fast group">
                        <p class="font-display font-bold text-pitch uppercase text-[14px] group-hover:text-bone">Posiciones</p>
                        <p class="text-[12px] text-ink-mute mt-1 group-hover:text-bone/70">Tabla de posiciones por grupo</p>
                        <p class="font-mono text-[10px] uppercase tracking-wide-label text-gol mt-2 group-hover:text-gol">Ver tabla</p>
                    </a>
                @else
                    <div class="bg-bone-soft border border-line rounded-md p-4 opacity-60">
                        <p class="font-display font-bold text-pitch uppercase text-[14px]">Posiciones</p>
                        <p class="text-[12px] text-ink-mute mt-1">Tabla de posiciones por grupo</p>
                        <p class="font-mono text-[10px] uppercase tracking-wide-label text-ink-mute mt-2">
                            {{ $hasFixture ? 'Solo torneos con grupos' : 'Requiere fixture' }}
                        </p>
                    </div>
                @endif

                {{-- Estadísticas: placeholder --}}
                <div class="bg-bone-soft border border-line rounded-md p-4 opacity-60">
                    <p class="font-display font-bold text-pitch uppercase text-[14px]">Estadísticas</p>
                    <p class="text-[12px] text-ink-mute mt-1">Goleadores, tarjetas y posiciones</p>
                    <p class="font-mono text-[10px] uppercase tracking-wide-label text-ink-mute mt-2">Próximamente</p>
                </div>
            </div>
        </div>

        {{-- Datos + cambio de estado --}}
        <div class="space-y-6">
            <div class="bg-white border border-line rounded-md shadow-card-2 p-5">
                <p class="font-display font-bold text-pitch uppercase text-[15px] mb-3">Datos generales</p>
                <dl class="space-y-2 text-[13px]">
                    <div class="flex justify-between"><dt class="text-ink-mute">Deporte</dt><dd class="capitalize font-semibold">{{ $tournament->sport }}</dd></div>
                    <div class="flex justify-between"><dt class="text-ink-mute">Grupos</dt><dd class="font-mono">{{ $tournament->groups_count }}</dd></div>
                    <div class="flex justify-between"><dt class="text-ink-mute">Equipos/grupo</dt><dd class="font-mono">{{ $tournament->teams_per_group }}</dd></div>
                    <div class="flex justify-between"><dt class="text-ink-mute">Clasifican</dt><dd class="font-mono">{{ $tournament->classifies_per_group }}</dd></div>
                    <div class="flex justify-between"><dt class="text-ink-mute">3er puesto</dt><dd class="font-semibold">{{ $tournament->third_place_match ? 'Sí' : 'No' }}</dd></div>
                    @if ($tournament->starts_at)
                        <div class="flex justify-between"><dt class="text-ink-mute">Inicio</dt><dd class="font-mono">{{ $tournament->starts_at->format('d/m/Y H:i') }}</dd></div>
                    @endif
                </dl>
            </div>

            {{-- Cambio de estado con confirmación Alpine --}}
            <div class="bg-white border border-line rounded-md shadow-card-2 p-5">
                <p class="font-display font-bold text-pitch uppercase text-[15px] mb-3">Estado</p>
                <p class="text-[13px] text-ink-soft mb-4">
                    Actual: <x-badge :variant="$variant">{{ $label }}</x-badge>
                </p>

                @if ($nextStatus)
                    <div x-data="{ confirming: false }">
                        <template x-if="!confirming">
                            <button type="button" @click="confirming = true"
                                    class="w-full inline-flex items-center justify-center gap-2 rounded-md font-display font-bold uppercase tracking-wide-cta px-[22px] py-3 text-[15px] bg-pitch text-bone hover:bg-pitch-deep transition-all duration-fast">
                                Avanzar a "{{ $statusLabels[$nextStatus] ?? $nextStatus }}"
                            </button>
                        </template>
                        <template x-if="confirming">
                            <div class="space-y-3">
                                <p class="text-[13px] text-ink">¿Confirmás el cambio a <strong>{{ $statusLabels[$nextStatus] ?? $nextStatus }}</strong>? Esta acción no se puede revertir.</p>
                                <form method="POST" action="{{ route('admin.torneos.status', $tournament) }}" class="flex gap-2">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="status" value="{{ $nextStatus }}">
                                    <x-btn type="submit" variant="primary" size="sm">Confirmar</x-btn>
                                    <button type="button" @click="confirming = false"
                                            class="px-3.5 py-2 text-[13px] font-display font-bold uppercase tracking-wide-cta text-pitch border border-pitch rounded-md hover:bg-pitch hover:text-bone transition-all duration-fast">
                                        Cancelar
                                    </button>
                                </form>
                            </div>
                        </template>
                    </div>
                @else
                    <p class="text-[12px] text-ink-mute font-mono uppercase tracking-wide-label">El torneo está finalizado.</p>
                @endif
            </div>

            {{-- Eliminar (solo borrador) --}}
            @if ($tournament->isDraft())
                <div x-data="{ confirming: false }" class="bg-white border border-alerta/40 rounded-md shadow-card-2 p-5">
                    <p class="font-display font-bold text-alerta uppercase text-[15px] mb-2">Zona peligrosa</p>
                    <p class="text-[12px] text-ink-mute mb-3">Solo se puede eliminar mientras está en borrador.</p>
                    <template x-if="!confirming">
                        <button type="button" @click="confirming = true"
                                class="w-full px-[22px] py-2.5 text-[14px] font-display font-bold uppercase tracking-wide-cta bg-alerta text-white rounded-md hover:bg-alerta-deep transition-all duration-fast">
                            Eliminar torneo
                        </button>
                    </template>
                    <template x-if="confirming">
                        <form method="POST" action="{{ route('admin.torneos.destroy', $tournament) }}" class="flex gap-2">
                            @csrf
                            @method('DELETE')
                            <button type="submit"
                                    class="px-3.5 py-2 text-[13px] font-display font-bold uppercase tracking-wide-cta bg-alerta text-white rounded-md hover:bg-alerta-deep transition-all duration-fast">
                                Sí, eliminar
                            </button>
                            <button type="button" @click="confirming = false"
                                    class="px-3.5 py-2 text-[13px] font-display font-bold uppercase tracking-wide-cta text-pitch border border-pitch rounded-md hover:bg-pitch hover:text-bone transition-all duration-fast">
                                Cancelar
                            </button>
                        </form>
                    </template>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
