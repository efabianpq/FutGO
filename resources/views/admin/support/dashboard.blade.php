@extends('layouts.app')
@section('title', 'Admin · Soporte')

@section('content')
@include('admin._nav')

<div class="max-w-7xl mx-auto px-4 py-8">
    <p class="eyebrow">Panel administrador</p>
    <div class="flex items-center gap-2 mt-2 mb-6">
        <h1 class="font-display font-bold text-2xl text-text">Centro de Soporte</h1>
        <x-help-hint topic="admin.soporte.dashboard" />
    </div>

    @if(session('success'))
        <div class="mb-6 p-3 rounded-lg bg-green/10 border border-green/30 text-green text-sm">{{ session('success') }}</div>
    @endif

    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-8">
        <x-stat-card label="Tickets totales" :value="$stats['total']" />
        <x-stat-card label="Abiertos" :value="$stats['abiertos']" />
        <x-stat-card label="En revisión" :value="$stats['en_revision']" />
        <x-stat-card label="Resueltos hoy" :value="$stats['resueltos_hoy']" />
        <x-stat-card label="Sin asignar" :value="$stats['sin_asignar']" accent="gol" />
        <x-stat-card label="Críticos abiertos" :value="$stats['criticos']" accent="gol" />
        <x-stat-card label="Satisfechos" :value="$stats['satisfaccion_positiva']" />
        <x-stat-card label="Insatisfechos" :value="$stats['satisfaccion_negativa']" />
    </div>

    <div class="flex flex-wrap gap-2 mb-8">
        <a href="{{ route('admin.soporte.tickets') }}" class="btn btn-secondary btn-sm">Todos los tickets</a>
        <a href="{{ route('admin.soporte.knowledge') }}" class="btn btn-secondary btn-sm">Base de conocimiento</a>
        <a href="{{ route('admin.soporte.status') }}" class="btn btn-secondary btn-sm">Estado del servicio</a>
        <a href="{{ route('admin.soporte.features') }}" class="btn btn-secondary btn-sm">Funcionalidades</a>
    </div>

    @if($patronesActivos->isNotEmpty())
        <div class="mb-8 p-4 rounded-xl border border-yellow-400 bg-yellow-400/10">
            <p class="font-semibold text-yellow-600 dark:text-yellow-300 mb-2 inline-flex items-center gap-1.5"><x-icon name="warning" class="w-4 h-4" /> Patrones de incidente activos</p>
            <ul class="text-sm text-text space-y-1">
                @foreach($patronesActivos as $p)
                    <li>{{ $p->pattern_key }} — {{ $p->tickets_count }} tickets (desde {{ $p->first_detected_at->diffForHumans() }})</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2">
            <h2 class="font-mono text-[11px] uppercase tracking-wide-label text-muted mb-3">Tickets abiertos por prioridad</h2>
            <div class="space-y-2">
                @forelse($ticketsRecientes as $ticket)
                    <a href="{{ route('admin.soporte.tickets.show', $ticket) }}"
                       class="flex items-center gap-3 p-3 rounded-xl border border-border bg-surface hover:bg-surface-2 transition">
                        <span class="shrink-0 text-[11px] px-2 py-0.5 rounded-full
                            {{ $ticket->priority === 'critica' ? 'bg-red-500/15 text-red-500' : ($ticket->priority === 'alta' ? 'bg-yellow-400/15 text-yellow-600' : 'bg-surface-2 text-muted') }}">
                            {{ ucfirst($ticket->priority) }}
                        </span>
                        <div class="min-w-0 flex-1">
                            <div class="text-sm font-medium text-text truncate">{{ $ticket->subject }}</div>
                            <div class="text-xs text-muted">#{{ $ticket->id }} · {{ $ticket->user->name ?? 'N/D' }} · {{ $ticket->category }}</div>
                        </div>
                    </a>
                @empty
                    <p class="text-sm text-muted p-4 rounded-xl border border-border inline-flex items-center gap-1.5"><x-icon name="check-circle" class="w-4 h-4 text-green" /> No hay tickets abiertos.</p>
                @endforelse
            </div>
        </div>

        <div>
            <h2 class="font-mono text-[11px] uppercase tracking-wide-label text-muted mb-3">Estado del servicio</h2>
            <div class="space-y-2">
                @foreach($statusComponents as $c)
                    <div class="flex items-center justify-between p-3 rounded-xl border border-border bg-surface">
                        <span class="text-sm text-text">{{ config('support.component_labels')[$c->component] ?? $c->component }}</span>
                        <span class="w-2.5 h-2.5 rounded-full {{ $c->status === 'operativo' ? 'bg-green' : ($c->status === 'caido' ? 'bg-red-500' : 'bg-yellow-400') }}"></span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
@endsection
