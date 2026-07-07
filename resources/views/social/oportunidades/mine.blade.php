@extends('layouts.app')
@section('title', 'Mis oportunidades')

@php
    $statusBadge = [
        'abierta' => 'bg-gol/20 text-pitch-deep', 'en_negociacion' => 'bg-amber-100 text-amber-800',
        'cerrada' => 'bg-bone-soft text-ink-mute', 'vencida' => 'bg-bone-soft text-ink-mute',
        'cancelada' => 'bg-alerta/15 text-alerta-deep',
    ];
    $respStatusLabel = [
        'pendiente' => 'Pendiente', 'aceptada' => 'Aceptada',
        'rechazada' => 'Rechazada', 'contrapropuesta' => 'Contrapropuesta',
    ];
@endphp

@section('content')
<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

    <div class="flex flex-wrap items-end justify-between gap-4 mb-6">
        <div>
            <p class="eyebrow">FutGO Social</p>
            <div class="flex items-center gap-2 mt-1">
                <h1 class="font-display font-bold text-display-s text-pitch uppercase">Mis oportunidades</h1>
                <x-help-hint topic="social.oportunidades.mine" />
            </div>
        </div>
        <a href="{{ route('social.oportunidades.create') }}" class="btn btn-primary btn-sm">+ Publicar</a>
    </div>

    @if (session('status'))
        <div class="mb-4 bg-gol/20 border border-gol text-pitch-deep px-4 py-3 rounded-md font-display font-semibold">{{ session('status') }}</div>
    @endif

    {{-- Publicadas --}}
    <p class="font-mono text-[11px] tracking-wide-label uppercase text-ink-mute mb-3">Publicadas ({{ $published->count() }})</p>
    @if ($published->isEmpty())
        <div class="bg-white border border-line rounded-md shadow-card p-6 text-center text-ink-soft text-[14px] mb-8">Todavía no publicaste ninguna oportunidad.</div>
    @else
        <div class="flex flex-col gap-3 mb-8">
            @foreach ($published as $op)
                <a href="{{ route('social.oportunidades.show', $op) }}" class="bg-white border border-line rounded-md shadow-card p-4 flex items-center justify-between gap-3 hover:border-pitch transition-all duration-fast">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <span class="font-display font-bold text-pitch text-[14px]">{{ $op->typeLabel() }}</span>
                            <span class="px-2 py-0.5 rounded-pill font-display font-bold text-[9.5px] uppercase tracking-wide-label {{ $statusBadge[$op->status] ?? '' }}">{{ $op->statusLabel() }}</span>
                        </div>
                        <p class="font-mono text-[11px] text-ink-mute mt-1">{{ $op->city }} · {{ $op->responses_count }} respuesta(s)</p>
                    </div>
                    @if ($op->pending_responses_count > 0)
                        <span class="shrink-0 px-2.5 py-1 rounded-pill bg-pitch text-bone font-display font-bold text-[11px]">{{ $op->pending_responses_count }} nuevas</span>
                    @endif
                </a>
            @endforeach
        </div>
    @endif

    {{-- Mis respuestas --}}
    <p class="font-mono text-[11px] tracking-wide-label uppercase text-ink-mute mb-3">Respuestas que envié ({{ $myResponses->count() }})</p>
    @if ($myResponses->isEmpty())
        <div class="bg-white border border-line rounded-md shadow-card p-6 text-center text-ink-soft text-[14px]">Todavía no respondiste a ninguna oportunidad.</div>
    @else
        <div class="flex flex-col gap-3">
            @foreach ($myResponses as $resp)
                @if ($resp->opportunity)
                    <a href="{{ route('social.oportunidades.show', $resp->opportunity) }}" class="bg-white border border-line rounded-md shadow-card p-4 flex items-center justify-between gap-3 hover:border-pitch transition-all duration-fast">
                        <div class="min-w-0">
                            <p class="font-display font-bold text-pitch text-[14px]">{{ $resp->opportunity->typeLabel() }} · {{ $resp->opportunity->club?->name ?? $resp->opportunity->city }}</p>
                            @if ($resp->message)
                                <p class="font-mono text-[11px] text-ink-mute mt-1 truncate">“{{ $resp->message }}”</p>
                            @endif
                        </div>
                        <span class="shrink-0 font-mono text-[10.5px] uppercase text-ink-mute">{{ $respStatusLabel[$resp->status] ?? $resp->status }}</span>
                    </a>
                @endif
            @endforeach
        </div>
    @endif
</div>
@endsection
