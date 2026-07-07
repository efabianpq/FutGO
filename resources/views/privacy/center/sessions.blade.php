@extends('layouts.app')
@section('title', 'Sesiones y dispositivos')

@section('content')
<div class="max-w-3xl mx-auto px-4 sm:px-6 py-8">
    <div class="flex items-center gap-2 mb-1">
        <h1 class="text-2xl font-bold text-text">Centro de Privacidad</h1>
        <x-help-hint topic="privacidad.sesiones" />
    </div>
    <p class="text-[14px] text-muted mb-6">Dispositivos con sesión abierta en tu cuenta.</p>

    @include('privacy.center._tabs')

    <div class="flex justify-end mb-3">
        <form method="POST" action="{{ route('privacidad.sesiones.otras') }}"
              onsubmit="return confirm('¿Cerrar sesión en todos los otros dispositivos?')">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-secondary btn-sm">Cerrar las demás sesiones</button>
        </form>
    </div>

    <div class="bg-surface border border-border rounded-md divide-y divide-border">
        @forelse($sessions as $s)
            <div class="flex items-center justify-between gap-4 p-4">
                <div>
                    <div class="flex items-center gap-2">
                        <span class="font-semibold text-text text-[14px]">{{ $s['device'] }}</span>
                        @if($s['is_current'])
                            <span class="px-2 py-0.5 rounded-full bg-primary/15 text-primary text-[11px] font-semibold">Este dispositivo</span>
                        @endif
                    </div>
                    <p class="text-[12px] text-muted mt-0.5">
                        IP {{ $s['ip'] ?? '—' }} · última actividad {{ $s['last_activity']->diffForHumans() }}
                    </p>
                </div>
                @unless($s['is_current'])
                    <form method="POST" action="{{ route('privacidad.sesiones.destroy', $s['id']) }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-[13px] text-alerta font-semibold">Cerrar</button>
                    </form>
                @endunless
            </div>
        @empty
            <p class="p-4 text-[13px] text-muted">No hay sesiones registradas.</p>
        @endforelse
    </div>
</div>
@endsection
