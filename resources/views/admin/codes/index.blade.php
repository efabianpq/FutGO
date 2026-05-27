@extends('layouts.app')
@section('title', 'Admin · Códigos')

@section('content')
@include('admin._nav')

<div class="max-w-5xl mx-auto px-4 py-8" x-data="{ exportOpen: false, exportText: '' }">
    <p class="eyebrow">Gestión de invitaciones</p>
    <h1 class="font-display font-bold text-display-m text-pitch uppercase mt-2 mb-6">Códigos de invitación</h1>

    {{-- Stats --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-6">
        <x-stat-card label="Disponibles" :value="$availableCount" accent="pitch" />
        <x-stat-card label="Usados" :value="$usedCount" />
        <x-stat-card label="Desactivados" :value="$deactivatedCount" accent="alerta" />
    </div>

    {{-- Acciones --}}
    <div class="bg-white border border-line rounded-md shadow-card p-4 mb-6 flex flex-wrap items-center gap-3">
        <form method="POST" action="{{ route('admin.codes.generate') }}" class="flex items-center gap-2 flex-wrap grow">
            @csrf
            <label class="font-mono text-[11px] tracking-wide-label uppercase text-ink-soft">Generar</label>
            <input type="number" name="quantity" min="1" max="100" value="10"
                   class="w-20 h-10 px-3 text-center font-mono font-bold bg-white border-[1.5px] border-line rounded-md focus:border-pitch focus:ring-0">
            <span class="font-mono text-[11px] tracking-wide-label uppercase text-ink-soft">códigos</span>
            <x-btn type="submit" variant="primary" size="sm">+ Generar SPM-XXXX</x-btn>
        </form>
        <button type="button"
                @click="exportOpen = true; fetch('{{ route('admin.codes.export') }}').then(r => r.text()).then(t => exportText = t)"
                class="font-display font-bold text-[13px] uppercase tracking-wide-cta px-3.5 py-2 rounded-md bg-gol text-pitch hover:bg-gol-deep transition-all duration-fast">
            📋 Exportar disponibles
        </button>
    </div>

    @error('code')<div class="bg-alerta/10 border border-alerta text-alerta px-4 py-2 rounded-md mb-4 font-mono text-[12px]">{{ $message }}</div>@enderror

    {{-- Tabla --}}
    <div class="bg-white border border-line rounded-md shadow-card overflow-x-auto">
        <table class="w-full">
            <thead class="bg-pitch text-bone font-mono text-[10.5px] tracking-wide-label uppercase text-left">
                <tr>
                    <th class="px-4 py-2.5">Código</th>
                    <th class="px-4 py-2.5">Estado</th>
                    <th class="px-4 py-2.5">Usado por</th>
                    <th class="px-4 py-2.5">Fecha uso</th>
                    <th class="px-4 py-2.5 text-right">Acción</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line-soft">
                @forelse ($codes as $c)
                    <tr class="hover:bg-bone-soft transition-colors duration-fast">
                        <td class="px-4 py-3 font-mono font-bold text-body-s text-pitch">{{ $c->code }}</td>
                        <td class="px-4 py-3">
                            @if (! $c->is_active)
                                <x-badge variant="default" class="!bg-alerta/10 !text-alerta">Desactivado</x-badge>
                            @elseif ($c->is_used)
                                <x-badge variant="upcoming">Usado</x-badge>
                            @else
                                <x-badge variant="default">Disponible</x-badge>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-body-s">
                            @if ($c->used_by_name)
                                <p class="font-display font-semibold text-ink">{{ $c->used_by_name }}</p>
                                <p class="font-mono text-[11px] text-ink-mute">{{ $c->used_by_email }}</p>
                            @else
                                <span class="text-ink-mute">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 font-mono text-[11px] tracking-wide-eyebrow uppercase text-ink-mute">{{ $c->used_at ? \Carbon\Carbon::parse($c->used_at)->locale('es')->isoFormat('D MMM YY HH:mm') : '—' }}</td>
                        <td class="px-4 py-3 text-right">
                            @if (! $c->is_used && $c->is_active)
                                <form method="POST" action="{{ route('admin.codes.deactivate', $c->id) }}" class="inline">
                                    @csrf @method('PATCH')
                                    <button type="submit" class="font-mono text-[11px] tracking-wide-label uppercase text-alerta hover:underline">Desactivar</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-10 text-center font-body text-body-s text-ink-mute italic">No hay códigos. Generá algunos arriba.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Modal export --}}
    <div x-show="exportOpen" x-cloak x-transition.opacity
         class="fixed inset-0 bg-pitch/60 flex items-center justify-center p-4 z-50"
         @click.self="exportOpen = false">
        <div class="bg-white rounded-lg shadow-modal p-6 w-full max-w-md">
            <h3 class="font-display font-bold text-display-s text-pitch uppercase mb-2">Códigos disponibles</h3>
            <p class="font-mono text-[11px] tracking-wide-label uppercase text-ink-mute mb-3">Copiá y pegá en WhatsApp o email.</p>
            <textarea readonly x-text="exportText" rows="12"
                      class="w-full font-mono text-body-s rounded-md border-[1.5px] border-line bg-bone-soft p-3 focus:border-pitch focus:ring-0"></textarea>
            <div class="mt-4 flex justify-end gap-2">
                <button type="button" @click="navigator.clipboard.writeText(exportText)"
                        class="font-display font-bold text-[13px] uppercase tracking-wide-cta px-3.5 py-2 rounded-md bg-pitch text-bone hover:bg-pitch-deep transition-all duration-fast">Copiar</button>
                <button type="button" @click="exportOpen = false"
                        class="font-display font-bold text-[13px] uppercase tracking-wide-cta px-3.5 py-2 rounded-md bg-line text-ink hover:bg-line-soft transition-all duration-fast">Cerrar</button>
            </div>
        </div>
    </div>
</div>
@endsection
