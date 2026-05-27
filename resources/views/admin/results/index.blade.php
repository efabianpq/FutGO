@extends('layouts.app')
@section('title', 'Admin · Resultados')

@section('content')
@include('admin._nav')

<div class="max-w-6xl mx-auto px-4 py-6">
    <h1 class="text-2xl font-bold text-pachon-green mb-4">🎯 Ingreso de Resultados</h1>

    <p class="text-sm text-gray-600 mb-4">
        Al guardar un resultado, se calculan automáticamente los puntos de todos los pronósticos del partido
        y se recalcula el ranking. Podés volver a guardar el mismo partido si necesitás corregir el marcador.
    </p>

    @error('result')
        <div class="bg-red-100 border border-red-300 text-red-700 px-4 py-2 rounded mb-4 text-sm">{{ $message }}</div>
    @enderror

    <h2 class="text-lg font-bold text-pachon-green-dark mb-2">⏰ Partidos pendientes de resultado</h2>
    <div class="bg-white rounded-lg shadow overflow-x-auto mb-6">
        <table class="w-full text-sm">
            <thead class="bg-gray-100 text-left text-xs uppercase text-gray-600">
                <tr>
                    <th class="px-3 py-2">#</th>
                    <th class="px-3 py-2">Partido</th>
                    <th class="px-3 py-2">Fecha</th>
                    <th class="px-3 py-2 text-center">Goles</th>
                    <th class="px-3 py-2 text-right">Acción</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse ($pending as $g)
                    <tr>
                        <td class="px-3 py-2 text-xs font-mono text-gray-500">{{ $g->match_number }}</td>
                        <td class="px-3 py-2">{{ $g->home_flag }} {{ $g->home_team }} <span class="text-gray-400">vs</span> {{ $g->away_flag }} {{ $g->away_team }}</td>
                        <td class="px-3 py-2 text-xs">{{ $g->match_datetime->locale('es')->isoFormat('ddd D MMM HH:mm') }}</td>
                        <td colspan="2" class="px-3 py-2">
                            <form method="POST" action="{{ route('admin.results.store', $g->id) }}" class="flex items-center justify-end gap-2">
                                @csrf
                                <input type="number" name="home_score" min="0" max="20" required class="w-16 text-center rounded-md border-gray-300 text-lg font-bold">
                                <span class="text-gray-400">:</span>
                                <input type="number" name="away_score" min="0" max="20" required class="w-16 text-center rounded-md border-gray-300 text-lg font-bold">
                                <button type="submit" class="bg-pachon-green hover:bg-pachon-green-dark text-white text-xs px-3 py-2 rounded font-semibold">
                                    Guardar y calcular
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-3 py-6 text-center text-gray-500 italic">No hay partidos pendientes con hora ya pasada.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <h2 class="text-lg font-bold text-pachon-green-dark mb-2">✅ Últimos partidos finalizados (recalcular)</h2>
    <div class="bg-white rounded-lg shadow overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-100 text-left text-xs uppercase text-gray-600">
                <tr>
                    <th class="px-3 py-2">#</th>
                    <th class="px-3 py-2">Partido</th>
                    <th class="px-3 py-2 text-center">Resultado</th>
                    <th class="px-3 py-2 text-right">Recalcular</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse ($finished as $g)
                    <tr>
                        <td class="px-3 py-2 text-xs font-mono text-gray-500">{{ $g->match_number }}</td>
                        <td class="px-3 py-2">{{ $g->home_team }} <span class="text-gray-400">vs</span> {{ $g->away_team }}</td>
                        <td colspan="2" class="px-3 py-2">
                            <form method="POST" action="{{ route('admin.results.store', $g->id) }}" class="flex items-center justify-end gap-2">
                                @csrf
                                <input type="number" name="home_score" min="0" max="20" required value="{{ $g->home_score_official }}" class="w-16 text-center rounded-md border-gray-300 text-lg font-bold">
                                <span class="text-gray-400">:</span>
                                <input type="number" name="away_score" min="0" max="20" required value="{{ $g->away_score_official }}" class="w-16 text-center rounded-md border-gray-300 text-lg font-bold">
                                <button type="submit" class="bg-amber-500 hover:bg-amber-600 text-white text-xs px-3 py-2 rounded font-semibold">
                                    Recalcular
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-3 py-6 text-center text-gray-500 italic">No hay partidos finalizados todavía.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
