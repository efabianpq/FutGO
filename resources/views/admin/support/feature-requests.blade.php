@extends('layouts.app')
@section('title', 'Admin · Funcionalidades')

@php
    $statuses = [
        'recibido' => 'Recibido', 'evaluando' => 'Evaluando', 'planeado' => 'Planeado',
        'en_desarrollo' => 'En desarrollo', 'lanzado' => 'Lanzado', 'descartado' => 'Descartado',
    ];
@endphp

@section('content')
@include('admin._nav')

<div class="max-w-4xl mx-auto px-4 py-8">
    <div class="flex items-center gap-3 mb-6">
        <a href="{{ route('admin.soporte.dashboard') }}" class="text-sm text-muted hover:text-text underline">← Soporte</a>
        <h1 class="font-display text-2xl font-bold text-text">Funcionalidades solicitadas</h1>
    </div>

    @if(session('success'))
        <div class="mb-6 p-3 rounded-lg bg-green/10 border border-green/30 text-green text-sm">{{ session('success') }}</div>
    @endif

    <div class="space-y-2">
        @forelse($features as $feature)
            <div class="flex items-start gap-4 p-4 rounded-xl border border-border bg-surface">
                <div class="shrink-0 flex flex-col items-center justify-center w-12 h-12 rounded-xl bg-surface-2 text-text">
                    <span class="text-sm font-bold">{{ $feature->votes_count }}</span>
                    <span class="text-[10px] text-muted">votos</span>
                </div>
                <div class="min-w-0 flex-1">
                    <h3 class="font-semibold text-text">{{ $feature->title }}</h3>
                    <p class="text-sm text-muted mt-1">{{ $feature->description }}</p>
                </div>
                <form method="POST" action="{{ route('admin.soporte.features.status', $feature) }}" class="shrink-0">
                    @csrf @method('PATCH')
                    <select name="status" onchange="this.form.submit()"
                            class="rounded-lg border border-border bg-bg text-text text-sm px-3 py-2">
                        @foreach($statuses as $k => $v)
                            <option value="{{ $k }}" @selected($feature->status === $k)>{{ $v }}</option>
                        @endforeach
                    </select>
                </form>
            </div>
        @empty
            <p class="text-sm text-muted p-4 rounded-xl border border-border">No hay funcionalidades solicitadas todavía.</p>
        @endforelse
    </div>

    <div class="mt-6">{{ $features->links() }}</div>
</div>
@endsection
