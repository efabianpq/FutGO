@extends('layouts.app')
@section('title', 'Admin · Resultados')

@section('content')
@include('admin._nav')

<div class="max-w-6xl mx-auto px-4 py-8">
    <p class="eyebrow">Vista crítica</p>
    <h1 class="font-display font-bold text-display-m text-pitch uppercase mt-2 mb-3">Ingreso de resultados</h1>
    <p class="font-body text-body-s text-ink-soft mb-6 max-w-prose">Al guardar un resultado, se calculan automáticamente los puntos de todos los pronósticos del partido y se recalcula el ranking. Podés volver a guardar el mismo partido para corregir.</p>

    @error('result')<div class="bg-alerta/10 border border-alerta text-alerta px-4 py-2 rounded-md mb-4 font-mono text-[12px]">{{ $message }}</div>@enderror

    <section class="mb-10">
        <header class="flex items-end justify-between mb-3 pb-2 border-b-2 border-pitch">
            <h2 class="font-display font-bold text-display-s text-pitch uppercase">⏰ Pendientes de resultado</h2>
            <span class="font-mono text-[11px] tracking-wide-label uppercase text-ink-mute">{{ $pending->count() }} partidos</span>
        </header>
        <div class="bg-white border border-line rounded-md shadow-card overflow-x-auto">
            <table class="w-full">
                <thead class="bg-pitch text-bone font-mono text-[10.5px] tracking-wide-label uppercase text-left">
                    <tr>
                        <th class="px-3 py-2.5">#</th>
                        <th class="px-3 py-2.5">Partido</th>
                        <th class="px-3 py-2.5">Fecha</th>
                        <th class="px-3 py-2.5 text-right">Goles · Acción</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line-soft">
                    @forelse ($pending as $g)
                        <tr class="hover:bg-bone-soft transition-colors duration-fast">
                            <td class="px-3 py-3 font-mono text-[11px] text-ink-mute">{{ $g->match_number }}</td>
                            <td class="px-3 py-3 font-display font-semibold">{{ $g->home_flag }} {{ $g->home_team }} <span class="text-ink-mute font-body normal-case">vs</span> {{ $g->away_flag }} {{ $g->away_team }}</td>
                            <td class="px-3 py-3 font-mono text-[11px] text-ink-soft">{{ $g->match_datetime->locale('es')->isoFormat('ddd D MMM HH:mm') }}</td>
                            <td class="px-3 py-3">
                                <form method="POST" action="{{ route('admin.results.store', $g->id) }}" class="flex items-center justify-end gap-2 flex-wrap">
                                    @csrf
                                    <input type="number" name="home_score" min="0" max="20" required
                                           class="w-14 h-12 text-center font-display font-extrabold text-display-s text-pitch bg-bone-soft border-[1.5px] border-line rounded-md focus:border-pitch focus:bg-white focus:ring-0">
                                    <span class="font-mono text-[11px] text-ink-mute">vs</span>
                                    <input type="number" name="away_score" min="0" max="20" required
                                           class="w-14 h-12 text-center font-display font-extrabold text-display-s text-pitch bg-bone-soft border-[1.5px] border-line rounded-md focus:border-pitch focus:bg-white focus:ring-0">
                                    <x-btn type="submit" variant="primary" size="sm">Guardar</x-btn>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-3 py-10 text-center font-body text-body-s text-ink-mute italic">No hay partidos pendientes con hora ya pasada.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section>
        <header class="flex items-end justify-between mb-3 pb-2 border-b-2 border-pitch">
            <h2 class="font-display font-bold text-display-s text-pitch uppercase">✅ Últimos finalizados (recalcular)</h2>
            <span class="font-mono text-[11px] tracking-wide-label uppercase text-ink-mute">{{ $finished->count() }} partidos</span>
        </header>
        <div class="bg-white border border-line rounded-md shadow-card overflow-x-auto">
            <table class="w-full">
                <thead class="bg-pitch text-bone font-mono text-[10.5px] tracking-wide-label uppercase text-left">
                    <tr>
                        <th class="px-3 py-2.5">#</th>
                        <th class="px-3 py-2.5">Partido</th>
                        <th class="px-3 py-2.5 text-right">Resultado · Recalcular</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line-soft">
                    @forelse ($finished as $g)
                        <tr class="hover:bg-bone-soft transition-colors duration-fast">
                            <td class="px-3 py-3 font-mono text-[11px] text-ink-mute">{{ $g->match_number }}</td>
                            <td class="px-3 py-3 font-display font-semibold">{{ $g->home_team }} <span class="text-ink-mute font-body normal-case">vs</span> {{ $g->away_team }}</td>
                            <td class="px-3 py-3">
                                <form method="POST" action="{{ route('admin.results.store', $g->id) }}" class="flex items-center justify-end gap-2 flex-wrap">
                                    @csrf
                                    <input type="number" name="home_score" min="0" max="20" required value="{{ $g->home_score_official }}"
                                           class="w-14 h-12 text-center font-display font-extrabold text-display-s text-pitch bg-bone-soft border-[1.5px] border-line rounded-md focus:border-pitch focus:bg-white focus:ring-0">
                                    <span class="font-mono text-[11px] text-ink-mute">vs</span>
                                    <input type="number" name="away_score" min="0" max="20" required value="{{ $g->away_score_official }}"
                                           class="w-14 h-12 text-center font-display font-extrabold text-display-s text-pitch bg-bone-soft border-[1.5px] border-line rounded-md focus:border-pitch focus:bg-white focus:ring-0">
                                    <x-btn type="submit" variant="accent" size="sm">Recalcular</x-btn>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="px-3 py-10 text-center font-body text-body-s text-ink-mute italic">No hay partidos finalizados todavía.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
@endsection
