{{--
  Partial: visualizador de llaves eliminatorias (bracket).

  Vars esperadas:
    $knockoutPhases — Collection<TournamentPhase> con type=knockout|third_place,
                      eager-loaded con matches.homeTeam / matches.awayTeam.

  Uso: @include('torneos._bracket', ['knockoutPhases' => $knockoutPhases])
--}}
@php
    $mainPhases  = $knockoutPhases->where('type', 'knockout')->values();
    $thirdPlace  = $knockoutPhases->firstWhere('type', 'third_place');

    // Cantidad de partidos en la primera ronda = escala del bracket.
    $firstCount  = $mainPhases->first()?->matches->count() ?? 1;
    $matchH      = 84;  // px — altura de cada tarjeta de partido
    $containerH  = $firstCount * ($matchH + 20) + 20; // altura fija del bracket
@endphp

{{-- ── Header ── --}}
<div class="mb-4 flex items-center justify-between">
    <p class="font-mono text-[11px] tracking-wide-label uppercase text-ink-mute">
        Llaves eliminatorias
    </p>
    @if ($mainPhases->isEmpty())
        <span class="font-mono text-[11px] text-ink-mute">Todavía no hay partidos de eliminatoria.</span>
    @endif
</div>

@if ($mainPhases->isNotEmpty())
{{-- ── Bracket principal ── --}}
<div class="overflow-x-auto pb-2">
    <div class="flex gap-0 min-w-max" style="height: {{ $containerH }}px">
        @foreach ($mainPhases as $ri => $phase)
        @php
            $matchCount = $phase->matches->count();
        @endphp
        {{-- Columna de ronda --}}
        <div class="flex flex-col shrink-0" style="width: 200px">
            {{-- Label de ronda --}}
            <p class="font-mono text-[10px] uppercase tracking-wide-label text-ink-mute text-center pb-2 shrink-0">
                {{ $phase->name }}
            </p>
            {{-- Contenedor de partidos con justify-around — la magia del alineamiento --}}
            <div class="flex flex-col justify-around flex-1">
                @foreach ($phase->matches as $mi => $match)
                @php
                    $home    = $match->homeTeam;
                    $away    = $match->awayTeam;
                    $played  = $match->status === 'finished';
                    $homeWon = $played && $match->home_score > $match->away_score;
                    $awayWon = $played && $match->away_score > $match->home_score;
                @endphp
                <div class="relative flex items-center" style="height: {{ $matchH }}px">

                    {{-- Línea de conexión hacia la siguiente ronda (excepto en la final) --}}
                    @unless ($ri === $mainPhases->count() - 1)
                    <div class="absolute right-0 top-1/2 w-3 border-t border-line-soft"></div>
                    {{-- Líneas que conectan pares (una raya vertical cada 2 partidos) --}}
                    @if ($mi % 2 === 0)
                        <div class="absolute right-0 border-r border-line-soft"
                             style="top: 50%; height: calc({{ $matchH }}px + {{ ($containerH / ($matchCount)) - $matchH }}px / 2)"></div>
                    @else
                        <div class="absolute right-0 border-r border-line-soft"
                             style="bottom: 50%; height: calc({{ $matchH }}px + {{ ($containerH / ($matchCount)) - $matchH }}px / 2)"></div>
                    @endif
                    @endunless

                    {{-- Tarjeta del partido --}}
                    <div class="bg-white border border-line rounded-md overflow-hidden shadow-card"
                         style="width: 188px; height: {{ $matchH - 8 }}px">

                        {{-- Equipo local --}}
                        <div class="flex items-center justify-between px-2.5 py-1.5 border-b border-line-soft
                                    {{ $homeWon ? 'bg-gol/10' : '' }}">
                            <div class="flex items-center gap-1.5 min-w-0">
                                @if ($home?->color)
                                    <span class="w-2 h-2 rounded-full shrink-0" style="background:{{ $home->color }}"></span>
                                @endif
                                <span class="font-display font-bold text-[12px] truncate {{ $homeWon ? 'text-gol-deep' : ($home ? 'text-pitch' : 'text-ink-mute') }}">
                                    {{ $home?->name ?? 'Por definir' }}
                                </span>
                            </div>
                            @if ($played)
                                <span class="font-mono font-bold text-[14px] shrink-0 {{ $homeWon ? 'text-gol-deep' : 'text-ink' }}">
                                    {{ $match->home_score }}
                                </span>
                            @endif
                        </div>

                        {{-- Equipo visitante --}}
                        <div class="flex items-center justify-between px-2.5 py-1.5
                                    {{ $awayWon ? 'bg-gol/10' : '' }}">
                            <div class="flex items-center gap-1.5 min-w-0">
                                @if ($away?->color)
                                    <span class="w-2 h-2 rounded-full shrink-0" style="background:{{ $away->color }}"></span>
                                @endif
                                <span class="font-display font-bold text-[12px] truncate {{ $awayWon ? 'text-gol-deep' : ($away ? 'text-pitch' : 'text-ink-mute') }}">
                                    {{ $away?->name ?? 'Por definir' }}
                                </span>
                            </div>
                            @if ($played)
                                <span class="font-mono font-bold text-[14px] shrink-0 {{ $awayWon ? 'text-gol-deep' : 'text-ink' }}">
                                    {{ $match->away_score }}
                                </span>
                            @endif
                        </div>

                        {{-- Fecha si está programada y no jugada --}}
                        @if (! $played && $match->scheduled_at)
                            <div class="border-t border-line-soft px-2.5 py-0.5">
                                <span class="font-mono text-[9px] text-ink-mute">
                                    {{ $match->scheduled_at->format('d/m H:i') }}
                                </span>
                            </div>
                        @elseif ($played)
                            <div class="border-t border-line-soft px-2.5 py-0.5 bg-gol/5">
                                <span class="font-mono text-[9px] text-gol-deep uppercase">Jugado</span>
                            </div>
                        @else
                            <div class="border-t border-line-soft px-2.5 py-0.5">
                                <span class="font-mono text-[9px] text-ink-mute">Sin fecha</span>
                            </div>
                        @endif
                    </div>

                </div>
                @endforeach
            </div>
        </div>

        {{-- Conector horizontal entre rondas --}}
        @unless ($ri === $mainPhases->count() - 1)
        <div class="flex items-center shrink-0" style="width: 20px">
            <div class="w-full border-t border-line-soft border-dashed"></div>
        </div>
        @endunless

        @endforeach
    </div>
</div>

{{-- ── Tercer puesto (separado, debajo del bracket) ── --}}
@if ($thirdPlace && $thirdPlace->matches->isNotEmpty())
@php
    $tp = $thirdPlace->matches->first();
    $tpPlayed = $tp->status === 'finished';
@endphp
<div class="mt-4 pt-4 border-t border-line-soft">
    <p class="font-mono text-[10px] uppercase tracking-wide-label text-ink-mute mb-2">Tercer puesto</p>
    <div class="bg-white border border-line rounded-md overflow-hidden shadow-card inline-flex flex-col" style="width: 188px">
        <div class="flex items-center justify-between px-2.5 py-1.5 border-b border-line-soft">
            <span class="font-display font-bold text-[12px] truncate text-pitch">{{ $tp->homeTeam?->name ?? 'Por definir' }}</span>
            @if ($tpPlayed)<span class="font-mono font-bold text-[14px]">{{ $tp->home_score }}</span>@endif
        </div>
        <div class="flex items-center justify-between px-2.5 py-1.5">
            <span class="font-display font-bold text-[12px] truncate text-pitch">{{ $tp->awayTeam?->name ?? 'Por definir' }}</span>
            @if ($tpPlayed)<span class="font-mono font-bold text-[14px]">{{ $tp->away_score }}</span>@endif
        </div>
        <div class="border-t border-line-soft px-2.5 py-0.5">
            <span class="font-mono text-[9px] {{ $tpPlayed ? 'text-gol-deep' : 'text-ink-mute' }} uppercase">
                {{ $tpPlayed ? 'Jugado' : 'Tercer puesto' }}
            </span>
        </div>
    </div>
</div>
@endif
@endif
