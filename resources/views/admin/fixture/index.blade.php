@extends('layouts.app')
@section('title', 'Admin · Fixture')

@section('content')
@include('admin._nav')

<div class="max-w-7xl mx-auto px-4 py-6">
    <h1 class="text-2xl font-bold text-pachon-green mb-4">📅 Fixture</h1>
    <p class="text-sm text-gray-600 mb-4">Editá equipos, fecha y estadio de cualquier partido. Útil sobre todo para llenar los placeholders de eliminatoria.</p>

    @foreach ($phases as $phase)
        <section class="mb-6">
            <h2 class="text-lg font-bold text-pachon-green-dark mb-2">{{ $phase['label'] }}</h2>
            <div class="bg-white rounded-lg shadow overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-100 text-left text-xs uppercase text-gray-600">
                        <tr>
                            <th class="px-3 py-2">#</th>
                            <th class="px-3 py-2">Partido</th>
                            <th class="px-3 py-2">Fecha</th>
                            <th class="px-3 py-2">Estadio</th>
                            <th class="px-3 py-2">Estado</th>
                            <th class="px-3 py-2 text-right">Acción</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @foreach ($phase['games'] as $g)
                            <tr>
                                <td class="px-3 py-2 text-xs font-mono text-gray-500">{{ $g->match_number }}</td>
                                <td class="px-3 py-2">{{ $g->home_flag }} {{ $g->home_team }} <span class="text-gray-400">vs</span> {{ $g->away_flag }} {{ $g->away_team }}</td>
                                <td class="px-3 py-2 text-xs">{{ $g->match_datetime->locale('es')->isoFormat('ddd D MMM HH:mm') }}</td>
                                <td class="px-3 py-2 text-xs">{{ $g->venue }}</td>
                                <td class="px-3 py-2">
                                    @if ($g->status === 'finished') <span class="text-xs text-gray-600">⚫ Finalizado</span>
                                    @elseif ($g->status === 'live') <span class="text-xs text-red-600">🔴 En curso</span>
                                    @else <span class="text-xs text-green-600">🟢 Por jugar</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-right">
                                    <a href="{{ route('admin.fixture.edit', $g->id) }}" class="text-xs text-pachon-green hover:underline">Editar</a>
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
