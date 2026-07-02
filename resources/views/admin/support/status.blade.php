@extends('layouts.app')
@section('title', 'Admin · Estado del servicio')

@section('content')
@include('admin._nav')

<div class="max-w-3xl mx-auto px-4 py-8">
    <div class="flex items-center gap-3 mb-6">
        <a href="{{ route('admin.soporte.dashboard') }}" class="text-sm text-muted hover:text-text underline">← Soporte</a>
        <h1 class="font-display text-2xl font-bold text-text">Estado del servicio</h1>
    </div>

    @if(session('success'))
        <div class="mb-6 p-3 rounded-lg bg-green/10 border border-green/30 text-green text-sm">{{ session('success') }}</div>
    @endif

    <p class="text-sm text-muted mb-6">El monitor automático revisa cada componente cada 5 minutos. Puedes forzar un estado manual acá (marca <em>auto_detected = false</em>).</p>

    <div class="space-y-3">
        @foreach($components as $c)
            <form method="POST" action="{{ route('admin.soporte.status.update', $c->component) }}"
                  class="flex flex-wrap items-center gap-3 p-4 rounded-xl border border-border bg-surface">
                @csrf @method('PATCH')
                <div class="min-w-0 flex-1">
                    <div class="font-medium text-text">{{ config('support.component_labels')[$c->component] ?? $c->component }}</div>
                    <div class="text-xs text-muted mt-0.5">
                        {{ $c->auto_detected ? 'Automático' : 'Manual' }}
                        @if($c->last_checked_at) · verificado {{ $c->last_checked_at->diffForHumans() }} @endif
                    </div>
                </div>
                <select name="status" class="rounded-lg border border-border bg-bg text-text text-sm px-3 py-2">
                    @foreach(['operativo' => 'Operativo', 'degradado' => 'Degradado', 'caido' => 'Caído', 'mantenimiento' => 'Mantenimiento'] as $k => $v)
                        <option value="{{ $k }}" @selected($c->status === $k)>{{ $v }}</option>
                    @endforeach
                </select>
                <input type="text" name="message" maxlength="300" value="{{ $c->message }}" placeholder="Mensaje (opcional)"
                       class="rounded-lg border border-border bg-bg text-text text-sm px-3 py-2 flex-1 min-w-[10rem]">
                <button class="btn btn-secondary btn-sm">Guardar</button>
            </form>
        @endforeach
    </div>
</div>
@endsection
