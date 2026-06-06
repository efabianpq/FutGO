@extends('layouts.public')

@section('title', $tournament->name . ' · FutGO')
@section('og_description', $tournament->name . ' — ' . $summary['teams'] . ' equipos · ' . $summary['played'] . ' partidos jugados. Tabla, resultados y goleadores en vivo.')
@if ($tournament->banner_url || $tournament->logo_url)
    @section('og_image', $tournament->banner_url ?? $tournament->logo_url)
@endif

@php
    $statusMeta = [
        'draft' => ['Borrador', 'bg-ink-mute'],
        'open' => ['Inscripción abierta', 'bg-gol'],
        'in_progress' => ['En juego', 'bg-gol-deep'],
        'finished' => ['Finalizado', 'bg-ink-soft'],
        'cancelled' => ['Cancelado', 'bg-alerta'],
    ];
    [$statusLabel, $statusBg] = $statusMeta[$tournament->status] ?? [$tournament->status, 'bg-ink-mute'];
    $fmt = fn ($d) => $d ? \Carbon\Carbon::parse($d)->locale('es')->isoFormat('ddd D MMM · HH:mm') : 'Por programar';
@endphp

@section('content')
<div class="max-w-3xl mx-auto px-4 py-6" x-data="{ shareUrl: window.location.href, shareTitle: @js($tournament->name) }">

    {{-- ── Cabecera del torneo ─────────────────────────────────────────── --}}
    @if ($tournament->banner_url)
        <img src="{{ $tournament->banner_url }}" alt="" class="w-full h-32 sm:h-44 object-cover rounded-md border border-line mb-4">
    @endif

    <div class="bg-white border border-line rounded-md shadow-card-2 p-5 mb-5">
        <div class="flex items-start gap-4">
            @if ($tournament->logo_url)
                <img src="{{ $tournament->logo_url }}" alt="" class="w-16 h-16 rounded-md object-cover border border-line shrink-0">
            @endif
            <div class="min-w-0 flex-1">
                <span class="inline-block {{ $statusBg }} text-bone font-display font-bold text-[11px] uppercase tracking-wide-label px-2 py-0.5 rounded-xs">{{ $statusLabel }}</span>
                <h1 class="font-display font-extrabold text-display-s sm:text-display-m text-pitch uppercase mt-2 leading-tight break-words">{{ $tournament->name }}</h1>
                <p class="font-mono text-[12px] text-ink-mute mt-1">
                    @if ($tournament->category){{ $tournament->category }} · @endif
                    @if ($tournament->city){{ $tournament->city }}@endif
                </p>
            </div>
        </div>

        <div class="grid grid-cols-3 gap-2 mt-4">
            @foreach ([['Equipos', $summary['teams']], ['Jugados', $summary['played']], ['Próximos', $summary['scheduled']]] as [$lbl, $val])
                <div class="bg-bone-soft border border-line rounded-md p-2.5 text-center">
                    <p class="font-display font-extrabold text-xl text-pitch">{{ $val }}</p>
                    <p class="font-mono text-[10px] uppercase tracking-wide-label text-ink-mute">{{ $lbl }}</p>
                </div>
            @endforeach
        </div>

        {{-- Compartir + exportar --}}
        <div class="flex flex-wrap items-center gap-2 mt-4 pt-4 border-t border-line-soft">
            <button type="button"
                    @click="navigator.share ? navigator.share({title: shareTitle, url: shareUrl}) : window.open('https://wa.me/?text=' + encodeURIComponent(shareTitle + ' ' + shareUrl), '_blank')"
                    class="inline-flex items-center gap-1.5 bg-gol-deep text-bone font-display font-bold text-[12px] uppercase tracking-wide-label px-3 py-2 rounded-md hover:opacity-90">
                📲 Compartir
            </button>
            <div x-data="{ open: false }" class="relative">
                <button type="button" @click="open=!open" class="inline-flex items-center gap-1.5 bg-white border border-line text-pitch font-display font-bold text-[12px] uppercase tracking-wide-label px-3 py-2 rounded-md hover:bg-bone-soft">🖼️ Imágenes</button>
                <div x-show="open" x-cloak @click.outside="open=false" class="absolute z-10 mt-1 bg-white border border-line rounded-md shadow-card-2 py-1 w-48">
                    <a href="{{ route('torneos.public.img', [$tournament, 'goleadores']) }}" target="_blank" class="block px-3 py-2 text-[13px] text-ink hover:bg-bone-soft">Goleadores</a>
                    <a href="{{ route('torneos.public.img', [$tournament, 'posiciones']) }}" target="_blank" class="block px-3 py-2 text-[13px] text-ink hover:bg-bone-soft">Posiciones</a>
                    @if ($tournament->mvpEnabled())
                        <a href="{{ route('torneos.public.img', [$tournament, 'mvp']) }}" target="_blank" class="block px-3 py-2 text-[13px] text-ink hover:bg-bone-soft">MVP de la fecha</a>
                    @endif
                </div>
            </div>
            <div x-data="{ open: false }" class="relative">
                <button type="button" @click="open=!open" class="inline-flex items-center gap-1.5 bg-white border border-line text-pitch font-display font-bold text-[12px] uppercase tracking-wide-label px-3 py-2 rounded-md hover:bg-bone-soft">⬇ Exportar</button>
                <div x-show="open" x-cloak @click.outside="open=false" class="absolute z-10 mt-1 bg-white border border-line rounded-md shadow-card-2 py-1 w-52">
                    @foreach (['resultados' => 'Resultados', 'posiciones' => 'Posiciones', 'estadisticas' => 'Estadísticas'] as $ds => $lbl)
                        <div class="flex items-center justify-between px-3 py-1.5 hover:bg-bone-soft">
                            <span class="text-[13px] text-ink">{{ $lbl }}</span>
                            <span class="flex gap-1.5">
                                <a href="{{ route('torneos.public.export', [$tournament, $ds, 'pdf']) }}" class="font-mono text-[11px] text-alerta font-bold">PDF</a>
                                <a href="{{ route('torneos.public.export', [$tournament, $ds, 'csv']) }}" class="font-mono text-[11px] text-pitch font-bold">CSV</a>
                            </span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    {{-- ── Ficha del torneo (E7/H10): info de presentación + inscripción ──── --}}
    @php
        $formatLabels = [
            'groups_and_knockout' => 'Grupos + Eliminación',
            'knockout_only'       => 'Solo eliminación',
            'round_robin'         => 'Todos contra todos',
            'liga'                => 'Liga / Abierto',
        ];
        $fmtDate = fn ($d) => $d ? \Carbon\Carbon::parse($d)->locale('es')->isoFormat('D MMM YYYY') : null;
        $hasSportInfo = $tournament->match_duration || $tournament->min_players_per_team || $tournament->max_players_per_team;
    @endphp

    <section class="bg-white border border-line rounded-md shadow-card-2 mb-5 overflow-hidden">
        <div class="bg-pitch-mist border-b border-line px-4 py-2.5">
            <p class="font-mono text-[11px] tracking-wide-label uppercase text-pitch">Información del torneo</p>
        </div>
        <div class="p-5 space-y-5">
            {{-- Inscripción --}}
            <div>
                <p class="font-display font-bold text-pitch uppercase text-[13px] mb-2">Inscripción</p>
                <dl class="grid grid-cols-2 gap-x-4 gap-y-1.5 text-[13px]">
                    <div class="flex justify-between"><dt class="text-ink-mute">Costo</dt><dd class="font-semibold text-pitch">{{ (int) $tournament->registration_fee > 0 ? '$' . number_format($tournament->registration_fee, 0, ',', '.') : 'Gratuita' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-ink-mute">Cupos</dt><dd class="font-semibold text-pitch">{{ $approvedTeams }} / {{ $tournament->max_teams }}</dd></div>
                    @if ($tournament->registration_opens_at)
                        <div class="flex justify-between"><dt class="text-ink-mute">Abre</dt><dd class="font-mono text-pitch">{{ $fmtDate($tournament->registration_opens_at) }}</dd></div>
                    @endif
                    @if ($tournament->registration_closes_at)
                        <div class="flex justify-between"><dt class="text-ink-mute">Cierra</dt><dd class="font-mono text-pitch">{{ $fmtDate($tournament->registration_closes_at) }}</dd></div>
                    @endif
                </dl>
            </div>

            {{-- Ubicación y fechas --}}
            @if ($tournament->city || $tournament->venue || $tournament->starts_at || $tournament->ends_at)
                <div class="pt-4 border-t border-line-soft">
                    <p class="font-display font-bold text-pitch uppercase text-[13px] mb-2">Ubicación y fechas</p>
                    <dl class="grid grid-cols-2 gap-x-4 gap-y-1.5 text-[13px]">
                        @if ($tournament->city)<div class="flex justify-between"><dt class="text-ink-mute">Ciudad</dt><dd class="font-semibold text-pitch">{{ $tournament->city }}</dd></div>@endif
                        @if ($tournament->venue)<div class="flex justify-between"><dt class="text-ink-mute">Sede</dt><dd class="font-semibold text-pitch">{{ $tournament->venue }}</dd></div>@endif
                        @if ($tournament->starts_at)<div class="flex justify-between"><dt class="text-ink-mute">Inicio</dt><dd class="font-mono text-pitch">{{ $fmtDate($tournament->starts_at) }}</dd></div>@endif
                        @if ($tournament->ends_at)<div class="flex justify-between"><dt class="text-ink-mute">Fin</dt><dd class="font-mono text-pitch">{{ $fmtDate($tournament->ends_at) }}</dd></div>@endif
                    </dl>
                </div>
            @endif

            {{-- Formato y reglas deportivas --}}
            <div class="pt-4 border-t border-line-soft">
                <p class="font-display font-bold text-pitch uppercase text-[13px] mb-2">Formato y reglas</p>
                <dl class="grid grid-cols-2 gap-x-4 gap-y-1.5 text-[13px]">
                    <div class="flex justify-between"><dt class="text-ink-mute">Formato</dt><dd class="font-semibold text-pitch">{{ $formatLabels[$tournament->format] ?? $tournament->format }}</dd></div>
                    @if ($tournament->category)<div class="flex justify-between"><dt class="text-ink-mute">Categoría</dt><dd class="font-semibold text-pitch capitalize">{{ $tournament->category }}</dd></div>@endif
                    @if ($tournament->match_duration)<div class="flex justify-between"><dt class="text-ink-mute">Duración</dt><dd class="font-semibold text-pitch">{{ $tournament->match_duration }} min</dd></div>@endif
                    @if ($tournament->min_players_per_team || $tournament->max_players_per_team)
                        <div class="flex justify-between"><dt class="text-ink-mute">Jugadores</dt><dd class="font-semibold text-pitch">{{ $tournament->min_players_per_team }}–{{ $tournament->max_players_per_team }}</dd></div>
                    @endif
                    <div class="flex justify-between"><dt class="text-ink-mute">Puntos (V/E/D)</dt><dd class="font-mono text-pitch">{{ $tournament->points_win }}/{{ $tournament->points_draw }}/{{ $tournament->points_loss }}</dd></div>
                </dl>
            </div>

            {{-- Premios y reglamento --}}
            @if ($tournament->prize_description || $tournament->rules)
                <div class="pt-4 border-t border-line-soft">
                    <p class="font-display font-bold text-pitch uppercase text-[13px] mb-2">Premios y reglamento</p>
                    @if ($tournament->prize_description)
                        <p class="text-[13px] text-ink mb-2"><span class="text-ink-mute">🏆 Premio:</span> {{ $tournament->prize_description }}</p>
                    @endif
                    @if ($tournament->rules)
                        <p class="text-[13px] text-ink-soft whitespace-pre-line">{{ $tournament->rules }}</p>
                    @endif
                </div>
            @endif

            {{-- Botón de inscripción --}}
            @if ($canRegister)
                <div class="pt-4 border-t border-line-soft">
                    @auth
                        @if ($isCaptain)
                            <a href="{{ route('torneos.equipo.inscribir', $tournament) }}"
                               class="inline-flex items-center gap-1.5 bg-gol-deep text-bone font-display font-bold text-[13px] uppercase tracking-wide-label px-5 py-2.5 rounded-md hover:opacity-90">
                                Inscribir mi equipo
                            </a>
                        @else
                            <a href="{{ route('torneos.mis-equipos') }}"
                               class="inline-flex items-center gap-1.5 bg-gol-deep text-bone font-display font-bold text-[13px] uppercase tracking-wide-label px-5 py-2.5 rounded-md hover:opacity-90">
                                Creá tu equipo para inscribirte
                            </a>
                        @endif
                    @else
                        <a href="{{ route('login') }}"
                           class="inline-flex items-center gap-1.5 bg-gol-deep text-bone font-display font-bold text-[13px] uppercase tracking-wide-label px-5 py-2.5 rounded-md hover:opacity-90">
                            Ingresá para inscribir tu equipo
                        </a>
                    @endauth
                </div>
            @endif
        </div>
    </section>

    {{-- ── Tabla de posiciones ─────────────────────────────────────────── --}}
    @foreach ($standings as $phase)
        @foreach ($phase->groups as $group)
            <section class="bg-white border border-line rounded-md shadow-card mb-4 overflow-hidden">
                <div class="bg-pitch-mist border-b border-line px-4 py-2.5">
                    <p class="font-mono text-[11px] tracking-wide-label uppercase text-pitch">{{ $group->name }}</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-[13px] min-w-[460px]">
                        <thead>
                            <tr class="bg-bone-soft text-ink-mute font-mono text-[10px] uppercase tracking-wide-label">
                                <th class="text-left px-3 py-2 w-8">#</th>
                                <th class="text-left px-2 py-2">Equipo</th>
                                @foreach (['PJ','PG','PE','PP','GF','GC','DG','Pts'] as $h)
                                    <th class="text-center px-2 py-2">{{ $h }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line-soft">
                            @forelse ($group->standings as $s)
                                <tr>
                                    <td class="px-3 py-2 font-display font-bold text-pitch">{{ $s->position }}</td>
                                    <td class="px-2 py-2 font-semibold text-ink whitespace-nowrap">{{ $s->team?->name ?? '—' }}</td>
                                    <td class="text-center px-2 py-2">{{ $s->played }}</td>
                                    <td class="text-center px-2 py-2">{{ $s->won }}</td>
                                    <td class="text-center px-2 py-2">{{ $s->drawn }}</td>
                                    <td class="text-center px-2 py-2">{{ $s->lost }}</td>
                                    <td class="text-center px-2 py-2">{{ $s->goals_for }}</td>
                                    <td class="text-center px-2 py-2">{{ $s->goals_against }}</td>
                                    <td class="text-center px-2 py-2">{{ $s->goal_difference }}</td>
                                    <td class="text-center px-2 py-2 font-display font-extrabold text-pitch">{{ $s->points }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="10" class="px-3 py-4 text-center text-ink-soft">Aún sin posiciones.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        @endforeach
    @endforeach

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        {{-- ── Resultados ──────────────────────────────────────────────── --}}
        <section class="bg-white border border-line rounded-md shadow-card overflow-hidden">
            <div class="bg-pitch-mist border-b border-line px-4 py-2.5">
                <p class="font-mono text-[11px] tracking-wide-label uppercase text-pitch">Resultados</p>
            </div>
            @forelse ($results as $m)
                <a href="{{ route('torneos.public.img.match', [$tournament, $m]) }}" target="_blank"
                   class="flex items-center gap-2 px-3 py-2.5 border-b border-line-soft last:border-0 hover:bg-bone-soft">
                    <span class="flex-1 text-right text-[13px] font-semibold text-ink truncate">{{ $m->homeTeam?->name ?? '—' }}</span>
                    <span class="font-display font-extrabold text-pitch shrink-0">{{ $m->home_score }}-{{ $m->away_score }}</span>
                    <span class="flex-1 text-left text-[13px] font-semibold text-ink truncate">{{ $m->awayTeam?->name ?? '—' }}</span>
                </a>
            @empty
                <p class="px-3 py-4 text-center text-ink-soft text-[14px]">Todavía no hay partidos jugados.</p>
            @endforelse
        </section>

        {{-- ── Próximos partidos ───────────────────────────────────────── --}}
        <section class="bg-white border border-line rounded-md shadow-card overflow-hidden">
            <div class="bg-pitch-mist border-b border-line px-4 py-2.5">
                <p class="font-mono text-[11px] tracking-wide-label uppercase text-pitch">Próximos partidos</p>
            </div>
            @forelse ($upcoming as $m)
                <div class="px-3 py-2.5 border-b border-line-soft last:border-0">
                    <div class="flex items-center gap-2">
                        <span class="flex-1 text-right text-[13px] font-semibold text-ink truncate">{{ $m->homeTeam?->name ?? 'Por definir' }}</span>
                        <span class="font-mono text-[11px] text-ink-mute shrink-0">vs</span>
                        <span class="flex-1 text-left text-[13px] font-semibold text-ink truncate">{{ $m->awayTeam?->name ?? 'Por definir' }}</span>
                    </div>
                    <p class="text-center font-mono text-[10px] text-ink-mute mt-1">
                        🗓 {{ $fmt($m->scheduled_at) }}@if ($m->venue) · 📍 {{ $m->venue }}@endif
                    </p>
                </div>
            @empty
                <p class="px-3 py-4 text-center text-ink-soft text-[14px]">No hay partidos programados.</p>
            @endforelse
        </section>
    </div>

    {{-- ── Goleadores ──────────────────────────────────────────────────── --}}
    <section class="bg-white border border-line rounded-md shadow-card mt-4 overflow-hidden">
        <div class="bg-pitch-mist border-b border-line px-4 py-2.5 flex items-center justify-between">
            <p class="font-mono text-[11px] tracking-wide-label uppercase text-pitch">⚽ Goleadores</p>
            <a href="{{ route('torneos.public.img', [$tournament, 'goleadores']) }}" target="_blank" class="font-mono text-[10px] text-gol-deep font-bold uppercase tracking-wide-label">Compartir 🖼️</a>
        </div>
        @forelse ($scorers as $i => $st)
            <div class="flex items-center gap-3 px-4 py-2.5 border-b border-line-soft last:border-0">
                <span class="font-display font-extrabold text-pitch w-5 text-center">{{ $i + 1 }}</span>
                <div class="flex-1 min-w-0">
                    <p class="font-semibold text-ink truncate">{{ $st->teamPlayer?->displayName() ?? '—' }}</p>
                    <p class="font-mono text-[11px] text-ink-mute truncate">{{ $st->teamPlayer?->team?->name ?? '—' }}</p>
                </div>
                <span class="font-display font-extrabold text-lg text-gol-deep">{{ $st->goals }}</span>
            </div>
        @empty
            <p class="px-3 py-4 text-center text-ink-soft text-[14px]">Aún no hay goles registrados.</p>
        @endforelse
    </section>

    {{-- ── Patrocinadores (Sesión G) ───────────────────────────────────── --}}
    @if ($sponsors->isNotEmpty())
        <section class="bg-white border border-line rounded-md shadow-card mt-4 p-5">
            <p class="font-mono text-[11px] tracking-wide-label uppercase text-ink-mute text-center mb-4">Patrocinan este torneo</p>
            <div class="flex flex-wrap items-center justify-center gap-5">
                @foreach ($sponsors as $sponsor)
                    @php $inner = $sponsor->logo_url
                        ? '<img src="' . e($sponsor->logo_url) . '" alt="' . e($sponsor->name) . '" class="max-h-12 max-w-[140px] object-contain">'
                        : '<span class="font-display font-bold text-pitch">' . e($sponsor->name) . '</span>'; @endphp
                    @if ($sponsor->link_url)
                        <a href="{{ $sponsor->link_url }}" target="_blank" rel="noopener" title="{{ $sponsor->name }}" class="opacity-90 hover:opacity-100">{!! $inner !!}</a>
                    @else
                        <span title="{{ $sponsor->name }}">{!! $inner !!}</span>
                    @endif
                @endforeach
            </div>
        </section>
    @endif

</div>
@endsection
