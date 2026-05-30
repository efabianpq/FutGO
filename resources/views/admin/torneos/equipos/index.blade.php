@extends('layouts.app')
@section('title', 'Admin · Equipos · ' . $tournament->name)

@section('content')
@include('admin.torneos._nav')

@php
    $statusMeta = [
        'pending'  => ['Pendiente', 'upcoming'],
        'approved' => ['Aprobado',  'win'],
        'rejected' => ['Rechazado', 'default'],
    ];
@endphp

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

    {{-- Breadcrumb --}}
    <nav class="flex items-center gap-2 font-mono text-[12px] text-ink-mute mb-5">
        <a href="{{ route('admin.torneos.show', $tournament) }}" class="hover:text-pitch">{{ $tournament->name }}</a>
        <span>›</span>
        <span class="text-pitch font-semibold">Equipos</span>
    </nav>

    <div class="flex items-center justify-between mb-6">
        <div>
            <p class="eyebrow">{{ $tournament->name }}</p>
            <h1 class="font-display font-bold text-display-m text-pitch uppercase mt-1">Equipos inscritos</h1>
        </div>
        <x-badge variant="default">{{ $teams->count() }} equipos</x-badge>
    </div>

    @if (session('status'))
        <div class="mb-4 bg-gol/20 border border-gol text-pitch-deep px-4 py-3 rounded-md font-display font-semibold">
            {{ session('status') }}
        </div>
    @endif

    @if ($teams->isEmpty())
        <div class="bg-white border border-line rounded-md shadow-card-2 p-10 text-center">
            <p class="text-ink-soft text-lg">Todavía no hay equipos inscritos en este torneo.</p>
        </div>
    @else
        <div class="bg-white border border-line rounded-md shadow-card-2 overflow-hidden">
            <table class="w-full text-left">
                <thead class="bg-pitch-mist border-b border-line">
                    <tr class="font-mono text-[11px] tracking-wide-label uppercase text-pitch">
                        <th class="px-4 py-3">Equipo</th>
                        <th class="px-4 py-3">Capitán</th>
                        <th class="px-4 py-3 text-center">Jugadores</th>
                        <th class="px-4 py-3 text-center">Estado</th>
                        <th class="px-4 py-3 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line-soft">
                    @foreach ($teams as $team)
                        @php [$label, $variant] = $statusMeta[$team->status] ?? [$team->status, 'default']; @endphp
                        <tr class="hover:bg-bone-soft transition-colors duration-fast">
                            <td class="px-4 py-3">
                                <a href="{{ route('admin.torneos.equipos.show', [$tournament, $team]) }}"
                                   class="font-display font-bold text-pitch hover:underline">
                                    {{ $team->name }}
                                </a>
                                @if ($team->color)
                                    <span class="inline-block w-3 h-3 rounded-full ml-2 border border-line"
                                          style="background:{{ $team->color }}"></span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-[13px]">{{ $team->captain?->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-center font-mono">{{ $team->players_count }}</td>
                            <td class="px-4 py-3 text-center">
                                <x-badge :variant="$variant">{{ $label }}</x-badge>
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <a href="{{ route('admin.torneos.equipos.show', [$tournament, $team]) }}"
                                   class="text-pitch font-display font-semibold text-[13px] uppercase hover:underline mr-3">Ver</a>

                                @if ($team->isPending() || $team->isRejected())
                                    <form method="POST"
                                          action="{{ route('admin.torneos.equipos.approve', [$tournament, $team]) }}"
                                          class="inline">
                                        @csrf @method('PATCH')
                                        <button type="submit"
                                                class="text-gol-deep font-display font-semibold text-[13px] uppercase hover:underline mr-2">
                                            Aprobar
                                        </button>
                                    </form>
                                @endif

                                @if ($team->isPending() || $team->isApproved())
                                    <form method="POST"
                                          action="{{ route('admin.torneos.equipos.reject', [$tournament, $team]) }}"
                                          class="inline">
                                        @csrf @method('PATCH')
                                        <button type="submit"
                                                class="text-alerta font-display font-semibold text-[13px] uppercase hover:underline">
                                            Rechazar
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection
