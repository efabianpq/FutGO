@extends('layouts.app')
@section('title', 'Inicio')

@section('content')
@php
    use App\Services\Social\SportsAgendaService;
    use App\Models\Social\FeedEvent;

    $firstName = \Illuminate\Support\Str::of($user->name)->trim()->before(' ');

    $dayLabel = function (string $key) {
        $d = \Illuminate\Support\Carbon::parse($key);
        $today = now()->startOfDay();
        if ($d->isSameDay($today)) return 'Hoy';
        if ($d->isSameDay($today->copy()->addDay())) return 'Mañana';
        return ucfirst($d->translatedFormat('l d M'));
    };

    // Resumen compacto de un evento del Feed (versión liviana de feed/index).
    $feedSummary = function (FeedEvent $e) {
        return match ($e->type) {
            FeedEvent::TYPE_OPORTUNIDAD_PUBLICADA => ($e->meta('author', 'Alguien')) . ' publicó: ' . $e->meta('type_label') . ' en ' . $e->meta('city'),
            FeedEvent::TYPE_OPORTUNIDAD_ACEPTADA  => ($e->meta('owner', 'Alguien')) . ' aceptó a ' . $e->meta('responder', 'un equipo'),
            FeedEvent::TYPE_AMISTOSO_CONFIRMADO   => 'Amistoso: ' . $e->meta('home') . ' vs ' . $e->meta('away'),
            FeedEvent::TYPE_RESULTADO_AMISTOSO    => $e->meta('home') . ' ' . $e->meta('home_score') . '–' . $e->meta('away_score') . ' ' . $e->meta('away'),
            FeedEvent::TYPE_RESULTADO_TORNEO      => $e->meta('home') . ' ' . $e->meta('home_score') . '–' . $e->meta('away_score') . ' ' . $e->meta('away'),
            FeedEvent::TYPE_LOGRO_DESBLOQUEADO    => ($e->meta('player', 'Un jugador')) . ' desbloqueó ' . $e->meta('achievement_name', 'un logro'),
            default => $e->typeLabel(),
        };
    };

    $feedEmoji = [
        FeedEvent::TYPE_OPORTUNIDAD_PUBLICADA => '📣',
        FeedEvent::TYPE_OPORTUNIDAD_ACEPTADA  => '🤝',
        FeedEvent::TYPE_AMISTOSO_CONFIRMADO   => '📅',
        FeedEvent::TYPE_RESULTADO_AMISTOSO    => '⚽',
        FeedEvent::TYPE_RESULTADO_TORNEO      => '🏆',
        FeedEvent::TYPE_LOGRO_DESBLOQUEADO    => '🏅',
    ];
@endphp

<div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

    {{-- ── Saludo + acciones rápidas ── --}}
    <div class="flex flex-wrap items-end justify-between gap-4 mb-6">
        <div>
            <p class="font-mono text-[11px] tracking-[.14em] uppercase text-muted">{{ now()->translatedFormat('l d \d\e F') }}</p>
            <h1 class="font-display font-bold text-2xl sm:text-3xl text-text mt-1">Hola, {{ $firstName }} 👋</h1>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('torneos.mi-carrera') }}" class="btn btn-primary btn-sm">Mi Carrera</a>
        </div>
    </div>

    {{-- ── Recordatorios (acción inmediata) ── --}}
    @if ($reminders->isNotEmpty())
        <div class="mb-6 space-y-2">
            @foreach ($reminders as $item)
                <div class="flex flex-wrap items-center gap-3 bg-green-tint border border-green/40 rounded-md px-4 py-3">
                    <span class="text-lg leading-none shrink-0">{{ $item->status === 'convocatoria_pendiente' ? '📣' : '⚽' }}</span>
                    <div class="min-w-0 flex-1">
                        <p class="font-display font-semibold text-text text-[14px] truncate">{{ $item->title }}</p>
                        <p class="font-mono text-[11px] text-muted truncate">
                            {{ $item->status === 'convocatoria_pendiente' ? 'Te convocaron — confirma tu asistencia' : 'Ya se jugó — carga el resultado' }}
                        </p>
                    </div>
                    @if ($item->status === 'convocatoria_pendiente')
                        <div class="flex items-center gap-2 shrink-0">
                            <form method="POST" action="{{ route('torneos.convocatoria.respond', [$item->tournament, $item->match]) }}">
                                @csrf <input type="hidden" name="response" value="confirmado">
                                <button type="submit" class="btn btn-primary btn-sm">Confirmar</button>
                            </form>
                            <form method="POST" action="{{ route('torneos.convocatoria.respond', [$item->tournament, $item->match]) }}">
                                @csrf <input type="hidden" name="response" value="declinado">
                                <button type="submit" class="text-[12px] font-semibold text-alerta hover:underline">Declinar</button>
                            </form>
                        </div>
                    @else
                        <a href="{{ route('social.amistosos.index') }}" class="btn btn-secondary btn-sm shrink-0">Cargar resultado</a>
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    <div class="grid lg:grid-cols-3 gap-6">

        {{-- ── Columna principal: Tu semana ── --}}
        <div class="lg:col-span-2">
            <div class="flex items-center justify-between mb-3">
                <h2 class="font-display font-bold text-text text-[16px]">Tu semana</h2>
                <a href="{{ route('social.agenda.index') }}" class="font-mono text-[12px] text-muted hover:text-text">Ver agenda completa →</a>
            </div>

            @if ($grouped->isEmpty())
                <div class="bg-surface border border-border rounded-md p-8 text-center">
                    <p class="text-3xl mb-2">📭</p>
                    <p class="font-display font-semibold text-text text-[15px]">No tienes nada programado próximamente.</p>
                    <p class="text-muted text-[13px] mt-1">Cuando te convoquen, confirmes un amistoso o publiques una oportunidad, aparece acá.</p>
                    <div class="mt-4 flex justify-center gap-2">
                        <a href="{{ route('social.oportunidades.index') }}" class="btn btn-primary btn-sm">Ver oportunidades</a>
                        <a href="{{ route('social.oportunidades.express') }}" class="btn btn-secondary btn-sm">⚡ Buscar rival ya</a>
                    </div>
                </div>
            @else
                <div class="flex flex-col gap-4">
                    @foreach ($grouped as $dayKey => $items)
                        <section>
                            <p class="font-mono text-[11px] tracking-wide-label uppercase text-muted mb-2 pl-1">{{ $dayLabel($dayKey) }} · {{ \Illuminate\Support\Carbon::parse($dayKey)->translatedFormat('d M') }}</p>
                            <div class="bg-surface border border-border rounded-md overflow-hidden divide-y divide-border">
                                @foreach ($items as $item)
                                    @php
                                        $icon = match ($item->kind) {
                                            SportsAgendaService::KIND_TOURNAMENT_MATCH => '🏆',
                                            SportsAgendaService::KIND_FRIENDLY          => '🤝',
                                            SportsAgendaService::KIND_OPPORTUNITY       => '📣',
                                            default                                     => '•',
                                        };
                                    @endphp
                                    <div class="px-4 py-3 flex items-start gap-3">
                                        <span class="text-lg leading-none mt-0.5 shrink-0">{{ $icon }}</span>
                                        <div class="min-w-0 flex-1">
                                            <p class="font-display font-semibold text-text text-[14px] truncate">{{ $item->title }}</p>
                                            <p class="font-mono text-[11px] text-muted truncate">
                                                @if ($item->date){{ $item->date->format('H:i') }} · @endif{{ $item->subtitle }}
                                            </p>
                                        </div>
                                        @if ($item->kind === SportsAgendaService::KIND_TOURNAMENT_MATCH)
                                            @if ($item->status === 'confirmado')
                                                <x-badge variant="win">Confirmado</x-badge>
                                            @elseif ($item->match?->status === 'live')
                                                <x-badge variant="live">En vivo</x-badge>
                                            @else
                                                <x-badge variant="upcoming">Programado</x-badge>
                                            @endif
                                        @elseif ($item->kind === SportsAgendaService::KIND_FRIENDLY)
                                            <x-badge variant="win">Amistoso</x-badge>
                                        @elseif ($item->kind === SportsAgendaService::KIND_OPPORTUNITY)
                                            <a href="{{ route('social.oportunidades.show', $item->opportunity) }}" class="font-display font-bold text-[12px] uppercase text-green hover:underline shrink-0">Ver →</a>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </section>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- ── Columna lateral: Sugeridas + Novedades ── --}}
        <div class="space-y-6">

            {{-- Sugeridas (oportunidades de tu ciudad) --}}
            <div>
                <div class="flex items-center justify-between mb-3">
                    <h2 class="font-display font-bold text-text text-[16px]">Sugeridas para ti</h2>
                    <a href="{{ route('social.oportunidades.index') }}" class="font-mono text-[12px] text-muted hover:text-text">Más →</a>
                </div>

                @if ($suggested->isNotEmpty())
                    <div class="space-y-2">
                        @foreach ($suggested as $op)
                            <a href="{{ route('social.oportunidades.show', $op) }}"
                               class="block bg-surface border border-border rounded-md p-3 hover:border-green transition-colors {{ $op->isExpress() ? 'ring-1 ring-alerta/30 border-alerta/50' : '' }}">
                                <p class="font-mono text-[10px] tracking-wide-label uppercase text-green">
                                    {{ $op->isExpress() ? '⚡ Urgente · ' : '' }}{{ $op->typeLabel() }}
                                </p>
                                <p class="font-display font-semibold text-text text-[14px] mt-0.5 truncate">{{ $op->authorName() }}</p>
                                <p class="font-mono text-[11px] text-muted truncate">{{ $op->city }}</p>
                            </a>
                        @endforeach
                    </div>
                @else
                    <div class="bg-surface border border-border rounded-md p-4 text-center">
                        <p class="text-muted text-[13px]">
                            @if ($user->city)
                                No hay oportunidades activas en {{ $user->city }} por ahora.
                            @else
                                Agrega tu ciudad en el perfil para ver oportunidades cerca de ti.
                            @endif
                        </p>
                        @unless ($user->city)
                            <a href="{{ route('profile.show') }}" class="btn btn-secondary btn-sm mt-3">Completar perfil</a>
                        @endunless
                    </div>
                @endif
            </div>

            {{-- Novedades (preview del Feed) --}}
            <div>
                <div class="flex items-center justify-between mb-3">
                    <h2 class="font-display font-bold text-text text-[16px]">Novedades</h2>
                    <a href="{{ route('social.feed.index') }}" class="font-mono text-[12px] text-muted hover:text-text">Ver todo →</a>
                </div>

                @if ($feedPreview->isNotEmpty())
                    <div class="bg-surface border border-border rounded-md overflow-hidden divide-y divide-border">
                        @foreach ($feedPreview as $event)
                            <div class="px-4 py-3 flex items-start gap-3">
                                <span class="text-base leading-none mt-0.5 shrink-0">{{ ($event->type === FeedEvent::TYPE_OPORTUNIDAD_PUBLICADA && $event->meta('express')) ? '⚡' : ($feedEmoji[$event->type] ?? '•') }}</span>
                                <div class="min-w-0 flex-1">
                                    <p class="text-text text-[13px] leading-snug">{{ $feedSummary($event) }}</p>
                                    <p class="font-mono text-[10px] text-muted mt-0.5">{{ $event->occurred_at?->diffForHumans() }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="bg-surface border border-border rounded-md p-4 text-center">
                        <p class="text-muted text-[13px]">Sigue equipos, jugadores y torneos para llenar tu feed de novedades.</p>
                    </div>
                @endif
            </div>

        </div>
    </div>
</div>
@endsection
