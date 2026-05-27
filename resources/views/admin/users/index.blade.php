@extends('layouts.app')
@section('title', 'Admin · Usuarios')

@section('content')
@include('admin._nav')

<div class="max-w-7xl mx-auto px-4 py-6">
    <h1 class="text-2xl font-bold text-pachon-green mb-4">👥 Usuarios</h1>

    <form method="GET" class="mb-4">
        <input type="search" name="q" value="{{ $search }}"
               placeholder="Buscar por nombre o email..."
               class="w-full sm:w-96 rounded-md border-gray-300 focus:ring-pachon-green focus:border-pachon-green">
    </form>

    <div class="bg-white rounded-lg shadow overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-100 text-left text-xs uppercase text-gray-600">
                <tr>
                    <th class="px-3 py-2">#</th>
                    <th class="px-3 py-2">Nombre</th>
                    <th class="px-3 py-2">Email</th>
                    <th class="px-3 py-2">📱 Teléfono</th>
                    <th class="px-3 py-2">Rol</th>
                    <th class="px-3 py-2">Estado</th>
                    <th class="px-3 py-2 text-right">Pts</th>
                    <th class="px-3 py-2 text-right">Pos</th>
                    <th class="px-3 py-2">Registrado</th>
                    <th class="px-3 py-2 text-right">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse ($rows as $u)
                    <tr>
                        <td class="px-3 py-2 text-xs text-gray-500">{{ $u->id }}</td>
                        <td class="px-3 py-2 font-medium">{{ $u->name }}</td>
                        <td class="px-3 py-2 text-xs text-gray-600">{{ $u->email }}</td>
                        <td class="px-3 py-2 text-xs font-mono text-gray-600">{{ $u->phone_whatsapp ?? '—' }}</td>
                        <td class="px-3 py-2">
                            @if ($u->role === 'admin')
                                <span class="bg-pachon-gold/30 text-pachon-green-dark px-2 py-0.5 rounded text-xs font-bold">admin</span>
                            @else
                                <span class="text-xs text-gray-600">user</span>
                            @endif
                        </td>
                        <td class="px-3 py-2">
                            @if ($u->is_active)
                                <span class="bg-green-100 text-green-700 px-2 py-0.5 rounded text-xs">Activo</span>
                            @else
                                <span class="bg-amber-100 text-amber-700 px-2 py-0.5 rounded text-xs">Pendiente código</span>
                            @endif
                        </td>
                        <td class="px-3 py-2 text-right font-mono">{{ $u->total_points ?? '—' }}</td>
                        <td class="px-3 py-2 text-right">{{ $u->current_position ?? '—' }}</td>
                        <td class="px-3 py-2 text-xs text-gray-500">{{ $u->created_at ? \Carbon\Carbon::parse($u->created_at)->locale('es')->isoFormat('D MMM YYYY') : '—' }}</td>
                        <td class="px-3 py-2 text-right whitespace-nowrap">
                            <a href="{{ route('ranking.show', $u->id) }}" target="_blank"
                               class="text-xs text-pachon-green hover:underline mr-2">Ver pronósticos</a>
                            <form method="POST" action="{{ route('admin.users.toggle', $u->id) }}" class="inline">
                                @csrf @method('PATCH')
                                <button type="submit" class="text-xs {{ $u->is_active ? 'text-red-600' : 'text-green-700' }} hover:underline">
                                    {{ $u->is_active ? 'Desactivar' : 'Activar' }}
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="10" class="px-3 py-6 text-center text-gray-500 italic">No hay usuarios que coincidan.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
