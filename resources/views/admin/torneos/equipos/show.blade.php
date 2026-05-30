@extends('layouts.app')
@section('title', 'Admin · ' . $team->name)

@section('content')
@include('admin.torneos._nav')

@php
    $statusMeta = [
        'pending'  => ['Pendiente', 'upcoming'],
        'approved' => ['Aprobado',  'win'],
        'rejected' => ['Rechazado', 'default'],
    ];
    [$label, $variant] = $statusMeta[$team->status] ?? [$team->status, 'default'];
@endphp

<div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

    {{-- Breadcrumb --}}
    <nav class="flex items-center gap-2 font-mono text-[12px] text-ink-mute mb-5">
        <a href="{{ route('admin.torneos.show', $tournament) }}" class="hover:text-pitch">{{ $tournament->name }}</a>
        <span>›</span>
        <a href="{{ route('admin.torneos.equipos.index', $tournament) }}" class="hover:text-pitch">Equipos</a>
        <span>›</span>
        <span class="text-pitch font-semibold">{{ $team->name }}</span>
    </nav>

    @if (session('status'))
        <div class="mb-4 bg-gol/20 border border-gol text-pitch-deep px-4 py-3 rounded-md font-display font-semibold">
            {{ session('status') }}
        </div>
    @endif

    <div class="flex items-start justify-between gap-4 mb-6">
        <div>
            <div class="flex items-center gap-3">
                <h1 class="font-display font-bold text-display-m text-pitch uppercase">{{ $team->name }}</h1>
                @if ($team->color)
                    <span class="inline-block w-5 h-5 rounded-full border border-line"
                          style="background:{{ $team->color }}"></span>
                @endif
                <x-badge :variant="$variant">{{ $label }}</x-badge>
            </div>
            <p class="font-mono text-[12px] text-ink-mute mt-1">
                Capitán: {{ $team->captain?->name ?? '—' }} · {{ $team->players->count() }} jugadores
            </p>
        </div>
        <div class="flex gap-2">
            @if ($team->isPending() || $team->isRejected())
                <form method="POST" action="{{ route('admin.torneos.equipos.approve', [$tournament, $team]) }}">
                    @csrf @method('PATCH')
                    <x-btn type="submit" variant="accent" size="sm">Aprobar</x-btn>
                </form>
            @endif
            @if ($team->isPending() || $team->isApproved())
                <form method="POST" action="{{ route('admin.torneos.equipos.reject', [$tournament, $team]) }}">
                    @csrf @method('PATCH')
                    <x-btn type="submit" variant="danger" size="sm">Rechazar</x-btn>
                </form>
            @endif
        </div>
    </div>

    {{-- Lista de jugadores --}}
    <div class="bg-white border border-line rounded-md shadow-card-2 overflow-hidden">
        <div class="bg-pitch-mist border-b border-line px-4 py-3">
            <p class="font-mono text-[11px] tracking-wide-label uppercase text-pitch">Jugadores</p>
        </div>

        @if ($team->players->isEmpty())
            <div class="p-8 text-center text-ink-soft">Sin jugadores registrados.</div>
        @else
            <table class="w-full text-left">
                <thead>
                    <tr class="font-mono text-[11px] tracking-wide-label uppercase text-ink-mute border-b border-line-soft">
                        <th class="px-4 py-2">#</th>
                        <th class="px-4 py-2">Jugador</th>
                        <th class="px-4 py-2">Posición</th>
                        <th class="px-4 py-2 text-center">Estado</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line-soft">
                    @foreach ($team->players as $tp)
                        <tr class="hover:bg-bone-soft">
                            <td class="px-4 py-3 font-mono text-ink-mute text-[13px]">
                                {{ $tp->jersey_number ?? '—' }}
                            </td>
                            <td class="px-4 py-3">
                                <p class="font-semibold text-[14px]">{{ $tp->user->name }}</p>
                                <p class="font-mono text-[11px] text-ink-mute">{{ $tp->user->email }}</p>
                                @if ($tp->user_id === $team->captain_user_id)
                                    <x-badge variant="win" class="mt-1">Capitán</x-badge>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-[13px] text-ink-soft capitalize">
                                {{ $tp->position ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-center">
                                <x-badge :variant="$tp->isActive() ? 'win' : 'upcoming'">
                                    {{ $tp->isActive() ? 'Activo' : 'Inactivo' }}
                                </x-badge>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>
@endsection
