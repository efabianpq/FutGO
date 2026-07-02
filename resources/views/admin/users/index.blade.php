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
                    <th class="px-3 py-2.5"><x-icon name="phone" class="w-3.5 h-3.5 inline" /> Teléfono</th>
                    <th class="px-3 py-2.5">Rol</th>
                    <th class="px-3 py-2.5">Registrado</th>
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
                        <td class="px-3 py-2.5 font-mono text-[11px] text-ink-mute">{{ $u->created_at ? \Carbon\Carbon::parse($u->created_at)->locale('es')->isoFormat('D MMM YY') : '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-3 py-10 text-center font-body text-body-s text-ink-mute italic">No hay usuarios que coincidan.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
