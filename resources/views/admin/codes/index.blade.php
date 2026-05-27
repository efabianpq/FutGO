@extends('layouts.app')
@section('title', 'Admin · Códigos')

@section('content')
@include('admin._nav')

<div class="max-w-5xl mx-auto px-4 py-6"
     x-data="{ exportOpen: false, exportText: '' }">

    <h1 class="text-2xl font-bold text-pachon-green mb-4">🎟️ Códigos de invitación</h1>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-6">
        <div class="bg-green-50 rounded-lg p-3"><p class="text-xs text-gray-500">Disponibles</p><p class="text-2xl font-bold text-green-700">{{ $availableCount }}</p></div>
        <div class="bg-gray-50 rounded-lg p-3"><p class="text-xs text-gray-500">Usados</p><p class="text-2xl font-bold text-gray-700">{{ $usedCount }}</p></div>
        <div class="bg-red-50 rounded-lg p-3"><p class="text-xs text-gray-500">Desactivados</p><p class="text-2xl font-bold text-red-700">{{ $deactivatedCount }}</p></div>
    </div>

    <div class="bg-white rounded-lg shadow p-4 mb-4 flex flex-wrap items-center gap-3">
        <form method="POST" action="{{ route('admin.codes.generate') }}" class="flex items-center gap-2 grow">
            @csrf
            <label for="quantity" class="text-sm font-medium">Generar</label>
            <input type="number" name="quantity" id="quantity" min="1" max="100" value="10"
                   class="w-24 rounded-md border-gray-300 text-sm">
            <span class="text-sm">códigos</span>
            <button type="submit" class="bg-pachon-green hover:bg-pachon-green-dark text-white px-4 py-2 rounded-md text-sm font-semibold">
                ➕ Generar SPM-XXXX
            </button>
        </form>
        <button type="button"
                @click="exportOpen = true; fetch('{{ route('admin.codes.export') }}').then(r => r.text()).then(t => exportText = t)"
                class="bg-pachon-gold hover:bg-pachon-gold-dark text-white px-4 py-2 rounded-md text-sm font-semibold">
            📋 Exportar disponibles
        </button>
    </div>

    @error('code')
        <div class="bg-red-100 border border-red-300 text-red-700 px-4 py-2 rounded mb-4 text-sm">{{ $message }}</div>
    @enderror

    <div class="bg-white rounded-lg shadow overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-100 text-left text-xs uppercase text-gray-600">
                <tr>
                    <th class="px-3 py-2">Código</th>
                    <th class="px-3 py-2">Estado</th>
                    <th class="px-3 py-2">Usado por</th>
                    <th class="px-3 py-2">Fecha uso</th>
                    <th class="px-3 py-2 text-right">Acción</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse ($codes as $c)
                    <tr>
                        <td class="px-3 py-2 font-mono font-bold">{{ $c->code }}</td>
                        <td class="px-3 py-2">
                            @if (! $c->is_active)
                                <span class="inline-block bg-red-100 text-red-700 px-2 py-0.5 rounded text-xs">Desactivado</span>
                            @elseif ($c->is_used)
                                <span class="inline-block bg-gray-200 text-gray-600 px-2 py-0.5 rounded text-xs">Usado</span>
                            @else
                                <span class="inline-block bg-green-100 text-green-700 px-2 py-0.5 rounded text-xs">Disponible</span>
                            @endif
                        </td>
                        <td class="px-3 py-2 text-gray-700">
                            @if ($c->used_by_name)
                                <div>{{ $c->used_by_name }}</div>
                                <div class="text-xs text-gray-500">{{ $c->used_by_email }}</div>
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                        </td>
                        <td class="px-3 py-2 text-xs text-gray-500">{{ $c->used_at ? \Carbon\Carbon::parse($c->used_at)->locale('es')->isoFormat('D MMM YYYY HH:mm') : '—' }}</td>
                        <td class="px-3 py-2 text-right">
                            @if (! $c->is_used && $c->is_active)
                                <form method="POST" action="{{ route('admin.codes.deactivate', $c->id) }}" class="inline">
                                    @csrf @method('PATCH')
                                    <button type="submit" class="text-xs text-red-600 hover:underline">Desactivar</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-3 py-6 text-center text-gray-500 italic">No hay códigos. Generá algunos arriba.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Modal Exportar -->
    <div x-show="exportOpen" x-cloak x-transition.opacity
         class="fixed inset-0 bg-black/50 flex items-center justify-center p-4 z-50"
         @click.self="exportOpen = false">
        <div class="bg-white rounded-lg p-6 w-full max-w-md">
            <h3 class="font-bold text-lg mb-2">Códigos disponibles</h3>
            <p class="text-xs text-gray-500 mb-3">Copiá y pegá esta lista en WhatsApp o email.</p>
            <textarea readonly x-text="exportText" rows="12"
                      class="w-full font-mono text-sm rounded-md border-gray-300 bg-gray-50"></textarea>
            <div class="mt-3 flex justify-end gap-2">
                <button type="button" @click="navigator.clipboard.writeText(exportText)"
                        class="bg-pachon-green text-white px-3 py-1.5 rounded text-sm">Copiar al portapapeles</button>
                <button type="button" @click="exportOpen = false" class="bg-gray-200 px-3 py-1.5 rounded text-sm">Cerrar</button>
            </div>
        </div>
    </div>
</div>
@endsection
