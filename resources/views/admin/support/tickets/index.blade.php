@extends('layouts.app')
@section('title', 'Admin · Tickets de soporte')

@php
    $statusLabels = [
        'abierto' => 'Abierto', 'en_diagnostico' => 'En diagnóstico', 'esperando_usuario' => 'Esperando usuario',
        'en_revision' => 'En revisión', 'resuelto' => 'Resuelto', 'cerrado' => 'Cerrado', 'reabierto' => 'Reabierto',
    ];
@endphp

@section('content')
@include('admin._nav')

<div class="max-w-7xl mx-auto px-4 py-8">
    <div class="flex items-center gap-3 mb-6">
        <a href="{{ route('admin.soporte.dashboard') }}" class="text-sm text-muted hover:text-text underline">← Soporte</a>
        <h1 class="font-display text-2xl font-bold text-text">Tickets</h1>
        <x-help-hint topic="admin.soporte.tickets" />
    </div>

    <form method="GET" class="flex flex-wrap gap-2 mb-6">
        <select name="status" class="rounded-lg border border-border bg-bg text-text text-sm px-3 py-2">
            <option value="">Todos los estados</option>
            @foreach($statusLabels as $k => $v)
                <option value="{{ $k }}" @selected(request('status') === $k)>{{ $v }}</option>
            @endforeach
        </select>
        <select name="priority" class="rounded-lg border border-border bg-bg text-text text-sm px-3 py-2">
            <option value="">Toda prioridad</option>
            @foreach(['critica' => 'Crítica', 'alta' => 'Alta', 'media' => 'Media', 'baja' => 'Baja'] as $k => $v)
                <option value="{{ $k }}" @selected(request('priority') === $k)>{{ $v }}</option>
            @endforeach
        </select>
        <button type="submit" class="btn btn-secondary btn-sm">Filtrar</button>
        @if(request()->hasAny(['status', 'priority', 'category', 'assigned']))
            <a href="{{ route('admin.soporte.tickets') }}" class="btn btn-ghost btn-sm">Limpiar</a>
        @endif
    </form>

    <div class="overflow-x-auto rounded-xl border border-border">
        <table class="w-full text-sm">
            <thead class="bg-surface-2 text-muted text-left">
                <tr>
                    <th class="px-4 py-2 font-medium">#</th>
                    <th class="px-4 py-2 font-medium">Asunto</th>
                    <th class="px-4 py-2 font-medium">Usuario</th>
                    <th class="px-4 py-2 font-medium">Categoría</th>
                    <th class="px-4 py-2 font-medium">Prioridad</th>
                    <th class="px-4 py-2 font-medium">Estado</th>
                    <th class="px-4 py-2 font-medium">Creado</th>
                </tr>
            </thead>
            <tbody>
                @forelse($tickets as $ticket)
                    <tr class="border-t border-border hover:bg-surface-2 cursor-pointer" onclick="window.location='{{ route('admin.soporte.tickets.show', $ticket) }}'">
                        <td class="px-4 py-2 text-muted">{{ $ticket->id }}</td>
                        <td class="px-4 py-2 text-text max-w-xs truncate">{{ $ticket->subject }}</td>
                        <td class="px-4 py-2 text-muted">{{ $ticket->user->name ?? 'N/D' }}</td>
                        <td class="px-4 py-2 text-muted">{{ $ticket->category }}</td>
                        <td class="px-4 py-2">
                            <span class="text-[11px] px-2 py-0.5 rounded-full {{ $ticket->priority === 'critica' ? 'bg-red-500/15 text-red-500' : ($ticket->priority === 'alta' ? 'bg-yellow-400/15 text-yellow-600' : 'bg-surface-2 text-muted') }}">
                                {{ ucfirst($ticket->priority) }}
                            </span>
                        </td>
                        <td class="px-4 py-2 text-muted">{{ $statusLabels[$ticket->status] ?? $ticket->status }}</td>
                        <td class="px-4 py-2 text-muted">{{ $ticket->created_at->diffForHumans() }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-muted">No hay tickets que coincidan.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">{{ $tickets->links() }}</div>
</div>
@endsection
