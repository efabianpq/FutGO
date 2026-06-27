@extends('layouts.app')
@section('title', $club->name . ' · Club · FutGO')

@php
    $clubOgDesc = $club->name . ' · ' . $participations->count() . ' torneo(s) · ' . $agg['played'] . ' partidos jugados.';
    if ($club->captain?->city) {
        $clubOgDesc .= ' ' . $club->captain->city . '.';
    }
    $clubOgDesc .= ' Perfil en FutGO.';
@endphp
@section('og_description', $clubOgDesc)
@if ($club->shield_url)
    @section('og_image', $club->shield_url)
@endif

@section('content')
@php
    $statusMeta = [
        'draft'       => ['Borrador',    'upcoming'],
        'open'        => ['Inscripción', 'win'],
        'in_progress' => ['En juego',    'live'],
        'finished'    => ['Finalizado',  'default'],
        'cancelled'   => ['Cancelado',   'default'],
    ];
    // $canManage viene del controlador via compact() — no sobreescribir acá.
@endphp

<div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

    @if (session('status'))
        <div class="mb-4 bg-gol/20 border border-gol text-pitch-deep px-4 py-3 rounded-md font-display font-semibold">{{ session('status') }}</div>
    @endif

    {{-- Encabezado del club --}}
    <div class="bg-white border border-line rounded-md shadow-card-2 p-5 sm:p-6 mb-6 flex flex-wrap sm:flex-nowrap sm:items-center gap-4 sm:gap-5">
        <x-avatar :name="$club->name" :src="$club->shield_url" size="lg" class="sm:hidden shrink-0" />
        <x-avatar :name="$club->name" :src="$club->shield_url" size="xl" class="hidden sm:block shrink-0" />
        <div class="min-w-0 flex-1">
            <p class="eyebrow">Equipo</p>
            <h1 class="font-display font-bold text-2xl sm:text-display-s md:text-display-m text-pitch uppercase mt-1 break-words">{{ $club->name }}</h1>
            <p class="font-mono text-[12px] text-ink-mute mt-1">
                Capitán: {{ $club->captain?->name ?? '—' }} · {{ $participations->count() }} participación(es) · {{ $players->count() }} jugadores
                @if ($club->play_level)
                    @php $lvls = ['recreativo'=>'Recreativo','intermedio'=>'Intermedio','competitivo'=>'Competitivo','elite_amateur'=>'Élite Amateur']; @endphp
                    · {{ $lvls[$club->play_level] ?? $club->play_level }}
                @endif
            </p>
        </div>
        <div class="w-full sm:w-auto shrink-0 flex flex-wrap items-center gap-2">
            {{-- FutGO Social: seguir el club para recibir sus novedades en el Feed --}}
            <x-social.follow-button :followable="$club" type="club" />
            {{-- WhatsApp: compartir el perfil del club --}}
            <a href="https://wa.me/?text={{ urlencode($club->name . ' — Perfil del equipo en FutGO: ' . url()->current()) }}"
               target="_blank" rel="noopener"
               class="inline-flex items-center gap-1 bg-[#25D366] text-white font-display font-bold text-[11px] uppercase tracking-wide-label px-2.5 py-1.5 rounded-md hover:opacity-90">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5 shrink-0" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/><path d="M12 0C5.373 0 0 5.373 0 12c0 2.121.552 4.112 1.518 5.845L0 24l6.335-1.518A11.952 11.952 0 0012 24c6.627 0 12-5.373 12-12S18.627 0 12 0zm0 22c-1.896 0-3.67-.502-5.204-1.381l-.374-.222-3.763.902.919-3.667-.243-.386A9.96 9.96 0 012 12C2 6.477 6.477 2 12 2s10 4.477 10 10-4.477 10-10 10z"/></svg>
                WhatsApp
            </a>
            @if ($canManage)
                <x-btn :href="route('torneos.clubes.manage', $club)" variant="primary" size="sm">Gestionar plantilla</x-btn>
            @endif
        </div>
    </div>

    {{-- ── FutGO Social (S3-A): sugerencia de recategorización de nivel ──────── --}}
    @if ($levelSuggestion ?? null)
        @php $lvlSug = ['recreativo'=>'Recreativo','intermedio'=>'Intermedio','competitivo'=>'Competitivo','elite_amateur'=>'Élite Amateur']; @endphp
        <div class="mb-6 bg-amber-50 border border-amber-300 rounded-md p-4 flex flex-wrap items-center gap-3">
            <span class="text-2xl">📈</span>
            <div class="min-w-0 flex-1">
                <p class="font-display font-bold text-amber-900 text-[14px]">¿Subimos de nivel?</p>
                <p class="text-amber-800 text-[13px]">
                    Ganaste {{ $levelSuggestion['wins'] }} amistosos contra equipos de nivel superior siendo
                    <strong>{{ $lvlSug[$levelSuggestion['current_level']] ?? $levelSuggestion['current_level'] }}</strong>.
                    Te sugerimos recategorizar a <strong>{{ $lvlSug[$levelSuggestion['suggested_level']] ?? $levelSuggestion['suggested_level'] }}</strong>.
                    Es solo un aviso — vos decidís.
                </p>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <a href="{{ route('torneos.clubes.manage', $club) }}" class="btn btn-primary btn-sm">Cambiar nivel</a>
                <form method="POST" action="{{ route('torneos.clubes.level-suggestion.dismiss', $club) }}">
                    @csrf
                    <button type="submit" class="btn btn-secondary btn-sm">Ignorar</button>
                </form>
            </div>
        </div>
    @endif

    {{-- Stats acumuladas --}}
    <p class="font-mono text-[11px] tracking-wide-label uppercase text-ink-mute mb-3">Estadísticas acumuladas (todos los torneos)</p>
    <div class="grid grid-cols-3 sm:grid-cols-6 gap-3 mb-8">
        @foreach ([
            ['PJ', $agg['played'], 'pitch'],
            ['PG', $agg['won'], 'gol'],
            ['PE', $agg['drawn'], 'pitch'],
            ['PP', $agg['lost'], 'alerta'],
            ['GF', $agg['goals_for'], 'pitch'],
            ['GC', $agg['goals_against'], 'pitch'],
        ] as [$lbl, $val, $accent])
            <div class="bg-white border border-line rounded-md shadow-card p-3 text-center border-l-4
                {{ $accent === 'gol' ? 'border-l-gol' : ($accent === 'alerta' ? 'border-l-alerta' : 'border-l-pitch') }}">
                <p class="font-mono text-[10px] uppercase tracking-wide-label text-ink-mute">{{ $lbl }}</p>
                <p class="font-display font-extrabold text-2xl mt-0.5
                    {{ $accent === 'gol' ? 'text-gol-deep' : ($accent === 'alerta' ? 'text-alerta' : 'text-pitch') }}">{{ $val }}</p>
            </div>
        @endforeach
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Historial de participaciones --}}
        <section class="bg-white border border-line rounded-md shadow-card-2 overflow-hidden">
            <div class="bg-pitch-mist border-b border-line px-4 py-3">
                <p class="font-mono text-[11px] tracking-wide-label uppercase text-pitch">Historial de participaciones</p>
            </div>
            @if ($participations->isEmpty())
                <div class="p-6 text-center text-ink-soft text-[14px]">Sin participaciones registradas.</div>
            @else
                <ul class="divide-y divide-line-soft">
                    @foreach ($participations as $team)
                        @php [$lbl, $variant] = $statusMeta[$team->tournament->status] ?? [$team->tournament->status, 'default']; @endphp
                        <li class="px-4 py-3 flex items-center justify-between gap-3">
                            <div class="min-w-0">
                                <a href="{{ route('torneos.hub', $team->tournament) }}" class="font-display font-semibold text-pitch text-[14px] hover:underline truncate block">{{ $team->tournament->name }}</a>
                                <p class="font-mono text-[11px] text-ink-mute">Inscripto como {{ $team->name }}</p>
                            </div>
                            <x-badge :variant="$variant">{{ $lbl }}</x-badge>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        {{-- Goleadores históricos --}}
        <section class="bg-white border border-line rounded-md shadow-card-2 overflow-hidden">
            <div class="bg-pitch-mist border-b border-line px-4 py-3">
                <p class="font-mono text-[11px] tracking-wide-label uppercase text-pitch">Goleadores históricos</p>
            </div>
            @if ($topScorers->isEmpty())
                <div class="p-6 text-center text-ink-soft text-[14px]">Sin goles registrados todavía.</div>
            @else
                <ul class="divide-y divide-line-soft">
                    @foreach ($topScorers as $row)
                        <li class="px-4 py-3 flex items-center justify-between">
                            <span class="font-display font-semibold text-pitch text-[14px]">{{ $row['name'] }}</span>
                            <span class="font-display font-extrabold text-gol-deep text-[16px]">{{ $row['goals'] }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>

    {{-- ── FutGO Social (S1-C): Amistosos ───────────────────────────────────── --}}
    <div class="flex items-center justify-between gap-3 mt-8 mb-3">
        <p class="font-mono text-[11px] tracking-wide-label uppercase text-ink-mute">Amistosos</p>
        @if ($canManage)
            <a href="{{ route('social.amistosos.index') }}" class="font-display font-semibold text-[12px] uppercase tracking-wide-label text-pitch hover:underline">Gestionar amistosos →</a>
        @endif
    </div>
    <div class="grid grid-cols-2 sm:grid-cols-5 gap-3 mb-4">
        @foreach ([
            ['PJ', $friendlyMetrics['played'], 'pitch'],
            ['PG', $friendlyMetrics['won'], 'gol'],
            ['PE', $friendlyMetrics['drawn'], 'pitch'],
            ['PP', $friendlyMetrics['lost'], 'alerta'],
            ['Prom. GF', $friendlyMetrics['avg_for'], 'pitch'],
        ] as [$lbl, $val, $accent])
            <div class="bg-white border border-line rounded-md shadow-card p-3 text-center border-l-4
                {{ $accent === 'gol' ? 'border-l-gol' : ($accent === 'alerta' ? 'border-l-alerta' : 'border-l-pitch') }}">
                <p class="font-mono text-[10px] uppercase tracking-wide-label text-ink-mute">{{ $lbl }}</p>
                <p class="font-display font-extrabold text-2xl mt-0.5
                    {{ $accent === 'gol' ? 'text-gol-deep' : ($accent === 'alerta' ? 'text-alerta' : 'text-pitch') }}">{{ $val }}</p>
            </div>
        @endforeach
    </div>
    <section class="bg-white border border-line rounded-md shadow-card-2 overflow-hidden mb-2">
        <div class="bg-pitch-mist border-b border-line px-4 py-3">
            <p class="font-mono text-[11px] tracking-wide-label uppercase text-pitch">Historial de amistosos ({{ $friendlies->count() }})</p>
        </div>
        @if ($friendlies->isEmpty())
            <div class="p-6 text-center text-ink-soft text-[14px]">Sin amistosos jugados todavía.</div>
        @else
            <ul class="divide-y divide-line-soft">
                @foreach ($friendlies as $row)
                    <li class="px-4 py-3 flex items-center justify-between gap-3">
                        <div class="min-w-0">
                            <p class="font-display font-semibold text-pitch text-[14px] truncate">vs {{ $row->opponent?->name ?? '—' }}</p>
                            <p class="font-mono text-[11px] text-ink-mute">{{ $row->date?->format('d/m/Y') ?? 'Sin fecha' }}</p>
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <span class="font-display font-extrabold text-pitch text-[15px]">{{ $row->for }}–{{ $row->against }}</span>
                            <span class="px-2 py-0.5 rounded-pill font-display font-bold text-[10px] uppercase
                                {{ $row->outcome === 'V' ? 'bg-gol/20 text-pitch-deep' : ($row->outcome === 'D' ? 'bg-alerta/15 text-alerta-deep' : 'bg-bone-soft text-ink-mute') }}">
                                {{ $row->outcome === 'V' ? 'G' : ($row->outcome === 'D' ? 'P' : 'E') }}
                            </span>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    {{-- ── FutGO Social (S3-A): historial de compatibilidad (head-to-head) ───── --}}
    @if ($headToHead->isNotEmpty())
        <section class="bg-white border border-line rounded-md shadow-card-2 overflow-hidden mt-6">
            <div class="bg-pitch-mist border-b border-line px-4 py-3">
                <p class="font-mono text-[11px] tracking-wide-label uppercase text-pitch">Ya jugaron antes ({{ $headToHead->count() }} {{ $headToHead->count() === 1 ? 'rival' : 'rivales' }})</p>
            </div>
            <ul class="divide-y divide-line-soft">
                @foreach ($headToHead as $h)
                    <li class="px-4 py-3 flex items-center justify-between gap-3">
                        <div class="min-w-0 flex items-center gap-2">
                            <x-avatar :name="$h->opponent->name" :src="$h->opponent->shield_url" size="sm" />
                            <a href="{{ route('torneos.clubes.show', $h->opponent) }}" class="font-display font-semibold text-pitch text-[14px] truncate hover:underline">{{ $h->opponent->name }}</a>
                        </div>
                        <div class="flex items-center gap-3 shrink-0">
                            <span class="font-mono text-[11px] text-ink-mute">{{ $h->won }}G · {{ $h->drawn }}E · {{ $h->lost }}P</span>
                            <span class="font-display font-bold text-pitch text-[13px]">{{ $h->count }} {{ $h->count === 1 ? 'vez' : 'veces' }}</span>
                        </div>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    {{-- Jugadores históricos --}}
    <section class="bg-white border border-line rounded-md shadow-card-2 overflow-hidden mt-6">
        <div class="bg-pitch-mist border-b border-line px-4 py-3">
            <p class="font-mono text-[11px] tracking-wide-label uppercase text-pitch">Jugadores históricos ({{ $players->count() }})</p>
        </div>
        @if ($players->isEmpty())
            <div class="p-6 text-center text-ink-soft text-[14px]">Sin jugadores registrados.</div>
        @else
            <ul class="divide-y divide-line-soft">
                @foreach ($players as $tp)
                    <li class="px-4 py-3 flex items-center gap-3">
                        <x-avatar :user="$tp->user" :name="$tp->displayName()" size="sm" />
                        <div class="min-w-0">
                            @if ($tp->user?->futgo_id)
                                <a href="{{ route('social.player.show', $tp->user->futgo_id) }}" class="font-display font-semibold text-pitch text-[14px] truncate hover:underline block">{{ $tp->displayName() }}</a>
                            @else
                                <p class="font-display font-semibold text-pitch text-[14px] truncate">{{ $tp->displayName() }}</p>
                            @endif
                            <p class="font-mono text-[11px] text-ink-mute">{{ $tp->position ?? 'Sin posición' }}</p>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    {{-- ── FutGO Social (S1-F): Score de confiabilidad + Oportunidades abiertas --}}

    {{-- Score de confiabilidad: para el dueño siempre; público solo si ≥ 80 --}}
    @if ($reliabilityForOwner ?? null)
        <div class="mt-6 p-4 {{ ($reliabilityForOwner->score >= 80) ? 'bg-gol/10 border-gol/30' : 'bg-white border-line' }} border rounded-md shadow-card flex items-center gap-3">
            <span class="font-display font-bold text-2xl {{ ($reliabilityForOwner->score >= 80) ? 'text-gol-deep' : 'text-pitch' }}">{{ $reliabilityForOwner->score }}</span>
            <div>
                <p class="font-display font-semibold text-pitch text-[14px]">Score de confiabilidad del equipo <span class="text-[11px] font-mono text-ink-mute">(visible solo para vos)</span></p>
                @if ($reliabilityForOwner->is_paused)
                    <p class="text-alerta text-[12px]">Equipo pausado por no-shows acumulados.</p>
                @else
                    <p class="text-ink-soft text-[12px]">Basado en asistencia en amistosos los últimos 90 días.</p>
                @endif
            </div>
        </div>
    @elseif ($reliabilityScore ?? null)
        <div class="mt-6 p-4 bg-gol/10 border border-gol/30 rounded-md shadow-card flex items-center gap-3">
            <span class="font-display font-bold text-2xl text-gol-deep">{{ $reliabilityScore->score }}</span>
            <div>
                <p class="font-display font-semibold text-pitch text-[14px]">Score de confiabilidad</p>
                <p class="text-ink-soft text-[12px]">Basado en asistencia en amistosos los últimos 90 días.</p>
            </div>
        </div>
    @endif

    {{-- Oportunidades abiertas del club --}}
    @if ($openOpportunities->isNotEmpty())
        <p class="font-mono text-[11px] tracking-wide-label uppercase text-ink-mute mt-6 mb-3">Busca actualmente ({{ $openOpportunities->count() }})</p>
        <div class="flex flex-col gap-2">
            @foreach ($openOpportunities as $op)
                <a href="{{ route('social.oportunidades.show', $op) }}"
                   class="bg-white border border-line rounded-md shadow-card p-4 hover:shadow-card-2 transition-shadow flex items-center justify-between gap-3">
                    <div>
                        <p class="font-display font-semibold text-pitch text-[14px]">{{ $op->typeLabel() }}</p>
                        <p class="text-ink-soft text-[12px] mt-0.5">{{ $op->city }}@if($op->required_level) · {{ $op->required_level }}@endif</p>
                    </div>
                    <span class="font-mono text-[11px] text-gol uppercase tracking-wide-label shrink-0">Abierta</span>
                </a>
            @endforeach
        </div>
    @endif

</div>
@endsection
