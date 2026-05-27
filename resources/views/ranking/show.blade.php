@extends('layouts.app')

@section('title', 'Auditoría — ' . $participant->name)

@php
    if (! function_exists('points_class')) {
        function points_class($p) {
            return match ((int) $p) {
                5 => 'bg-amber-100 text-amber-900 border-amber-400',
                3 => 'bg-emerald-100 text-emerald-900 border-emerald-400',
                2 => 'bg-blue-100 text-blue-900 border-blue-400',
                1 => 'bg-yellow-100 text-yellow-900 border-yellow-400',
                default => 'bg-gray-100 text-gray-700 border-gray-300',
            };
        }
    }
@endphp

@section('content')
<div class="max-w-5xl mx-auto px-4 py-8">
    <a href="{{ route('ranking.index') }}" class="text-sm text-pachon-green hover:underline">← Volver al ranking</a>

    <div class="bg-white rounded-lg shadow p-6 mt-3 mb-6 flex flex-wrap items-center gap-6">
        <div>
            <p class="text-xs uppercase text-gray-500">Participante</p>
            <h1 class="text-2xl font-bold">{{ $participant->name }}</h1>
        </div>
        @if ($ranking)
            <div>
                <p class="text-xs uppercase text-gray-500">Posición</p>
                <p class="text-xl font-bold">
                    @if ($ranking->current_position === 1) 🥇 1
                    @elseif ($ranking->current_position === 2) 🥈 2
                    @elseif ($ranking->current_position === 3) 🥉 3
                    @else {{ $ranking->current_position ?? '—' }}
                    @endif
                </p>
            </div>
            <div>
                <p class="text-xs uppercase text-gray-500">Puntos Totales</p>
                <p class="text-xl font-bold font-mono">{{ $ranking->total_points }}</p>
            </div>
            <div>
                <p class="text-xs uppercase text-gray-500">🎯 Exactos</p>
                <p class="text-xl font-bold text-amber-700">{{ $ranking->exact_predictions }}</p>
            </div>
        @endif
    </div>

    @if ($totalFinished === 0)
        <div class="bg-white rounded-lg shadow p-10 text-center text-gray-500">
            <div class="text-5xl mb-3">⏳</div>
            <p class="text-lg font-semibold">Aún no hay partidos finalizados para este participante</p>
            <p class="text-sm mt-1">La auditoría aparecerá acá apenas el administrador cargue los primeros resultados oficiales.</p>
        </div>
    @else
        @foreach ($phases as $phase)
            <section class="mb-6">
                <h2 class="text-lg font-bold text-pachon-green-dark mb-2">{{ $phase['label'] }}</h2>
                <div class="bg-white rounded-lg shadow overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-100 text-left text-xs uppercase text-gray-600">
                            <tr>
                                <th class="px-3 py-2">#</th>
                                <th class="px-3 py-2">Partido</th>
                                <th class="px-3 py-2 text-right">Pronóstico</th>
                                <th class="px-3 py-2 text-right">Resultado</th>
                                <th class="px-3 py-2 text-right">Puntos</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            @foreach ($phase['rows'] as $row)
                                <tr>
                                    <td class="px-3 py-2 text-xs text-gray-500 font-mono">#{{ $row['match_number'] }}</td>
                                    <td class="px-3 py-2">
                                        <div class="font-medium">
                                            {{ $row['home_flag'] }} {{ $row['home_team'] }}
                                            <span class="text-gray-400">vs</span>
                                            {{ $row['away_flag'] }} {{ $row['away_team'] }}
                                        </div>
                                        <div class="text-xs text-gray-500">
                                            @if ($row['group_name']) Grupo {{ $row['group_name'] }} · @endif
                                            {{ $row['date_label'] }} GMT-5
                                        </div>
                                    </td>
                                    <td class="px-3 py-2 text-right font-mono">
                                        @if ($row['prediction'])
                                            <span class="font-bold">{{ $row['prediction'] }}</span>
                                        @else
                                            <span class="text-gray-400 italic text-xs">Sin pronóstico</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2 text-right font-mono font-bold">{{ $row['official'] }}</td>
                                    <td class="px-3 py-2 text-right">
                                        <span class="{{ points_class($row['points_earned']) }} px-2 py-0.5 rounded font-bold border text-sm">
                                            {{ $row['points_earned'] }} pts
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endforeach
    @endif
</div>
@endsection
