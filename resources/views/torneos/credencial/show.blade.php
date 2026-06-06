@extends('layouts.app')
@section('title', 'Mi credencial · ' . $user->name)

@section('content')
@php
    $statusMeta = [
        'draft'       => 'Borrador',
        'open'        => 'Inscripción',
        'in_progress' => 'En juego',
        'finished'    => 'Finalizado',
        'cancelled'   => 'Cancelado',
    ];
@endphp

<div class="max-w-xl mx-auto px-4 sm:px-6 py-8">

    <div class="flex items-center justify-between mb-5">
        <p class="eyebrow">🪪 Credencial digital FUTGO</p>
        <x-btn :href="route('torneos.mi-carrera')" variant="ghost" size="sm">← Mi carrera</x-btn>
    </div>

    {{-- Tarjeta credencial --}}
    <div class="bg-white border border-line rounded-md shadow-card-2 overflow-hidden">
        {{-- Cabecera identidad --}}
        <div class="bg-pitch px-5 sm:px-6 py-4 sm:py-5 flex items-center gap-4">
            <x-avatar :user="$user" size="md" class="sm:hidden !border-bone/40 shrink-0" />
            <x-avatar :user="$user" size="lg" class="hidden sm:block !border-bone/40 shrink-0" />
            <div class="min-w-0">
                <p class="font-display font-extrabold text-bone text-lg sm:text-display-s uppercase break-words">{{ $user->name }}</p>
                <p class="font-mono text-[12px] text-bone/70 mt-0.5">Jugador FUTGO</p>
            </div>
        </div>

        {{-- Identificador + QR --}}
        <div class="p-6 flex flex-col sm:flex-row items-center gap-6">
            <div class="shrink-0 bg-white border border-line rounded-md p-2" style="width:180px;height:180px">
                <div class="w-full h-full [&>svg]:w-full [&>svg]:h-full">{!! $qrSvg !!}</div>
            </div>
            <div class="flex-1 text-center sm:text-left">
                <p class="font-mono text-[11px] tracking-wide-label uppercase text-ink-mute">Identificador FUTGO</p>
                <p class="font-mono font-bold text-2xl text-pitch mt-1 tracking-wider">{{ $user->futgo_id }}</p>
                <p class="text-[13px] text-ink-soft mt-3 leading-relaxed">
                    Presentá este código al árbitro. Si no puede escanearlo, dictá el
                    identificador para validarlo a mano.
                </p>
            </div>
        </div>
    </div>

    {{-- Equipos vigentes --}}
    <section class="bg-white border border-line rounded-md shadow-card mt-6 overflow-hidden">
        <div class="bg-pitch-mist border-b border-line px-4 py-3">
            <p class="font-mono text-[11px] tracking-wide-label uppercase text-pitch">Mis equipos activos ({{ $memberships->count() }})</p>
        </div>
        @if ($memberships->isEmpty())
            <div class="p-6 text-center text-ink-soft text-[14px]">No estás activo en ningún equipo por ahora.</div>
        @else
            <ul class="divide-y divide-line-soft">
                @foreach ($memberships as $tp)
                    <li class="px-4 py-3 flex items-center justify-between gap-3">
                        <div class="min-w-0">
                            <p class="font-display font-bold text-ink truncate">{{ $tp->team->name }}</p>
                            <p class="font-mono text-[11px] text-ink-mute truncate">{{ $tp->team->tournament->name }}</p>
                        </div>
                        <span class="font-mono text-[10px] uppercase tracking-wide-label text-ink-soft shrink-0">
                            {{ $statusMeta[$tp->team->tournament->status] ?? $tp->team->tournament->status }}
                        </span>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    <p class="text-[12px] text-ink-mute mt-4 text-center">
        Por tu seguridad, el código no contiene tu documento ni datos personales:
        solo tu identificador público, verificable contra FUTGO.
    </p>
</div>
@endsection
