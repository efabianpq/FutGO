@extends('layouts.app')
@section('title', 'Feed')

@php
    use App\Models\Social\FeedEvent;

    // Ícono + color por tipo de evento (presentación liviana, sin lógica de negocio).
    $meta = [
        FeedEvent::TYPE_OPORTUNIDAD_PUBLICADA => ['emoji' => '📣', 'accent' => 'text-pitch'],
        FeedEvent::TYPE_OPORTUNIDAD_ACEPTADA  => ['emoji' => '🤝', 'accent' => 'text-gol-deep'],
        FeedEvent::TYPE_AMISTOSO_CONFIRMADO   => ['emoji' => '📅', 'accent' => 'text-pitch'],
        FeedEvent::TYPE_RESULTADO_AMISTOSO    => ['emoji' => '⚽', 'accent' => 'text-gol-deep'],
        FeedEvent::TYPE_RESULTADO_TORNEO      => ['emoji' => '🏆', 'accent' => 'text-pitch'],
        FeedEvent::TYPE_LOGRO_DESBLOQUEADO    => ['emoji' => '🏅', 'accent' => 'text-gol-deep'],
    ];
@endphp

@section('content')
<div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

    <div class="flex flex-wrap items-end justify-between gap-4 mb-6">
        <div>
            <p class="eyebrow">FutGO Social</p>
            <h1 class="font-display font-bold text-display-s text-pitch uppercase mt-1">Tu Feed</h1>
            <p class="text-ink-soft text-[14px] mt-1">Lo que pasa con los equipos, jugadores y torneos que seguís — y las oportunidades de tu ciudad.</p>
        </div>
        <a href="{{ route('social.oportunidades.index') }}" class="btn btn-secondary btn-sm">Ver oportunidades</a>
    </div>

    @if (session('status'))
        <div class="mb-4 bg-gol/20 border border-gol text-pitch-deep px-4 py-3 rounded-md font-display font-semibold">{{ session('status') }}</div>
    @endif

    @forelse ($events as $event)
        @php
            $m = $meta[$event->type] ?? ['emoji' => '•', 'accent' => 'text-ink'];
            $isExpress = $event->type === FeedEvent::TYPE_OPORTUNIDAD_PUBLICADA && $event->meta('express');
        @endphp
        <article class="bg-white border rounded-md shadow-card p-4 mb-3 flex gap-3 {{ $isExpress ? 'border-alerta ring-1 ring-alerta/30' : 'border-line' }}">
            <div class="text-2xl shrink-0 leading-none">{{ $isExpress ? '⚡' : $m['emoji'] }}</div>
            <div class="min-w-0 flex-1">
                <p class="font-mono text-[11px] tracking-wide-label uppercase {{ $m['accent'] }}">
                    {{ $event->typeLabel() }}
                    @if ($isExpress)
                        <span class="ml-1 px-1.5 py-0.5 rounded-pill text-[9px] bg-alerta text-white">URGENTE</span>
                    @endif
                </p>
                <p class="font-display font-semibold text-ink mt-0.5">
                    @switch($event->type)
                        @case(FeedEvent::TYPE_OPORTUNIDAD_PUBLICADA)
                            {{ $event->meta('author', 'Alguien') }} publicó: {{ $event->meta('type_label') }} en {{ $event->meta('city') }}
                            @break
                        @case(FeedEvent::TYPE_OPORTUNIDAD_ACEPTADA)
                            {{ $event->meta('owner', 'Alguien') }} aceptó a {{ $event->meta('responder', 'un equipo') }} ({{ $event->meta('type_label') }})
                            @break
                        @case(FeedEvent::TYPE_AMISTOSO_CONFIRMADO)
                            Amistoso confirmado: {{ $event->meta('home') }} vs {{ $event->meta('away') }}
                            @break
                        @case(FeedEvent::TYPE_RESULTADO_AMISTOSO)
                            {{ $event->meta('home') }} {{ $event->meta('home_score') }} – {{ $event->meta('away_score') }} {{ $event->meta('away') }}
                            @break
                        @case(FeedEvent::TYPE_RESULTADO_TORNEO)
                            {{ $event->meta('home') }} {{ $event->meta('home_score') }} – {{ $event->meta('away_score') }} {{ $event->meta('away') }}
                            <span class="text-ink-soft font-normal">· {{ $event->meta('tournament_name') }}</span>
                            @break
                        @case(FeedEvent::TYPE_LOGRO_DESBLOQUEADO)
                            {{ $event->meta('player', 'Un jugador') }} desbloqueó {{ $event->meta('achievement_name', 'un logro') }}
                            @break
                        @default
                            {{ $event->typeLabel() }}
                    @endswitch
                </p>
                <p class="text-[12px] text-ink-soft mt-1">{{ $event->occurred_at?->diffForHumans() }}</p>

                @if ($event->type === FeedEvent::TYPE_OPORTUNIDAD_PUBLICADA && $event->meta('opportunity_id'))
                    <a href="{{ route('social.oportunidades.show', $event->meta('opportunity_id')) }}" class="text-[13px] font-semibold text-pitch hover:underline mt-1 inline-block">Ver oportunidad →</a>
                @endif
            </div>
        </article>
    @empty
        {{-- Feed vacío: contenido de entrada (oportunidades de tu ciudad). --}}
        <div class="bg-white border border-line rounded-md shadow-card p-6 mb-6 text-center">
            <p class="text-3xl mb-2">📭</p>
            <p class="font-display font-semibold text-ink">Tu Feed todavía está tranquilo</p>
            <p class="text-ink-soft text-[14px] mt-1">
                @if ($hasCity)
                    Seguí equipos, jugadores y torneos para llenarlo. Mientras tanto, mirá lo que se mueve en tu ciudad:
                @else
                    Agregá tu ciudad en el perfil y seguí equipos o torneos para recibir novedades.
                @endif
            </p>
        </div>

        @if ($entryOpportunities->isNotEmpty())
            <p class="font-mono text-[11px] tracking-wide-label uppercase text-pitch mb-3">Oportunidades en tu ciudad</p>
            @foreach ($entryOpportunities as $op)
                <a href="{{ route('social.oportunidades.show', $op) }}" class="block bg-white border border-line rounded-md shadow-card p-4 mb-3 hover:border-pitch transition-colors">
                    <p class="font-mono text-[11px] tracking-wide-label uppercase text-pitch">{{ $op->typeLabel() }}</p>
                    <p class="font-display font-semibold text-ink mt-0.5">{{ $op->authorName() }} · {{ $op->city }}</p>
                </a>
            @endforeach
        @endif
    @endforelse

    @if ($events->hasPages())
        <div class="mt-6">{{ $events->links() }}</div>
    @endif
</div>
@endsection
