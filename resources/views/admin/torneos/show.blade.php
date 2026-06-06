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
        'liga'                => 'Liga / Abierto',
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
                <h1 class="font-display font-bold text-display-s sm:text-display-m text-pitch uppercase">{{ $tournament->name }}</h1>
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

    {{-- H6: panel de gestión del formato LIGA --}}
    @if ($league)
        <div class="bg-white border border-pitch rounded-md shadow-card-2 p-5 mb-8" x-data="{ addOpen: false }">
            <div class="flex flex-wrap items-start justify-between gap-3 mb-4">
                <div>
                    <p class="font-display font-bold text-pitch uppercase text-[15px]">Liga · Fixture</p>
                    <p class="text-[12px] text-ink-mute mt-0.5">Armá el calendario: agregá partidos o auto-generá todos contra todos.</p>
                </div>
                @if ($league['activated'])
                    <span class="font-mono text-[12px] text-ink-mute">
                        {{ $league['finishedMatches'] }}/{{ $league['totalMatches'] }} partidos jugados
                    </span>
                @endif
            </div>

            @if (! $league['activated'])
                {{-- Paso 1: activar la liga --}}
                <div class="bg-bone-soft border border-line rounded-md p-4">
                    <p class="text-[13px] text-ink-soft mb-3">
                        La liga todavía no está activa. Al activarla se crea la tabla con los equipos aprobados
                        ({{ $league['approvedTeams']->count() }}) y el torneo pasa a "En juego".
                    </p>
                    @if ($league['canActivate'])
                        <form method="POST" action="{{ route('admin.torneos.liga.activate', $tournament) }}">
                            @csrf
                            <x-btn type="submit" variant="primary" size="sm">Activar liga</x-btn>
                        </form>
                    @else
                        <p class="text-[12px] text-alerta">Se requieren al menos 2 equipos aprobados y el torneo en inscripción.</p>
                    @endif
                </div>
            @else
                {{-- Paso 2: cargar partidos --}}
                <div class="flex flex-wrap gap-2 mb-4">
                    <x-btn type="button" variant="primary" size="sm" x-on:click="addOpen = true">+ Agregar partido</x-btn>
                    <form method="POST" action="{{ route('admin.torneos.liga.roundRobin', $tournament) }}"
                          x-data x-on:submit="return confirm('¿Generar todos los partidos que falten (todos contra todos)?')">
                        @csrf
                        <x-btn type="submit" variant="ghost" size="sm">Auto-generar todos contra todos</x-btn>
                    </form>
                </div>

                {{-- Modal: agregar partido --}}
                <div x-show="addOpen" x-cloak x-transition.opacity
                     class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 px-4"
                     @click.self="addOpen = false" @keydown.escape.window="addOpen = false">
                    <div class="bg-white rounded-lg shadow-modal w-full max-w-md overflow-hidden">
                        <div class="bg-pitch px-5 py-4 flex items-center justify-between">
                            <p class="font-display font-extrabold text-bone uppercase text-[15px]">Agregar partido</p>
                            <button type="button" @click="addOpen = false" class="text-bone/80 hover:text-bone text-2xl leading-none">&times;</button>
                        </div>
                        <form method="POST" action="{{ route('admin.torneos.liga.matches.store', $tournament) }}" class="p-5 space-y-4">
                            @csrf
                            <div class="flex flex-col gap-1.5">
                                <label for="home_team_id" class="font-mono text-[11px] tracking-wide-label uppercase text-ink-soft">Equipo local *</label>
                                <select name="home_team_id" id="home_team_id" required class="h-[44px] px-3 bg-white border-[1.5px] border-line rounded-md text-[15px] focus:border-pitch focus:ring-0">
                                    <option value="">— Elegí —</option>
                                    @foreach ($league['approvedTeams'] as $t)
                                        <option value="{{ $t->id }}">{{ $t->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="flex flex-col gap-1.5">
                                <label for="away_team_id" class="font-mono text-[11px] tracking-wide-label uppercase text-ink-soft">Equipo visitante *</label>
                                <select name="away_team_id" id="away_team_id" required class="h-[44px] px-3 bg-white border-[1.5px] border-line rounded-md text-[15px] focus:border-pitch focus:ring-0">
                                    <option value="">— Elegí —</option>
                                    @foreach ($league['approvedTeams'] as $t)
                                        <option value="{{ $t->id }}">{{ $t->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div class="flex flex-col gap-1.5">
                                    <label for="scheduled_at" class="font-mono text-[11px] tracking-wide-label uppercase text-ink-soft">Fecha y hora</label>
                                    <input type="datetime-local" name="scheduled_at" id="scheduled_at" class="h-[44px] px-3 bg-white border-[1.5px] border-line rounded-md text-[14px] focus:border-pitch focus:ring-0">
                                </div>
                                <div class="flex flex-col gap-1.5">
                                    <label for="venue" class="font-mono text-[11px] tracking-wide-label uppercase text-ink-soft">Cancha</label>
                                    <input type="text" name="venue" id="venue" maxlength="100" class="h-[44px] px-3 bg-white border-[1.5px] border-line rounded-md text-[14px] focus:border-pitch focus:ring-0">
                                </div>
                            </div>
                            <div class="flex justify-end gap-2 pt-2">
                                <x-btn type="button" variant="ghost" size="sm" x-on:click="addOpen = false">Cancelar</x-btn>
                                <x-btn type="submit" variant="primary" size="sm">Agregar</x-btn>
                            </div>
                        </form>
                    </div>
                </div>

                {{-- Lista de partidos --}}
                @if ($league['matches']->isEmpty())
                    <p class="text-[13px] text-ink-mute italic">Todavía no hay partidos cargados.</p>
                @else
                    <div class="border border-line rounded-md overflow-hidden">
                        <table class="w-full text-left text-[13px]">
                            <thead class="bg-pitch-mist border-b border-line">
                                <tr class="font-mono text-[10px] tracking-wide-label uppercase text-pitch">
                                    <th class="px-3 py-2">#</th>
                                    <th class="px-3 py-2">Partido</th>
                                    <th class="px-3 py-2">Fecha</th>
                                    <th class="px-3 py-2 text-center">Estado</th>
                                    <th class="px-3 py-2 text-right">Acción</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-line-soft">
                                @foreach ($league['matches'] as $m)
                                    <tr>
                                        <td class="px-3 py-2 font-mono text-ink-mute">{{ $m->match_number }}</td>
                                        <td class="px-3 py-2 font-semibold text-pitch">
                                            {{ $m->homeTeam?->name ?? '—' }}
                                            @if ($m->status === 'finished')
                                                <span class="font-mono">{{ $m->home_score }}–{{ $m->away_score }}</span>
                                            @else
                                                vs
                                            @endif
                                            {{ $m->awayTeam?->name ?? '—' }}
                                        </td>
                                        <td class="px-3 py-2 font-mono text-ink-mute">{{ $m->scheduled_at?->format('d/m H:i') ?? '—' }}</td>
                                        <td class="px-3 py-2 text-center">
                                            @if ($m->status === 'finished')
                                                <span class="font-mono text-[11px] text-gol-deep uppercase">Jugado</span>
                                            @else
                                                <span class="font-mono text-[11px] text-ink-mute uppercase">Programado</span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2 text-right">
                                            @if ($m->status !== 'finished')
                                                <form method="POST" action="{{ route('admin.torneos.liga.matches.destroy', [$tournament, $m]) }}"
                                                      x-data x-on:submit="return confirm('¿Eliminar este partido?')" class="inline">
                                                    @csrf @method('DELETE')
                                                    <button type="submit" class="text-alerta font-display font-semibold text-[12px] uppercase hover:underline">Eliminar</button>
                                                </form>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    {{-- Cargar resultados + generar eliminatoria --}}
                    <div class="flex flex-wrap items-center gap-2 mt-4">
                        <x-btn :href="route('admin.torneos.partidos.index', $tournament)" variant="ghost" size="sm">Cargar resultados</x-btn>

                        @if ($league['classifies'] >= 2)
                            @if ($league['canGenerateKnockout'])
                                <form method="POST" action="{{ route('admin.torneos.liga.knockout', $tournament) }}"
                                      x-data x-on:submit="return confirm('¿Generar la eliminatoria con los {{ $league['classifies'] }} mejores de la tabla?')">
                                    @csrf
                                    <x-btn type="submit" variant="primary" size="sm">Generar eliminatoria (top {{ $league['classifies'] }})</x-btn>
                                </form>
                            @elseif (! $league['hasKnockout'])
                                <span class="font-mono text-[11px] text-ink-mute">
                                    Terminá todos los partidos para generar la eliminatoria (top {{ $league['classifies'] }}).
                                </span>
                            @endif
                        @endif
                    </div>
                @endif
            @endif
        </div>
    @endif

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

                {{-- Fixture / Calendario --}}
                @if ($hasFixture)
                    <a href="{{ route('torneos.cronograma.index', $tournament) }}"
                       class="bg-white border border-pitch rounded-md p-4 hover:bg-pitch hover:text-bone transition-colors duration-fast group">
                        <p class="font-display font-bold text-pitch uppercase text-[14px] group-hover:text-bone">Fixture</p>
                        <p class="text-[12px] text-ink-mute mt-1 group-hover:text-bone/70">Calendario completo por fase y grupo</p>
                        <p class="font-mono text-[10px] uppercase tracking-wide-label text-gol mt-2 group-hover:text-gol">Ver calendario</p>
                    </a>
                @else
                    <div class="bg-white border border-line rounded-md p-4">
                        <p class="font-display font-bold text-pitch uppercase text-[14px]">Fixture</p>
                        <p class="text-[12px] text-ink-mute mt-1">Fases, grupos y calendario de partidos</p>
                        @if ($canGenerate)
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
                @endif

                {{-- Resultados: activo si hay fixture --}}
                @if ($hasFixture)
                    <a href="{{ route('admin.torneos.partidos.index', $tournament) }}"
                       class="bg-white border border-pitch rounded-md p-4 hover:bg-pitch hover:text-bone transition-colors duration-fast group">
                        <p class="font-display font-bold text-pitch uppercase text-[14px] group-hover:text-bone">Partidos</p>
                        <p class="text-[12px] text-ink-mute mt-1 group-hover:text-bone/70">Programación, marcadores y eventos</p>
                        <p class="font-mono text-[10px] uppercase tracking-wide-label text-gol mt-2 group-hover:text-gol">
                            {{ $stats['matches_played'] }} jugado{{ $stats['matches_played'] !== 1 ? 's' : '' }}
                        </p>
                    </a>
                @else
                    <div class="bg-bone-soft border border-line rounded-md p-4 opacity-60">
                        <p class="font-display font-bold text-pitch uppercase text-[14px]">Partidos</p>
                        <p class="text-[12px] text-ink-mute mt-1">Programación, marcadores y eventos</p>
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

                {{-- Estadísticas --}}
                @if ($hasFixture)
                    <a href="{{ route('torneos.estadisticas.index', $tournament) }}"
                       class="bg-white border border-pitch rounded-md p-4 hover:bg-pitch hover:text-bone transition-colors duration-fast group">
                        <p class="font-display font-bold text-pitch uppercase text-[14px] group-hover:text-bone">Estadísticas</p>
                        <p class="text-[12px] text-ink-mute mt-1 group-hover:text-bone/70">Goleadores, asistencias y tarjetas</p>
                        <p class="font-mono text-[10px] uppercase tracking-wide-label text-gol mt-2 group-hover:text-gol">Ver estadísticas</p>
                    </a>
                @else
                    <div class="bg-bone-soft border border-line rounded-md p-4 opacity-60">
                        <p class="font-display font-bold text-pitch uppercase text-[14px]">Estadísticas</p>
                        <p class="text-[12px] text-ink-mute mt-1">Goleadores, tarjetas y posiciones</p>
                        <p class="font-mono text-[10px] uppercase tracking-wide-label text-ink-mute mt-2">Requiere fixture</p>
                    </div>
                @endif
            </div>

            {{-- Validar jugador (H17) --}}
            <div class="mt-4 bg-white border border-line rounded-md shadow-card p-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="font-display font-bold text-pitch uppercase text-[14px]">Validar credencial</p>
                        <p class="text-[12px] text-ink-mute mt-0.5">Verificá si un jugador está habilitado para este torneo.</p>
                    </div>
                    <a href="{{ route('torneos.validar', ['tournament_id' => $tournament->id]) }}"
                       class="btn btn-secondary btn-sm">Validar jugador</a>
                </div>
            </div>

            {{-- Exportar + portal público (Sesión E) --}}
            <div class="mt-4 bg-white border border-line rounded-md shadow-card p-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="font-display font-bold text-pitch uppercase text-[14px]">Exportar y compartir</p>
                        <p class="text-[12px] text-ink-mute mt-0.5">Descargá los datos del torneo o abrí el portal público.</p>
                    </div>
                    <div class="flex items-center gap-3">
                        <a href="{{ route('admin.torneos.sponsors.index', $tournament) }}"
                           class="font-mono text-[11px] uppercase tracking-wide-label text-pitch font-bold">Patrocinadores</a>
                        @if ($tournament->isPublic())
                            <a href="{{ route('torneos.public.show', $tournament) }}" target="_blank"
                               class="font-mono text-[11px] uppercase tracking-wide-label text-gol-deep font-bold">Ver portal público ↗</a>
                        @else
                            <span class="font-mono text-[11px] uppercase tracking-wide-label text-ink-mute">Torneo privado (sin portal)</span>
                        @endif
                    </div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 mt-3">
                    @foreach (['resultados' => 'Resultados', 'posiciones' => 'Posiciones', 'estadisticas' => 'Estadísticas'] as $ds => $lbl)
                        <div class="flex items-center justify-between bg-bone-soft border border-line rounded-md px-3 py-2">
                            <span class="text-[13px] font-semibold text-ink">{{ $lbl }}</span>
                            <span class="flex gap-2">
                                <a href="{{ route('admin.torneos.export', [$tournament, $ds, 'pdf']) }}" class="font-mono text-[11px] text-alerta font-bold">PDF</a>
                                <a href="{{ route('admin.torneos.export', [$tournament, $ds, 'csv']) }}" class="font-mono text-[11px] text-pitch font-bold">CSV</a>
                            </span>
                        </div>
                    @endforeach
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
