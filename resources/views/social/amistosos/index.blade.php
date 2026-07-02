@extends('layouts.app')
@section('title', 'Mis amistosos')

@php
    // Lado propio del capitán en cada amistoso (puede capitanear local o visitante).
    $mySide = fn ($m) => in_array($m->home_club_id, $myClubIds, true) ? 'home' : 'away';
@endphp

@section('content')
<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

    <div class="flex flex-wrap items-end justify-between gap-4 mb-6">
        <div>
            <p class="eyebrow">FutGO Social</p>
            <h1 class="font-display font-bold text-display-s text-pitch uppercase mt-1">Mis amistosos</h1>
            <p class="text-ink-soft text-[14px] mt-1">Carga el resultado de tus amistosos. El rival también carga el suyo: si coinciden, queda confirmado.</p>
        </div>
        <a href="{{ route('social.oportunidades.index', ['tipo' => 'BUSCAR_RIVAL']) }}" class="btn btn-secondary btn-sm">Buscar rival</a>
    </div>

    @if (session('status'))
        <div class="mb-4 bg-gol/20 border border-gol text-pitch-deep px-4 py-3 rounded-md font-display font-semibold">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-4 bg-alerta/15 border border-alerta text-alerta-deep px-4 py-3 rounded-md font-display font-semibold">{{ session('error') }}</div>
    @endif

    @if (empty($myClubIds))
        <div class="bg-white border border-line rounded-md shadow-card p-8 text-center text-ink-soft">
            Necesitas capitanear un equipo para tener amistosos.
        </div>
    @endif

    {{-- ── En disputa (notificación de desacuerdo) ─────────────────────── --}}
    @if ($enDisputa->isNotEmpty())
        <section class="mb-8">
            <p class="font-mono text-[11px] tracking-wide-label uppercase text-alerta mb-3 inline-flex items-center gap-1.5"><x-icon name="warning" class="w-3.5 h-3.5" /> En disputa ({{ $enDisputa->count() }})</p>
            <div class="flex flex-col gap-3">
                @foreach ($enDisputa as $m)
                    @php $side = $mySide($m); @endphp
                    <div class="bg-white border-2 border-alerta/40 rounded-md shadow-card p-4">
                        <div class="flex items-center justify-between gap-2 mb-2">
                            <p class="font-display font-bold text-pitch text-[15px]">{{ $m->homeClub?->name }} vs {{ $m->awayClub?->name }}</p>
                            <span class="font-mono text-[11px] text-ink-mute">{{ $m->scheduled_at?->format('d/m/Y H:i') ?? 'Sin fecha' }}</span>
                        </div>
                        <p class="text-[13px] text-alerta-deep mb-3">
                            Los marcadores cargados no coinciden:
                            <strong>local dice {{ $m->home_reported_home_score }}–{{ $m->home_reported_away_score }}</strong>,
                            <strong>visitante dice {{ $m->away_reported_home_score }}–{{ $m->away_reported_away_score }}</strong>.
                            Rectifica tu marcador para que coincida, o escala a un admin.
                        </p>
                        @if ($m->isEscalada())
                            <p class="font-mono text-[11px] text-ink-mute mb-2">Ya escalado a un admin de la plataforma.</p>
                        @endif
                        <div class="flex flex-wrap items-end gap-3">
                            <form method="POST" action="{{ route('social.amistosos.report', $m) }}" class="flex items-end gap-2">
                                @csrf
                                <div class="flex items-center gap-2">
                                    <span class="font-mono text-[11px] text-ink-mute">{{ $m->homeClub?->name }}</span>
                                    <input type="number" name="home_score" min="0" max="99" required value="{{ $m->home_reported_home_score }}"
                                           class="w-14 h-[38px] px-2 text-center bg-white border-[1.5px] border-line rounded-md text-[14px]">
                                    <span class="font-mono text-ink-mute">–</span>
                                    <input type="number" name="away_score" min="0" max="99" required value="{{ $m->home_reported_away_score }}"
                                           class="w-14 h-[38px] px-2 text-center bg-white border-[1.5px] border-line rounded-md text-[14px]">
                                    <span class="font-mono text-[11px] text-ink-mute">{{ $m->awayClub?->name }}</span>
                                </div>
                                <button type="submit" class="btn btn-primary btn-sm">Rectificar</button>
                            </form>
                            @unless ($m->isEscalada())
                                <form method="POST" action="{{ route('social.amistosos.escalate', $m) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-secondary btn-sm">Escalar a admin</button>
                                </form>
                            @endunless
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    {{-- ── Confirmados (pendientes de resultado) ───────────────────────── --}}
    <section class="mb-8">
        <p class="font-mono text-[11px] tracking-wide-label uppercase text-ink-mute mb-3">Confirmados ({{ $pendientes->count() }})</p>
        @if ($pendientes->isEmpty())
            <div class="bg-white border border-line rounded-md shadow-card p-6 text-center text-ink-soft text-[14px]">Sin amistosos pendientes de resultado.</div>
        @else
            <div class="flex flex-col gap-3">
                @foreach ($pendientes as $m)
                    @php $side = $mySide($m); $iReported = $m->sideHasReported($side); $rivalReported = $m->sideHasReported($side === 'home' ? 'away' : 'home'); @endphp
                    <div class="bg-white border border-line rounded-md shadow-card p-4">
                        <div class="flex items-center justify-between gap-2 mb-3">
                            <p class="font-display font-bold text-pitch text-[15px]">{{ $m->homeClub?->name }} vs {{ $m->awayClub?->name }}</p>
                            <span class="font-mono text-[11px] text-ink-mute">{{ $m->scheduled_at?->format('d/m/Y H:i') ?? 'Sin fecha' }}</span>
                        </div>
                        @if ($m->venue)
                            <p class="text-[12px] text-ink-mute mb-2 inline-flex items-center gap-1">
                                <x-icon name="map-pin" class="w-3.5 h-3.5" /> <a href="{{ route('social.canchas.show', $m->venue->slug) }}" class="hover:underline text-pitch/70">{{ $m->venue->name }}</a>
                            </p>
                        @elseif ($m->location)
                            <p class="text-[12px] text-ink-mute mb-2 inline-flex items-center gap-1"><x-icon name="map-pin" class="w-3.5 h-3.5" /> {{ $m->location }}</p>
                        @endif
                        @if ($rivalReported && ! $iReported)
                            <p class="font-mono text-[11px] text-gol-deep mb-2">El rival ya cargó su marcador. Carga el tuyo para confirmar.</p>
                        @elseif ($iReported && ! $rivalReported)
                            <p class="font-mono text-[11px] text-ink-mute mb-2">Cargaste tu marcador. Esperando que el rival cargue el suyo.</p>
                        @endif
                        <div class="flex flex-wrap items-end gap-3">
                            <form method="POST" action="{{ route('social.amistosos.report', $m) }}" class="flex items-end gap-2">
                                @csrf
                                <div class="flex items-center gap-2">
                                    <span class="font-mono text-[11px] text-ink-mute">{{ $m->homeClub?->name }}</span>
                                    <input type="number" name="home_score" min="0" max="99" required
                                           value="{{ $side === 'home' ? $m->home_reported_home_score : $m->away_reported_home_score }}"
                                           class="w-14 h-[38px] px-2 text-center bg-white border-[1.5px] border-line rounded-md text-[14px]">
                                    <span class="font-mono text-ink-mute">–</span>
                                    <input type="number" name="away_score" min="0" max="99" required
                                           value="{{ $side === 'home' ? $m->home_reported_away_score : $m->away_reported_away_score }}"
                                           class="w-14 h-[38px] px-2 text-center bg-white border-[1.5px] border-line rounded-md text-[14px]">
                                    <span class="font-mono text-[11px] text-ink-mute">{{ $m->awayClub?->name }}</span>
                                </div>
                                <button type="submit" class="btn btn-primary btn-sm">Cargar resultado</button>
                            </form>
                            <form method="POST" action="{{ route('social.amistosos.cancel', $m) }}"
                                  onsubmit="return confirm('¿Cancelar este amistoso? Si faltan menos de 24h afecta tu confiabilidad.')"
                                  class="flex items-end gap-2">
                                @csrf
                                <input type="text" name="reason" required maxlength="255" placeholder="Motivo"
                                       class="h-[38px] px-3 bg-white border-[1.5px] border-line rounded-md text-[13px]">
                                <button type="submit" class="btn btn-danger btn-sm">Cancelar</button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </section>

    {{-- ── Jugados ──────────────────────────────────────────────────────── --}}
    <section>
        <p class="font-mono text-[11px] tracking-wide-label uppercase text-ink-mute mb-3">Jugados ({{ $jugados->count() }})</p>
        @if ($jugados->isEmpty())
            <div class="bg-white border border-line rounded-md shadow-card p-6 text-center text-ink-soft text-[14px]">Todavía no jugaste amistosos.</div>
        @else
            <div class="flex flex-col gap-2">
                @foreach ($jugados as $m)
                    @php $side = $mySide($m); $r = $m->resultForClub($side === 'home' ? $m->home_club_id : $m->away_club_id); @endphp
                    <div class="bg-white border border-line rounded-md shadow-card p-4 flex items-center justify-between gap-3">
                        <div class="min-w-0">
                            <p class="font-display font-semibold text-pitch text-[14px]">{{ $m->homeClub?->name }} vs {{ $m->awayClub?->name }}</p>
                            <p class="font-mono text-[11px] text-ink-mute">{{ $m->scheduled_at?->format('d/m/Y') ?? '—' }}{{ $m->fueResueltoPorAdmin() ? ' · resuelto por admin' : '' }}{{ $m->venue ? ' · ' . $m->venue->name : ($m->location ? ' · ' . $m->location : '') }}</p>
                        </div>
                        <div class="flex items-center gap-3 shrink-0">
                            <span class="font-display font-extrabold text-pitch text-[16px]">{{ $m->final_home_score }}–{{ $m->final_away_score }}</span>
                            @if ($r)
                                <span class="px-2 py-0.5 rounded-pill font-display font-bold text-[10px] uppercase
                                    {{ $r['outcome'] === 'V' ? 'bg-gol/20 text-pitch-deep' : ($r['outcome'] === 'D' ? 'bg-alerta/15 text-alerta-deep' : 'bg-bone-soft text-ink-mute') }}">
                                    {{ $r['outcome'] === 'V' ? 'Ganado' : ($r['outcome'] === 'D' ? 'Perdido' : 'Empate') }}
                                </span>
                            @endif
                            {{-- Compartir resultado --}}
                            <div x-data="{ open: false }" class="relative">
                                <button type="button" @click="open=!open" class="font-mono text-[11px] text-gol-deep font-bold uppercase tracking-wide-label hover:underline"><x-icon name="photo" class="w-4 h-4" /></button>
                                <div x-show="open" x-cloak @click.outside="open=false" class="absolute right-0 z-10 mt-1 bg-white border border-line rounded-md shadow-card-2 py-1 w-44 text-left">
                                    <a href="{{ route('social.amistosos.img.card', $m) }}" target="_blank" class="block px-3 py-2 text-[13px] text-ink hover:bg-bone-soft">Ver tarjeta SVG</a>
                                    <a href="{{ route('social.amistosos.img.png', $m) }}" download class="block px-3 py-2 text-[13px] text-ink hover:bg-bone-soft">Descargar PNG</a>
                                    <a href="https://wa.me/?text={{ urlencode($m->homeClub?->name . ' ' . ($m->final_home_score ?? 0) . '–' . ($m->final_away_score ?? 0) . ' ' . $m->awayClub?->name . ' · Amistoso FutGO') }}"
                                       target="_blank" rel="noopener" class="block px-3 py-2 text-[13px] text-[#25D366] font-semibold hover:bg-bone-soft">Compartir en WhatsApp</a>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </section>
</div>
@endsection
