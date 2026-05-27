@extends('layouts.app')
@section('title', 'Admin · Dashboard')

@section('content')
@include('admin._nav')

<div class="max-w-7xl mx-auto px-4 py-6">
    <h1 class="text-2xl font-bold text-pachon-green mb-4">📊 Dashboard Administrador</h1>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-lg shadow p-4 border-l-4 border-pachon-green">
            <p class="text-xs uppercase text-gray-500">Participantes Activos</p>
            <p class="text-3xl font-bold text-pachon-green">{{ $activeParticipants }}</p>
            <p class="text-xs text-gray-500 mt-1">con código activado</p>
        </div>
        <div class="bg-white rounded-lg shadow p-4 border-l-4 border-amber-400">
            <p class="text-xs uppercase text-gray-500">Pendientes de Código</p>
            <p class="text-3xl font-bold text-amber-600">{{ $pendingParticipants }}</p>
            <p class="text-xs text-gray-500 mt-1">registrados sin activar</p>
        </div>
        <div class="bg-white rounded-lg shadow p-4 border-l-4 border-blue-400">
            <p class="text-xs uppercase text-gray-500">Partidos Finalizados</p>
            <p class="text-3xl font-bold text-blue-600">{{ $finishedMatches }} / {{ $totalMatches }}</p>
            <p class="text-xs text-gray-500 mt-1">
                @if ($totalMatches > 0)
                    {{ round($finishedMatches / $totalMatches * 100) }}% completado
                @endif
            </p>
        </div>
        <div class="bg-white rounded-lg shadow p-4 border-l-4 border-pachon-gold">
            <p class="text-xs uppercase text-gray-500">Acumulado Total</p>
            <p class="text-2xl font-bold text-pachon-gold-dark">
                @if ($prizes['pool'] !== null)
                    {{ number_format($prizes['pool'], 0, ',', '.') }} COP
                @else
                    <span class="text-gray-400 text-base italic">Por definir</span>
                @endif
            </p>
            <a href="{{ route('admin.settings.edit') }}" class="text-xs text-pachon-green hover:underline">configurar →</a>
        </div>
    </div>

    @if ($prizes['pool'] !== null)
        <div class="bg-white rounded-lg shadow p-4 mb-6">
            <h2 class="font-bold text-pachon-green-dark mb-3">💰 Desglose de Premios</h2>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-center">
                <div><p class="text-xs text-gray-500">🥇 60%</p><p class="font-bold text-amber-600 text-lg">{{ number_format($prizes['first'], 0, ',', '.') }}</p></div>
                <div><p class="text-xs text-gray-500">🥈 20%</p><p class="font-bold text-gray-600 text-lg">{{ number_format($prizes['second'], 0, ',', '.') }}</p></div>
                <div><p class="text-xs text-gray-500">🥉 10%</p><p class="font-bold text-amber-800 text-lg">{{ number_format($prizes['third'], 0, ',', '.') }}</p></div>
                <div><p class="text-xs text-gray-500">Plataforma 10%</p><p class="font-bold text-gray-500 text-lg">{{ number_format($prizes['platform'], 0, ',', '.') }}</p></div>
            </div>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <div class="bg-white rounded-lg shadow p-4">
            <h2 class="font-bold text-pachon-green-dark mb-3">🏆 Top 3 Actual</h2>
            @if ($top3->isEmpty())
                <p class="text-sm text-gray-500 italic">Aún no hay ranking calculado.</p>
            @else
                <ul class="divide-y">
                    @foreach ($top3 as $r)
                        <li class="py-2 flex items-center justify-between">
                            <span>
                                @if ($r->current_position === 1) 🥇
                                @elseif ($r->current_position === 2) 🥈
                                @elseif ($r->current_position === 3) 🥉
                                @endif
                                <span class="font-semibold ml-1">{{ $r->name }}</span>
                            </span>
                            <span class="font-mono font-bold">{{ $r->total_points }} pts <span class="text-xs text-amber-600">({{ $r->exact_predictions }} exactos)</span></span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
        <div class="bg-white rounded-lg shadow p-4">
            <h2 class="font-bold text-pachon-green-dark mb-3">📜 Últimos 5 Partidos Calculados</h2>
            @if ($lastCalculated->isEmpty())
                <p class="text-sm text-gray-500 italic">Aún no hay partidos finalizados.</p>
            @else
                <ul class="divide-y">
                    @foreach ($lastCalculated as $g)
                        <li class="py-2 text-sm flex items-center justify-between">
                            <span><span class="text-xs font-mono text-gray-500">#{{ $g->match_number }}</span> {{ $g->home_team }} <span class="font-mono font-bold mx-1">{{ $g->home_score_official }}-{{ $g->away_score_official }}</span> {{ $g->away_team }}</span>
                            <span class="text-xs text-gray-400">{{ \Carbon\Carbon::parse($g->updated_at)->locale('es')->diffForHumans() }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
</div>
@endsection
