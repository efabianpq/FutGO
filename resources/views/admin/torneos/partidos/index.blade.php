@extends('layouts.app')
@section('title', 'Admin · Partidos · ' . $tournament->name)

@section('content')
@include('admin.torneos._nav')

@php
$statusMeta = [
    'scheduled' => ['Programado', 'upcoming'],
    'live'      => ['En vivo',   'live'],
    'finished'  => ['Finalizado','win'],
    'postponed' => ['Postpuesto','default'],
];
// Todos los grupos del torneo (para el filtro por grupo).
$allGroups = $phases->flatMap(fn ($p) => $p->groups)->unique('id')->values();
@endphp

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8"
     x-data="{ filterPhase: 'all', filterGroup: 'all' }">

    {{-- Breadcrumb --}}
    <nav class="flex items-center gap-2 font-mono text-[12px] text-ink-mute mb-5">
        <a href="{{ route('admin.torneos.show', $tournament) }}" class="hover:text-pitch">{{ $tournament->name }}</a>
        <span>›</span>
        <span class="text-pitch font-semibold">Partidos</span>
    </nav>

    <div class="flex items-center justify-between mb-6">
        <div>
            <p class="eyebrow">{{ $tournament->name }}</p>
            <h1 class="font-display font-bold text-display-m text-pitch uppercase mt-1">Fixture y Resultados</h1>
        </div>
        <a href="{{ route('admin.torneos.show', $tournament) }}"
           class="text-pitch font-display font-semibold text-[13px] uppercase hover:underline">← Dashboard</a>
    </div>

    @if (session('status'))
        <div class="mb-4 bg-gol/20 border border-gol text-pitch-deep px-4 py-3 rounded-md font-display font-semibold">
            {{ session('status') }}
        </div>
    @endif
    @if (session('error'))
        <div class="mb-4 bg-alerta/15 border border-alerta text-alerta-deep px-4 py-3 rounded-md font-display font-semibold">
            {{ session('error') }}
        </div>
    @endif

    @if ($phases->isEmpty())
        <div class="bg-white border border-line rounded-md shadow-card-2 p-10 text-center">
            <p class="text-ink-soft text-lg">El fixture aún no fue generado para este torneo.</p>
        </div>
    @else

        {{-- Filtros --}}
        <div class="flex flex-wrap gap-3 mb-6">
            <div>
                <label class="font-mono text-[11px] uppercase tracking-wide-label text-ink-mute mr-2">Fase</label>
                <select x-model="filterPhase"
                        class="border border-line rounded-md px-3 py-1.5 text-[13px] font-mono focus:outline-none focus:border-pitch">
                    <option value="all">Todas</option>
                    @foreach ($phases as $phase)
                        <option value="{{ $phase->id }}">{{ $phase->name }}</option>
                    @endforeach
                </select>
            </div>

            @if ($allGroups->isNotEmpty())
                <div>
                    <label class="font-mono text-[11px] uppercase tracking-wide-label text-ink-mute mr-2">Grupo</label>
                    <select x-model="filterGroup"
                            class="border border-line rounded-md px-3 py-1.5 text-[13px] font-mono focus:outline-none focus:border-pitch">
                        <option value="all">Todos</option>
                        @foreach ($allGroups as $group)
                            <option value="{{ $group->id }}">Grupo {{ $group->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
        </div>

        @foreach ($phases as $phase)
            <div x-show="filterPhase === 'all' || filterPhase === '{{ $phase->id }}'">
                <p class="font-display font-bold text-pitch uppercase text-[15px] mt-6 mb-3 flex items-center gap-2">
                    {{ $phase->name }}
                    @if ($phase->is_active)
                        <x-badge variant="live">Activa</x-badge>
                    @endif
                </p>

                @if ($phase->matches->isEmpty())
                    <p class="text-ink-mute text-[13px] mb-4">Sin partidos en esta fase.</p>
                @else
                    <div class="bg-white border border-line rounded-md shadow-card-2 overflow-hidden mb-6">
                        <table class="w-full text-left">
                            <thead class="bg-pitch-mist border-b border-line">
                                <tr class="font-mono text-[11px] tracking-wide-label uppercase text-pitch">
                                    <th class="px-4 py-3 w-10">#</th>
                                    <th class="px-4 py-3">Partido</th>
                                    <th class="px-4 py-3 text-center">Resultado</th>
                                    <th class="px-4 py-3 text-center">Estado</th>
                                    <th class="px-4 py-3 text-right">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-line-soft">
                                @foreach ($phase->matches as $match)
                                    @php [$label, $variant] = $statusMeta[$match->status] ?? [$match->status, 'default']; @endphp
                                    <tr class="hover:bg-bone-soft transition-colors duration-fast"
                                        x-show="filterGroup === 'all' || filterGroup === '{{ $match->group_id }}'">
                                        <td class="px-4 py-3 font-mono text-[12px] text-ink-mute">{{ $match->match_number }}</td>
                                        <td class="px-4 py-3">
                                            <span class="font-display font-semibold text-pitch">
                                                {{ $match->homeTeam?->name ?? '—' }}
                                            </span>
                                            <span class="font-mono text-[12px] text-ink-mute mx-2">vs</span>
                                            <span class="font-display font-semibold text-pitch">
                                                {{ $match->awayTeam?->name ?? '—' }}
                                            </span>
                                            @if ($match->homeTeam?->color)
                                                <span class="inline-block w-2.5 h-2.5 rounded-full ml-1 border border-line"
                                                      style="background:{{ $match->homeTeam->color }}"></span>
                                            @endif
                                            @if ($match->scheduled_at)
                                                <span class="font-mono text-[11px] text-ink-mute block mt-0.5">
                                                    {{ $match->scheduled_at->format('d/m/Y H:i') }}
                                                </span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-center font-display font-bold text-[18px] text-pitch">
                                            @if ($match->hasResult())
                                                {{ $match->home_score }} – {{ $match->away_score }}
                                            @else
                                                <span class="text-ink-mute text-[13px] font-normal">—</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            <x-badge :variant="$variant">{{ $label }}</x-badge>
                                        </td>
                                        <td class="px-4 py-3 text-right whitespace-nowrap space-x-2">
                                            {{-- La planilla es el documento maestro del partido. --}}
                                            @if ($match->homeTeam && $match->awayTeam)
                                                <a href="{{ route('admin.torneos.partidos.resultado', [$tournament, $match]) }}"
                                                   class="text-[13px] font-display font-semibold uppercase text-pitch hover:underline">
                                                    Planilla
                                                </a>
                                                <a href="{{ route('admin.torneos.partidos.pdf', [$tournament, $match]) }}"
                                                   class="text-[12px] font-mono text-pitch hover:underline">PDF</a>
                                            @endif

                                            @unless ($match->isFinished())
                                                <a href="{{ route('admin.torneos.partidos.programar', [$tournament, $match]) }}"
                                                   class="text-[12px] font-mono text-pitch hover:underline">Programar</a>
                                            @endunless

                                            @if ($match->isScheduled() && $match->homeTeam && $match->awayTeam)
                                                <form method="POST"
                                                      action="{{ route('admin.torneos.partidos.live', [$tournament, $match]) }}"
                                                      class="inline">
                                                    @csrf @method('PATCH')
                                                    <button type="submit"
                                                            class="text-[12px] font-mono text-gol-deep hover:underline">
                                                        En vivo
                                                    </button>
                                                </form>
                                            @endif

                                            @if ($match->isFinished())
                                                <form method="POST"
                                                      action="{{ route('admin.torneos.partidos.destroy', [$tournament, $match]) }}"
                                                      class="inline"
                                                      x-data
                                                      @submit.prevent="if (confirm('¿Anular el resultado del partido #{{ $match->match_number }}?')) $el.submit()">
                                                    @csrf @method('DELETE')
                                                    <button type="submit"
                                                            class="text-[12px] font-mono text-alerta hover:underline">
                                                        Anular
                                                    </button>
                                                </form>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        @endforeach
    @endif
</div>
@endsection
