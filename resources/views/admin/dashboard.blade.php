@extends('layouts.app')
@section('title', 'Admin · Dashboard')

@section('content')
@include('admin._nav')

<div class="max-w-7xl mx-auto px-4 py-8">
    <p class="eyebrow">Panel administrador</p>
    <h1 class="font-display font-bold text-display-m sm:text-display-l text-pitch uppercase mt-2 mb-6 leading-[0.96]">Dashboard</h1>

    {{-- KPIs --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        <x-stat-card label="Participantes activos" :value="$activeParticipants" sub="con código activado" />
        <x-stat-card label="Pendientes de código" :value="$pendingParticipants" sub="registrados sin activar" accent="gol" />
        <x-stat-card label="Partidos finalizados" :value="$finishedMatches . ' / ' . $totalMatches"
                     sub="{{ $totalMatches > 0 ? round($finishedMatches / $totalMatches * 100) . '% completado' : '' }}" />
        <x-stat-card label="Acumulado total" accent="gol">
            @if ($prizes['pool'] !== null)
                <span class="text-gol-deep">{{ number_format($prizes['pool'], 0, ',', '.') }}</span>
                <span class="text-display-s text-ink-soft block mt-1">COP</span>
            @else
                <span class="font-body text-body italic text-ink-mute">Por definir</span>
            @endif
        </x-stat-card>
    </div>

    {{-- Desglose premios --}}
    @if ($prizes['pool'] !== null)
        <div class="bg-white border border-line rounded-md shadow-card p-5 mb-8">
            <p class="font-mono text-[11px] tracking-wide-label uppercase text-ink-mute mb-4">💰 Desglose de premios</p>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-center">
                <div><p class="font-mono text-[10.5px] tracking-wide-label uppercase text-ink-mute">🥇 60%</p><p class="font-display font-extrabold text-display-s text-gol-deep mt-1">{{ number_format($prizes['first'], 0, ',', '.') }}</p></div>
                <div><p class="font-mono text-[10.5px] tracking-wide-label uppercase text-ink-mute">🥈 20%</p><p class="font-display font-extrabold text-display-s text-ink-soft mt-1">{{ number_format($prizes['second'], 0, ',', '.') }}</p></div>
                <div><p class="font-mono text-[10.5px] tracking-wide-label uppercase text-ink-mute">🥉 10%</p><p class="font-display font-extrabold text-display-s text-[#b87333] mt-1">{{ number_format($prizes['third'], 0, ',', '.') }}</p></div>
                <div><p class="font-mono text-[10.5px] tracking-wide-label uppercase text-ink-mute">Plataforma 10%</p><p class="font-display font-extrabold text-display-s text-ink-mute mt-1">{{ number_format($prizes['platform'], 0, ',', '.') }}</p></div>
            </div>
        </div>
    @endif

    {{-- Top 3 + Últimos calculados --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <div class="bg-white border border-line rounded-md shadow-card p-5">
            <p class="font-mono text-[11px] tracking-wide-label uppercase text-ink-mute mb-4">🏆 Top 3 actual</p>
            @if ($top3->isEmpty())
                <p class="font-body text-body-s text-ink-mute italic">Aún no hay ranking calculado.</p>
            @else
                <ul class="divide-y divide-line-soft">
                    @foreach ($top3 as $r)
                        <li class="py-2.5 flex items-center justify-between">
                            <span class="font-display font-bold text-body">
                                @if ($r->current_position === 1) 🥇
                                @elseif ($r->current_position === 2) 🥈
                                @elseif ($r->current_position === 3) 🥉
                                @endif
                                <span class="ml-1">{{ $r->name }}</span>
                            </span>
                            <span class="font-display font-extrabold text-display-s text-pitch">{{ $r->total_points }} <span class="text-body-s text-ink-mute">/ {{ $r->exact_predictions }}🎯</span></span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
        <div class="bg-white border border-line rounded-md shadow-card p-5">
            <p class="font-mono text-[11px] tracking-wide-label uppercase text-ink-mute mb-4">📜 Últimos 5 partidos calculados</p>
            @if ($lastCalculated->isEmpty())
                <p class="font-body text-body-s text-ink-mute italic">Aún no hay partidos finalizados.</p>
            @else
                <ul class="divide-y divide-line-soft">
                    @foreach ($lastCalculated as $g)
                        <li class="py-2.5 text-body-s flex items-center justify-between gap-2">
                            <span><span class="font-mono text-[11px] tracking-wide-eyebrow text-ink-mute">#{{ $g->match_number }}</span> <span class="font-display font-semibold">{{ $g->home_team }}</span> <span class="font-mono font-bold text-pitch mx-1">{{ $g->home_score_official }}-{{ $g->away_score_official }}</span> <span class="font-display font-semibold">{{ $g->away_team }}</span></span>
                            <span class="font-mono text-[11px] tracking-wide-eyebrow text-ink-mute shrink-0">{{ \Carbon\Carbon::parse($g->updated_at)->locale('es')->diffForHumans() }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
</div>
@endsection
