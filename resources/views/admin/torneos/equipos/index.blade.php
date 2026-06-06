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

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8"
     x-data="{ createOpen: false, assignFor: null }">

    {{-- Breadcrumb --}}
    <nav class="flex items-center gap-2 font-mono text-[12px] text-ink-mute mb-5">
        <a href="{{ route('admin.torneos.show', $tournament) }}" class="hover:text-pitch">{{ $tournament->name }}</a>
        <span>›</span>
        <span class="text-pitch font-semibold">Equipos</span>
    </nav>

    <div class="flex items-center justify-between gap-4 mb-6">
        <div>
            <p class="eyebrow">{{ $tournament->name }}</p>
            <h1 class="font-display font-bold text-display-m text-pitch uppercase mt-1">Equipos inscritos</h1>
        </div>
        <div class="flex items-center gap-3">
            <x-badge variant="default">{{ $teams->count() }} equipos</x-badge>
            @if (in_array($tournament->status, ['draft', 'open']))
                <x-btn type="button" variant="primary" size="sm" x-on:click="createOpen = true">+ Crear equipo</x-btn>
            @endif
        </div>
    </div>

    @if (session('status'))
        <div class="mb-4 bg-gol/20 border border-gol text-pitch-deep px-4 py-3 rounded-md font-display font-semibold">
            {{ session('status') }}
        </div>
    @endif
    @if (session('error'))
        <div class="mb-4 bg-alerta/10 border border-alerta text-alerta px-4 py-3 rounded-md font-display font-semibold">
            {{ session('error') }}
        </div>
    @endif

    {{-- ── Modal: crear equipo (H7) ──────────────────────────────────────────── --}}
    @if (in_array($tournament->status, ['draft', 'open']))
        <div x-show="createOpen" x-cloak x-transition.opacity
             class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 px-4"
             @click.self="createOpen = false" @keydown.escape.window="createOpen = false">
            <div class="bg-white rounded-lg shadow-modal w-full max-w-md overflow-hidden">
                <div class="bg-pitch px-5 py-4 flex items-center justify-between">
                    <p class="font-display font-extrabold text-bone uppercase text-[15px]">Crear equipo</p>
                    <button type="button" @click="createOpen = false" class="text-bone/80 hover:text-bone text-2xl leading-none">&times;</button>
                </div>
                <form method="POST" action="{{ route('admin.torneos.equipos.create', $tournament) }}" class="p-5 space-y-4">
                    @csrf
                    <div class="flex flex-col gap-1.5">
                        <label for="new_name" class="font-mono text-[11px] tracking-wide-label uppercase text-ink-soft">Nombre del equipo *</label>
                        <input type="text" name="name" id="new_name" required minlength="3" maxlength="80"
                               class="h-[44px] px-3.5 bg-white border-[1.5px] border-line rounded-md text-[15px] focus:border-pitch focus:ring-0">
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label for="new_color" class="font-mono text-[11px] tracking-wide-label uppercase text-ink-soft">Color (opcional)</label>
                        <input type="text" name="color" id="new_color" maxlength="20" placeholder="#1B7F4C o 'Azul'"
                               class="h-[44px] px-3.5 bg-white border-[1.5px] border-line rounded-md text-[15px] focus:border-pitch focus:ring-0">
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label for="new_captain" class="font-mono text-[11px] tracking-wide-label uppercase text-ink-soft">Email del capitán (opcional)</label>
                        <input type="email" name="captain_email" id="new_captain" maxlength="120" placeholder="capitan@email.com"
                               class="h-[44px] px-3.5 bg-white border-[1.5px] border-line rounded-md text-[15px] focus:border-pitch focus:ring-0">
                        <p class="text-[11px] text-ink-mute">Si lo dejás vacío, el equipo queda <strong>por validar</strong> hasta asignarle un capitán.</p>
                    </div>
                    <div class="flex justify-end gap-2 pt-2">
                        <x-btn type="button" variant="ghost" size="sm" x-on:click="createOpen = false">Cancelar</x-btn>
                        <x-btn type="submit" variant="primary" size="sm">Crear e inscribir</x-btn>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @if ($teams->isEmpty())
        <div class="bg-white border border-line rounded-md shadow-card-2 p-10 text-center">
            <p class="text-ink-soft text-lg">Todavía no hay equipos inscritos en este torneo.</p>
        </div>
    @else
        <div class="bg-white border border-line rounded-md shadow-card-2 overflow-x-auto">
            <table class="w-full min-w-[480px] text-left">
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
                            <td class="px-4 py-3 text-[13px]">
                                @if ($team->captain)
                                    {{ $team->captain->name }}
                                @elseif ($team->club && $team->club->isPorValidar())
                                    <span class="font-mono text-[11px] uppercase tracking-wide-label text-alerta">Por validar</span>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-4 py-3 text-center font-mono">{{ $team->players_count }}</td>
                            <td class="px-4 py-3 text-center">
                                <x-badge :variant="$variant">{{ $label }}</x-badge>
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <a href="{{ route('admin.torneos.equipos.show', [$tournament, $team]) }}"
                                   class="text-pitch font-display font-semibold text-[13px] uppercase hover:underline mr-3">Ver</a>

                                {{-- H7: asignar capitán a un equipo "por validar". --}}
                                @if ($team->club && $team->club->isPorValidar())
                                    <button type="button"
                                            x-on:click="assignFor = {{ $team->id }}"
                                            class="text-pitch font-display font-semibold text-[13px] uppercase hover:underline mr-3">
                                        Asignar capitán
                                    </button>
                                @endif

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

    {{-- ── Modal: asignar capitán a equipo "por validar" (H7) ────────────────── --}}
    @foreach ($teams as $team)
        @if ($team->club && $team->club->isPorValidar())
            <div x-show="assignFor === {{ $team->id }}" x-cloak x-transition.opacity
                 class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 px-4"
                 @click.self="assignFor = null" @keydown.escape.window="assignFor = null">
                <div class="bg-white rounded-lg shadow-modal w-full max-w-md overflow-hidden">
                    <div class="bg-pitch px-5 py-4 flex items-center justify-between">
                        <p class="font-display font-extrabold text-bone uppercase text-[15px]">Asignar capitán</p>
                        <button type="button" @click="assignFor = null" class="text-bone/80 hover:text-bone text-2xl leading-none">&times;</button>
                    </div>
                    <form method="POST" action="{{ route('admin.torneos.equipos.assignCaptain', [$tournament, $team]) }}" class="p-5 space-y-4">
                        @csrf @method('PATCH')
                        <p class="text-[13px] text-ink-soft">
                            Asigná un capitán (usuario registrado) al equipo
                            <strong class="text-pitch">{{ $team->name }}</strong>. El equipo quedará validado.
                        </p>
                        <div class="flex flex-col gap-1.5">
                            <label for="cap_{{ $team->id }}" class="font-mono text-[11px] tracking-wide-label uppercase text-ink-soft">Email del capitán *</label>
                            <input type="email" name="captain_email" id="cap_{{ $team->id }}" required maxlength="120" placeholder="capitan@email.com"
                                   class="h-[44px] px-3.5 bg-white border-[1.5px] border-line rounded-md text-[15px] focus:border-pitch focus:ring-0">
                        </div>
                        <div class="flex justify-end gap-2 pt-2">
                            <x-btn type="button" variant="ghost" size="sm" x-on:click="assignFor = null">Cancelar</x-btn>
                            <x-btn type="submit" variant="primary" size="sm">Asignar y validar</x-btn>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    @endforeach
</div>
@endsection
