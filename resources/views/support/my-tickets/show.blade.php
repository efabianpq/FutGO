@extends('layouts.app')
@section('title', 'Caso #' . $ticket->id)

@php
    $statusLabels = [
        'abierto' => 'Abierto', 'en_diagnostico' => 'En diagnóstico', 'esperando_usuario' => 'Esperando respuesta',
        'en_revision' => 'En revisión', 'resuelto' => 'Resuelto', 'cerrado' => 'Cerrado', 'reabierto' => 'Reabierto',
    ];
@endphp

@section('content')
<div class="max-w-2xl mx-auto px-4 py-8">
    <a href="{{ route('soporte.my-tickets') }}" class="text-sm text-muted hover:text-text underline">← Mis casos</a>

    <div class="mt-4 flex items-start gap-3">
        <h1 class="font-display text-xl font-bold text-text flex-1">{{ $ticket->subject }}</h1>
        <x-help-hint topic="soporte.my-tickets.show" />
        <span class="shrink-0 text-xs px-2.5 py-1 rounded-full bg-surface-2 text-muted">
            {{ $statusLabels[$ticket->status] ?? $ticket->status }}
        </span>
    </div>
    <p class="text-xs text-muted mt-1">Caso #{{ $ticket->id }} · abierto {{ $ticket->created_at->diffForHumans() }}</p>

    @if($ticket->resolution_notes)
        <div class="mt-6 p-4 rounded-xl border border-green/30 bg-green/5">
            <p class="font-semibold text-green text-sm mb-1">Respuesta del equipo</p>
            <p class="text-sm text-text whitespace-pre-line">{{ $ticket->resolution_notes }}</p>
        </div>
    @endif

    @if($ticket->conversation && !empty($ticket->conversation->messages))
        <h2 class="font-mono text-[11px] uppercase tracking-wide-label text-muted mt-8 mb-3">Conversación</h2>
        <div class="space-y-3">
            @foreach($ticket->conversation->messages as $msg)
                <div class="{{ ($msg['role'] ?? '') === 'user' ? 'flex justify-end' : 'flex' }}">
                    <div class="{{ ($msg['role'] ?? '') === 'user' ? 'bg-green text-white' : 'bg-surface-2 text-text' }} rounded-2xl px-4 py-2.5 max-w-sm text-sm whitespace-pre-line">
                        {{ $msg['content'] ?? '' }}
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
@endsection
