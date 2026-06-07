@extends('layouts.app')
@section('title', 'Admin · Torneos')

@section('content')
@include('admin.torneos._nav')

@php
    $statusMeta = [
        'draft'       => ['Borrador',    'upcoming'],
        'open'        => ['Inscripción', 'win'],
        'in_progress' => ['En juego',    'live'],
        'finished'    => ['Finalizado',  'default'],
        'cancelled'   => ['Cancelado',   'default'],
    ];
    $formatLabels = [
        'groups_and_knockout' => 'Grupos + Eliminación',
        'knockout_only'       => 'Solo eliminación',
        'round_robin'         => 'Todos contra todos',
    ];
@endphp

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <div class="min-w-0">
            <p class="eyebrow">Administración</p>
            <h1 class="font-display font-bold text-display-s sm:text-display-m text-pitch uppercase mt-1">Torneos</h1>
        </div>
        <x-btn :href="route('admin.torneos.create')" variant="primary">+ Nuevo torneo</x-btn>
    </div>

    @if (session('error'))
        <div class="mb-4 bg-alerta/15 border border-alerta text-alerta-deep px-4 py-3 rounded-md font-display font-semibold">
            {{ session('error') }}
        </div>
    @endif

    @if ($tournaments->isEmpty())
        <div class="bg-white border border-line rounded-md shadow-card-2 p-10 text-center">
            <p class="text-ink-soft text-lg">Todavía no administrás ningún torneo.</p>
            <div class="mt-4">
                <x-btn :href="route('admin.torneos.create')" variant="accent">Crear el primero</x-btn>
            </div>
        </div>
    @else
        <div class="bg-white border border-line rounded-md shadow-card-2 overflow-x-auto">
            <table class="w-full min-w-[580px] text-left">
                <thead class="bg-pitch-mist border-b border-line">
                    <tr class="font-mono text-[11px] tracking-wide-label uppercase text-pitch">
                        <th class="px-4 py-3">Torneo</th>
                        <th class="px-4 py-3">Deporte</th>
                        <th class="px-4 py-3">Formato</th>
                        <th class="px-4 py-3 text-center">Equipos</th>
                        <th class="px-4 py-3 text-center">Estado</th>
                        <th class="px-4 py-3 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line-soft">
                    @foreach ($tournaments as $t)
                        @php [$label, $variant] = $statusMeta[$t->status] ?? [$t->status, 'default']; @endphp
                        <tr class="hover:bg-bone-soft transition-colors duration-fast">
                            <td class="px-4 py-3">
                                <a href="{{ route('admin.torneos.show', $t) }}" class="font-display font-bold text-pitch hover:underline">
                                    {{ $t->name }}
                                </a>
                                <p class="font-mono text-[11px] text-ink-mute">{{ $t->slug }}</p>
                            </td>
                            <td class="px-4 py-3 capitalize text-ink-soft">{{ $t->sport }}</td>
                            <td class="px-4 py-3 text-ink-soft text-[13px]">{{ $formatLabels[$t->format] ?? $t->format }}</td>
                            <td class="px-4 py-3 text-center font-mono">{{ $t->teams_count }}</td>
                            <td class="px-4 py-3 text-center">
                                <x-badge :variant="$variant">{{ $label }}</x-badge>
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <a href="{{ route('admin.torneos.show', $t) }}" class="text-pitch font-display font-semibold text-[13px] uppercase hover:underline mr-3">Ver</a>
                                <a href="{{ route('admin.torneos.edit', $t) }}" class="text-pitch font-display font-semibold text-[13px] uppercase hover:underline">Editar</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection
