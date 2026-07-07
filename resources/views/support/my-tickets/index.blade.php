@extends('layouts.app')
@section('title', 'Mis casos')

@php
    $statusLabels = [
        'abierto' => 'Abierto', 'en_diagnostico' => 'En diagnóstico', 'esperando_usuario' => 'Esperando respuesta',
        'en_revision' => 'En revisión', 'resuelto' => 'Resuelto', 'cerrado' => 'Cerrado', 'reabierto' => 'Reabierto',
    ];
    $statusColor = fn($s) => match($s) {
        'resuelto', 'cerrado' => 'bg-green/15 text-green',
        'en_revision', 'en_diagnostico' => 'bg-blue-400/15 text-blue-500',
        'reabierto' => 'bg-yellow-400/15 text-yellow-600',
        default => 'bg-surface-2 text-muted',
    };
@endphp

@section('content')
<div class="max-w-3xl mx-auto px-4 py-8">
    <div class="flex items-center gap-3 mb-6">
        <h1 class="font-display text-2xl font-bold text-text inline-flex items-center gap-2"><x-icon name="clipboard" class="w-6 h-6" /> Mis casos</h1>
        <x-help-hint topic="soporte.my-tickets" />
        <a href="{{ route('soporte.index') }}" class="ml-auto text-sm text-muted hover:text-text underline">Volver</a>
    </div>

    @forelse($tickets as $ticket)
        <a href="{{ route('soporte.my-tickets.show', $ticket) }}"
           class="flex items-center gap-3 p-4 mb-2 rounded-xl border border-border bg-surface hover:bg-surface-2 transition">
            <div class="min-w-0 flex-1">
                <div class="font-semibold text-text truncate">{{ $ticket->subject }}</div>
                <div class="text-xs text-muted mt-0.5">#{{ $ticket->id }} · {{ $ticket->created_at->diffForHumans() }}</div>
            </div>
            <span class="shrink-0 text-xs px-2.5 py-1 rounded-full {{ $statusColor($ticket->status) }}">
                {{ $statusLabels[$ticket->status] ?? $ticket->status }}
            </span>
        </a>
    @empty
        <div class="p-8 text-center text-muted rounded-xl border border-border">
            No tienes casos abiertos. Si necesitas ayuda, escríbele al
            <a href="{{ route('soporte.chat') }}" class="text-green underline">Asistente IA</a>.
        </div>
    @endforelse

    <div class="mt-6">{{ $tickets->links() }}</div>
</div>
@endsection
