@extends('layouts.public')
@section('title', 'Estado del Servicio — FutGO')

@section('content')
<div class="max-w-2xl mx-auto px-4 py-10">
    <h1 class="font-display text-2xl font-bold text-text mb-2">❤️ Estado del Servicio</h1>
    <p class="text-muted mb-8 text-sm">Actualizado cada 5 minutos automáticamente.</p>

    @php
        $allOk  = $components->every(fn($c) => $c->status === 'operativo');
        $labels = config('support.component_labels');
    @endphp

    @if($allOk)
        <div class="mb-6 p-4 rounded-xl bg-green/10 border border-green/30 text-green font-semibold">
            ✅ Todos los sistemas operativos
        </div>
    @else
        <div class="mb-6 p-4 rounded-xl bg-yellow-400/10 border border-yellow-400 text-yellow-600 dark:text-yellow-300 font-semibold">
            ⚠️ Hay componentes con problemas activos
        </div>
    @endif

    <div class="space-y-3">
        @foreach($components as $component)
        <div class="flex items-center justify-between p-4 rounded-xl border border-border bg-surface">
            <div>
                <div class="font-medium text-text">{{ $labels[$component->component] ?? $component->component }}</div>
                @if($component->message)
                    <div class="text-xs text-muted mt-0.5">{{ $component->message }}</div>
                @endif
                @if($component->last_checked_at)
                    <div class="text-xs text-muted mt-0.5">Verificado {{ $component->last_checked_at->diffForHumans() }}</div>
                @endif
            </div>
            <div class="flex items-center gap-2">
                @php
                    $dot = match($component->status) {
                        'operativo'     => 'bg-green',
                        'degradado'     => 'bg-yellow-400',
                        'caido'         => 'bg-red-500',
                        'mantenimiento' => 'bg-blue-400',
                        default         => 'bg-muted',
                    };
                    $label = match($component->status) {
                        'operativo'     => 'Operativo',
                        'degradado'     => 'Degradado',
                        'caido'         => 'Caído',
                        'mantenimiento' => 'Mantenimiento',
                        default         => ucfirst($component->status),
                    };
                @endphp
                <span class="w-3 h-3 rounded-full {{ $dot }}"></span>
                <span class="text-sm text-text">{{ $label }}</span>
            </div>
        </div>
        @endforeach
    </div>

    <p class="text-center mt-8 text-sm text-muted">
        @auth
            <a href="{{ route('soporte.index') }}" class="underline">Volver al Centro de Soporte</a>
        @else
            <a href="{{ route('home') }}" class="underline">Volver al inicio</a>
        @endauth
    </p>
</div>

<script>setTimeout(() => location.reload(), 60000);</script>
@endsection
