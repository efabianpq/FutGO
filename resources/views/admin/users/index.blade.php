@extends('layouts.app')
@section('title', 'Admin · Usuarios')

@section('content')
@include('admin._nav')

<div class="max-w-7xl mx-auto px-4 py-8">
    <p class="eyebrow">Comunidad</p>
    <h1 class="font-display font-bold text-display-m text-pitch uppercase mt-2 mb-6">Usuarios</h1>

    <form method="GET" class="mb-4">
        <input type="search" name="q" value="{{ $search }}" placeholder="Buscar por nombre o email..."
               class="w-full sm:w-96 h-[46px] px-3.5 bg-white border-[1.5px] border-line rounded-md text-[15px] focus:border-pitch focus:ring-0">
    </form>

    <div class="bg-white border border-line rounded-md shadow-card overflow-x-auto">
        <table class="w-full">
            <thead class="bg-pitch text-bone font-mono text-[10.5px] tracking-wide-label uppercase text-left">
                <tr>
                    <th class="px-3 py-2.5">#</th>
                    <th class="px-3 py-2.5">Nombre</th>
                    <th class="px-3 py-2.5">Email</th>
                    <th class="px-3 py-2.5">📱 Teléfono</th>
                    <th class="px-3 py-2.5">Rol</th>
                    <th class="px-3 py-2.5">Estado</th>
                    <th class="px-3 py-2.5 text-right">Pts</th>
                    <th class="px-3 py-2.5 text-right">Pos</th>
                    <th class="px-3 py-2.5">Registrado</th>
                    <th class="px-3 py-2.5 text-right">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line-soft">
                @forelse ($rows as $u)
                    <tr class="hover:bg-bone-soft transition-colors duration-fast">
                        <td class="px-3 py-2.5 font-mono text-[11px] text-ink-mute">{{ $u->id }}</td>
                        <td class="px-3 py-2.5 font-display font-semibold">{{ $u->name }}</td>
                        <td class="px-3 py-2.5 font-mono text-[11px] text-ink-soft">{{ $u->email }}</td>
                        <td class="px-3 py-2.5 font-mono text-[11px] text-ink-soft">{{ $u->phone_whatsapp ?? '—' }}</td>
                        <td class="px-3 py-2.5">
                            @if ($u->role === 'admin')
                                <x-badge variant="win">admin</x-badge>
                            @else
                                <span class="font-mono text-[11px] text-ink-mute">user</span>
                            @endif
                        </td>
                        <td class="px-3 py-2.5">
                            @if ($u->is_active)
                                <x-badge variant="default">Activo</x-badge>
                            @else
                                <x-badge variant="upcoming">Pendiente</x-badge>
                            @endif
                        </td>
                        <td class="px-3 py-2.5 text-right font-mono font-bold">{{ $u->total_points ?? '—' }}</td>
                        <td class="px-3 py-2.5 text-right font-display font-bold">{{ $u->current_position ?? '—' }}</td>
                        <td class="px-3 py-2.5 font-mono text-[11px] text-ink-mute">{{ $u->created_at ? \Carbon\Carbon::parse($u->created_at)->locale('es')->isoFormat('D MMM YY') : '—' }}</td>
                        <td class="px-3 py-2.5 text-right whitespace-nowrap">
                            <a href="{{ route('ranking.show', $u->id) }}" target="_blank" class="font-mono text-[11px] tracking-wide-label uppercase text-pitch hover:underline mr-3">Ver</a>
                            <form method="POST" action="{{ route('admin.users.toggle', $u->id) }}" class="inline">
                                @csrf @method('PATCH')
                                <button type="submit" class="font-mono text-[11px] tracking-wide-label uppercase {{ $u->is_active ? 'text-alerta' : 'text-pitch' }} hover:underline">
                                    {{ $u->is_active ? 'Desactivar' : 'Activar' }}
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="10" class="px-3 py-10 text-center font-body text-body-s text-ink-mute italic">No hay usuarios que coincidan.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
