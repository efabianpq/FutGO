@extends('layouts.app')
@section('title', 'Admin · Ticket #' . $ticket->id)

@php
    $statusLabels = [
        'abierto' => 'Abierto', 'en_diagnostico' => 'En diagnóstico', 'esperando_usuario' => 'Esperando usuario',
        'en_revision' => 'En revisión', 'resuelto' => 'Resuelto', 'cerrado' => 'Cerrado', 'reabierto' => 'Reabierto',
    ];
@endphp

@section('content')
@include('admin._nav')

<div class="max-w-4xl mx-auto px-4 py-8">
    <a href="{{ route('admin.soporte.tickets') }}" class="text-sm text-muted hover:text-text underline">← Tickets</a>

    @if(session('success'))
        <div class="my-4 p-3 rounded-lg bg-green/10 border border-green/30 text-green text-sm">{{ session('success') }}</div>
    @endif

    <div class="mt-4 flex items-start gap-3">
        <div class="flex-1">
            <h1 class="font-display text-xl font-bold text-text">{{ $ticket->subject }}</h1>
            <p class="text-xs text-muted mt-1">
                Caso #{{ $ticket->id }} · {{ $ticket->user->name ?? 'N/D' }} ({{ $ticket->user->futgo_id ?? '—' }})
                · {{ $ticket->category }} · prioridad {{ $ticket->priority }}
                · {{ $ticket->created_at->diffForHumans() }}
            </p>
        </div>
        <span class="shrink-0 text-xs px-2.5 py-1 rounded-full bg-surface-2 text-muted">{{ $statusLabels[$ticket->status] ?? $ticket->status }}</span>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mt-6">
        {{-- Columna principal: conversación + contexto --}}
        <div class="lg:col-span-2 space-y-6">
            @if($ticket->conversation && !empty($ticket->conversation->messages))
                <div>
                    <h2 class="font-mono text-[11px] uppercase tracking-wide-label text-muted mb-3">Conversación con el bot</h2>
                    <div class="space-y-3">
                        @foreach($ticket->conversation->messages as $msg)
                            <div class="{{ ($msg['role'] ?? '') === 'user' ? 'flex justify-end' : 'flex' }}">
                                <div class="{{ ($msg['role'] ?? '') === 'user' ? 'bg-green text-white' : 'bg-surface-2 text-text' }} rounded-2xl px-4 py-2.5 max-w-sm text-sm whitespace-pre-line">
                                    {{ $msg['content'] ?? '' }}
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            @if($ticket->context_snapshot)
                <div>
                    <h2 class="font-mono text-[11px] uppercase tracking-wide-label text-muted mb-2">Contexto capturado</h2>
                    <pre class="text-xs bg-surface-2 text-text rounded-xl p-4 overflow-x-auto">{{ json_encode($ticket->context_snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                </div>
            @endif

            @if($ticket->error_trace)
                <div>
                    <h2 class="font-mono text-[11px] uppercase tracking-wide-label text-muted mb-2">Traza técnica</h2>
                    <pre class="text-xs bg-surface-2 text-text rounded-xl p-4 overflow-x-auto">{{ json_encode($ticket->error_trace, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                </div>
            @endif
        </div>

        {{-- Columna lateral: acciones --}}
        <div class="space-y-4">
            <div class="p-4 rounded-xl border border-border bg-surface space-y-3">
                <form method="POST" action="{{ route('admin.soporte.tickets.status', $ticket) }}">
                    @csrf @method('PATCH')
                    <label class="block text-xs text-muted mb-1">Cambiar estado</label>
                    <div class="flex gap-2">
                        <select name="status" class="flex-1 rounded-lg border border-border bg-bg text-text text-sm px-3 py-2">
                            @foreach($statusLabels as $k => $v)
                                <option value="{{ $k }}" @selected($ticket->status === $k)>{{ $v }}</option>
                            @endforeach
                        </select>
                        <button class="btn btn-secondary btn-sm">Guardar</button>
                    </div>
                </form>

                <form method="POST" action="{{ route('admin.soporte.tickets.assign', $ticket) }}">
                    @csrf @method('PATCH')
                    <label class="block text-xs text-muted mb-1">Asignar a</label>
                    <div class="flex gap-2">
                        <select name="assigned_to" class="flex-1 rounded-lg border border-border bg-bg text-text text-sm px-3 py-2">
                            @foreach($admins as $admin)
                                <option value="{{ $admin->id }}" @selected($ticket->assigned_to === $admin->id)>{{ $admin->name }}</option>
                            @endforeach
                        </select>
                        <button class="btn btn-secondary btn-sm">Asignar</button>
                    </div>
                </form>
            </div>

            @unless($ticket->isResolved())
                <form method="POST" action="{{ route('admin.soporte.tickets.resolve', $ticket) }}" class="p-4 rounded-xl border border-green/30 bg-green/5">
                    @csrf
                    <label class="block text-xs text-muted mb-1">Notas de resolución</label>
                    <textarea name="resolution_notes" rows="4" required maxlength="2000"
                              class="w-full rounded-lg border border-border bg-bg text-text text-sm px-3 py-2 mb-2"
                              placeholder="Explicá cómo se resolvió..."></textarea>
                    <button class="btn btn-primary btn-sm w-full">Resolver ticket</button>
                </form>
            @else
                <form method="POST" action="{{ route('admin.soporte.tickets.generate-article', $ticket) }}" class="p-4 rounded-xl border border-border bg-surface">
                    @csrf
                    <p class="text-xs text-muted mb-2">Convertí este caso resuelto en un artículo de la base de conocimiento.</p>
                    <button class="btn btn-secondary btn-sm w-full">Generar artículo</button>
                </form>
            @endunless
        </div>
    </div>
</div>
@endsection
