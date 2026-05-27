@extends('layouts.app')
@section('title', 'Admin · Fixture')

@section('content')
@include('admin._nav')

<div class="max-w-7xl mx-auto px-4 py-8">
    <p class="eyebrow">Calendario</p>
    <h1 class="font-display font-bold text-display-m text-pitch uppercase mt-2 mb-3">Fixture</h1>
    <p class="font-body text-body-s text-ink-soft mb-6 max-w-prose">Editá equipos, fecha y estadio de cualquier partido. Útil sobre todo para llenar los placeholders de eliminatoria.</p>

    @foreach ($phases as $phase)
        <section class="mb-8">
            <header class="flex items-end justify-between mb-3 pb-2 border-b-2 border-pitch">
                <h2 class="font-display font-bold text-display-s text-pitch uppercase">{{ $phase['label'] }}</h2>
                <span class="font-mono text-[11px] tracking-wide-label uppercase text-ink-mute">{{ $phase['games']->count() }} partidos</span>
            </header>

            <div class="bg-white border border-line rounded-md shadow-card overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-pitch text-bone font-mono text-[10.5px] tracking-wide-label uppercase text-left">
                        <tr>
                            <th class="px-3 py-2.5">#</th>
                            <th class="px-3 py-2.5">Partido</th>
                            <th class="px-3 py-2.5">Fecha</th>
                            <th class="px-3 py-2.5">Estadio</th>
                            <th class="px-3 py-2.5">Estado</th>
                            <th class="px-3 py-2.5 text-right">Acción</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line-soft">
                        @foreach ($phase['games'] as $g)
                            <tr class="hover:bg-bone-soft transition-colors duration-fast">
                                <td class="px-3 py-2.5 font-mono text-[11px] text-ink-mute">{{ $g->match_number }}</td>
                                <td class="px-3 py-2.5 font-display font-semibold">{{ $g->home_flag }} {{ $g->home_team }} <span class="text-ink-mute font-body normal-case">vs</span> {{ $g->away_flag }} {{ $g->away_team }}</td>
                                <td class="px-3 py-2.5 font-mono text-[11px] text-ink-soft">{{ $g->match_datetime->locale('es')->isoFormat('ddd D MMM HH:mm') }}</td>
                                <td class="px-3 py-2.5 font-mono text-[11px] text-ink-soft">{{ $g->venue }}</td>
                                <td class="px-3 py-2.5">
                                    @if ($g->status === 'finished') <x-badge variant="upcoming">Finalizado</x-badge>
                                    @elseif ($g->status === 'live') <x-badge variant="live">Live</x-badge>
                                    @else <x-badge variant="default">Por jugar</x-badge>
                                    @endif
                                </td>
                                <td class="px-3 py-2.5 text-right">
                                    <a href="{{ route('admin.fixture.edit', $g->id) }}" class="font-mono text-[11px] tracking-wide-label uppercase text-pitch hover:underline">Editar</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endforeach
</div>
@endsection
