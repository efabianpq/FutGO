@extends('layouts.app')
@section('title', 'Ranking FUTGO')

@section('content')
@php
    $qs = fn (array $over) => http_build_query(array_merge(['type' => $type, 'scope' => $scopeType, 'value' => $scopeValue], $over));
@endphp

<div class="max-w-4xl mx-auto px-4 sm:px-6 py-8"
     x-data="{ q: '', match(s){ return this.q === '' || s.toLowerCase().includes(this.q.toLowerCase()); } }">
    <p class="eyebrow">📊 Reputación FUTGO</p>
    <h1 class="font-display font-bold text-display-s sm:text-display-m text-pitch uppercase mt-2 mb-1">Ranking de la plataforma</h1>
    <p class="text-[14px] text-ink-soft mb-1">Acumulado de toda la actividad en FUTGO. Se recalcula al finalizar torneos y diariamente por cron.</p>

    <div class="flex flex-wrap items-center gap-3 mb-6">
        <p class="text-[12px] text-ink-mute">
            @if ($lastCalculated)
                Actualizado {{ \Illuminate\Support\Carbon::parse($lastCalculated)->diffForHumans() }}.
            @else
                Todavía no se calculó.
            @endif
        </p>
        @if (auth()->user()?->isAdmin())
            <form method="POST" action="{{ route('admin.ranking.recalculate') }}">
                @csrf
                <button type="submit" class="text-[12px] font-display font-bold uppercase text-pitch hover:underline">Recalcular ahora</button>
            </form>
        @endif
    </div>

    {{-- Tipo: jugadores / equipos --}}
    <div class="flex gap-2 mb-4">
        <a href="?{{ $qs(['type' => 'player']) }}"
           class="px-4 py-2 rounded-md font-display font-bold text-[13px] uppercase tracking-wide-label {{ $type === 'player' ? 'bg-pitch text-bone' : 'bg-white border border-line text-pitch' }}">Jugadores</a>
        <a href="?{{ $qs(['type' => 'team']) }}"
           class="px-4 py-2 rounded-md font-display font-bold text-[13px] uppercase tracking-wide-label {{ $type === 'team' ? 'bg-pitch text-bone' : 'bg-white border border-line text-pitch' }}">Equipos</a>
    </div>

    {{-- Alcance: global / ciudad / categoría --}}
    <form method="GET" class="flex flex-wrap items-end gap-3 mb-6 bg-white border border-line rounded-md p-4">
        <input type="hidden" name="type" value="{{ $type }}">
        <div class="flex flex-col gap-1.5">
            <label class="font-mono text-[11px] tracking-wide-label uppercase text-ink-soft">Alcance</label>
            <select name="scope" onchange="this.form.submit()"
                    class="h-[42px] px-3 bg-white border-[1.5px] border-line rounded-md text-[14px] focus:border-pitch focus:ring-0">
                <option value="global" @selected($scopeType === 'global')>Global</option>
                <option value="city" @selected($scopeType === 'city')>Por ciudad</option>
                <option value="category" @selected($scopeType === 'category')>Por categoría</option>
            </select>
        </div>
        @if ($scopeType === 'city')
            <div class="flex flex-col gap-1.5">
                <label class="font-mono text-[11px] tracking-wide-label uppercase text-ink-soft">Ciudad</label>
                <select name="value" onchange="this.form.submit()"
                        class="h-[42px] px-3 bg-white border-[1.5px] border-line rounded-md text-[14px] focus:border-pitch focus:ring-0">
                    <option value="">— Elegí —</option>
                    @foreach ($cities as $c)
                        <option value="{{ $c }}" @selected($scopeValue === $c)>{{ $c }}</option>
                    @endforeach
                </select>
            </div>
        @elseif ($scopeType === 'category')
            <div class="flex flex-col gap-1.5">
                <label class="font-mono text-[11px] tracking-wide-label uppercase text-ink-soft">Categoría</label>
                <select name="value" onchange="this.form.submit()"
                        class="h-[42px] px-3 bg-white border-[1.5px] border-line rounded-md text-[14px] focus:border-pitch focus:ring-0 capitalize">
                    <option value="">— Elegí —</option>
                    @foreach ($categories as $c)
                        <option value="{{ $c }}" @selected($scopeValue === $c)>{{ $c }}</option>
                    @endforeach
                </select>
            </div>
        @endif
    </form>

    {{-- Buscador por nombre --}}
    <div class="mb-4"><x-search-input :placeholder="$type === 'player' ? 'Buscar jugador…' : 'Buscar equipo…'" /></div>

    {{-- Tabla de ranking --}}
    <div class="bg-white border border-line rounded-md shadow-card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-[13px] min-w-[560px]">
                <thead>
                    <tr class="bg-pitch-mist text-ink-mute font-mono text-[10px] uppercase tracking-wide-label">
                        <th class="text-left px-3 py-2.5 w-10">#</th>
                        <th class="text-left px-2 py-2.5">{{ $type === 'player' ? 'Jugador' : 'Equipo' }}</th>
                        <th class="text-center px-2 py-2.5">PJ</th>
                        <th class="text-center px-2 py-2.5">Goles</th>
                        <th class="text-center px-2 py-2.5">MVP</th>
                        <th class="text-center px-2 py-2.5">Fair</th>
                        <th class="text-center px-3 py-2.5">Puntaje</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line-soft">
                    @forelse ($rankings as $r)
                        <tr class="{{ $r->position <= 3 ? 'bg-gol/5' : '' }}"
                            x-show="match(@js($r->display_name))" x-cloak>
                            <td class="px-3 py-2.5 font-display font-extrabold {{ $r->position <= 3 ? 'text-gol-deep' : 'text-pitch' }}">{{ $r->position }}</td>
                            <td class="px-2 py-2.5 font-semibold text-ink whitespace-nowrap">{{ $r->display_name }}</td>
                            <td class="text-center px-2 py-2.5">{{ $r->matches_played }}</td>
                            <td class="text-center px-2 py-2.5">{{ $r->goals }}</td>
                            <td class="text-center px-2 py-2.5">{{ $r->mvps }}</td>
                            <td class="text-center px-2 py-2.5">
                                <span class="font-mono text-[12px] {{ $r->fair_play >= 90 ? 'text-gol-deep' : ($r->fair_play < 60 ? 'text-alerta' : 'text-ink') }}">{{ $r->fair_play }}</span>
                            </td>
                            <td class="text-center px-3 py-2.5 font-display font-extrabold text-pitch">{{ $r->score }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-3 py-8 text-center text-ink-soft">
                            @if ($scopeType !== 'global' && ! $scopeValue)
                                Elegí {{ $scopeType === 'city' ? 'una ciudad' : 'una categoría' }} para ver su ranking.
                            @else
                                Todavía no hay datos de ranking. Se genera al finalizar torneos.
                            @endif
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <p class="text-[12px] text-ink-mute mt-4">
        Puntaje = goles·4 + asistencias·2 + MVP·6 + victorias·3 + vallas·2 + partidos·1 + fair&nbsp;play·0.5.
    </p>
</div>
@endsection
