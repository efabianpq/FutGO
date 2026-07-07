@extends('layouts.app')
@section('title', 'Patrocinadores · ' . $tournament->name)

@section('content')
<div class="max-w-3xl mx-auto px-4 sm:px-6 py-8" x-data="{ confirmId: null, editId: null }">

    <div class="flex items-center justify-between mb-5">
        <div>
            <p class="eyebrow inline-flex items-center gap-1"><x-icon name="handshake" class="w-3.5 h-3.5" /> Monetización</p>
            <div class="flex items-center gap-2 mt-1">
                <h1 class="font-display font-bold text-display-s sm:text-display-m text-pitch uppercase">Patrocinadores</h1>
                <x-help-hint topic="admin.torneos.sponsors.index" />
            </div>
            <p class="text-[13px] text-ink-soft mt-1">{{ $tournament->name }}</p>
        </div>
        <x-btn :href="route('admin.torneos.show', $tournament)" variant="ghost" size="sm">← Volver</x-btn>
    </div>

    @if (session('status'))
        <div class="mb-4 bg-gol/20 border border-gol text-pitch-deep px-4 py-3 rounded-md font-display font-semibold">{{ session('status') }}</div>
    @endif

    {{-- Alta de patrocinador --}}
    <form method="POST" action="{{ route('admin.torneos.sponsors.store', $tournament) }}"
          enctype="multipart/form-data"
          class="bg-white border border-line rounded-md shadow-card p-5 mb-6 space-y-4">
        @csrf
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="flex flex-col gap-1.5">
                <label for="name" class="font-mono text-[11px] tracking-wide-label uppercase text-ink-soft">Nombre *</label>
                <input id="name" name="name" type="text" required value="{{ old('name') }}"
                       class="h-[44px] px-3 bg-white border-[1.5px] {{ $errors->has('name') ? 'border-alerta' : 'border-line' }} rounded-md text-[15px] focus:border-pitch focus:ring-0">
                @error('name')<p class="text-[12px] text-alerta">{{ $message }}</p>@enderror
            </div>
            <div class="flex flex-col gap-1.5">
                <label for="sort_order" class="font-mono text-[11px] tracking-wide-label uppercase text-ink-soft">Orden</label>
                <input id="sort_order" name="sort_order" type="number" min="0" max="999" value="{{ old('sort_order', 0) }}"
                       class="h-[44px] px-3 bg-white border-[1.5px] border-line rounded-md text-[15px] focus:border-pitch focus:ring-0">
            </div>
            <div class="flex flex-col gap-1.5">
                <label for="logo" class="font-mono text-[11px] tracking-wide-label uppercase text-ink-soft">Logo (imagen)</label>
                <input id="logo" name="logo" type="file" accept="image/png,image/jpeg,image/webp"
                       class="text-[13px] file:mr-3 file:h-[38px] file:px-3 file:rounded-md file:border-0 file:bg-bone-soft file:font-display file:font-bold file:text-pitch h-[44px] px-1 bg-white border-[1.5px] {{ $errors->has('logo') ? 'border-alerta' : 'border-line' }} rounded-md flex items-center">
                @error('logo')<p class="text-[12px] text-alerta">{{ $message }}</p>@enderror
            </div>
            <div class="flex flex-col gap-1.5">
                <label for="link_url" class="font-mono text-[11px] tracking-wide-label uppercase text-ink-soft">Enlace (URL)</label>
                <input id="link_url" name="link_url" type="url" placeholder="https://..." value="{{ old('link_url') }}"
                       class="h-[44px] px-3 bg-white border-[1.5px] {{ $errors->has('link_url') ? 'border-alerta' : 'border-line' }} rounded-md text-[14px] focus:border-pitch focus:ring-0">
                @error('link_url')<p class="text-[12px] text-alerta">{{ $message }}</p>@enderror
            </div>
        </div>
        <div class="flex justify-end">
            <x-btn type="submit" variant="primary">Agregar patrocinador</x-btn>
        </div>
    </form>

    {{-- Lista --}}
    <div class="bg-white border border-line rounded-md shadow-card overflow-hidden">
        <div class="bg-pitch-mist border-b border-line px-4 py-3">
            <p class="font-mono text-[11px] tracking-wide-label uppercase text-pitch">Patrocinadores ({{ $sponsors->count() }})</p>
        </div>
        @forelse ($sponsors as $sponsor)
            <div class="border-b border-line-soft last:border-0">
                <div class="flex items-center gap-3 px-4 py-3">
                    @if ($sponsor->logo_url)
                        <img src="{{ $sponsor->logo_url }}" alt="" class="w-12 h-12 rounded-md object-contain border border-line bg-white shrink-0 {{ $sponsor->is_active ? '' : 'opacity-40' }}">
                    @else
                        <span class="w-12 h-12 rounded-md bg-bone-soft border border-line flex items-center justify-center font-display font-bold text-pitch shrink-0 {{ $sponsor->is_active ? '' : 'opacity-40' }}">{{ mb_strtoupper(mb_substr($sponsor->name, 0, 1)) }}</span>
                    @endif
                    <div class="min-w-0 flex-1">
                        <p class="font-display font-bold text-ink truncate {{ $sponsor->is_active ? '' : 'opacity-50' }}">
                            {{ $sponsor->name }}
                            @unless ($sponsor->is_active)
                                <span class="font-mono text-[10px] uppercase tracking-wide-label text-ink-mute align-middle ml-1">Inactivo</span>
                            @endunless
                        </p>
                        @if ($sponsor->link_url)
                            <a href="{{ $sponsor->link_url }}" target="_blank" rel="noopener" class="font-mono text-[11px] text-gol-deep truncate block">{{ $sponsor->link_url }}</a>
                        @endif
                    </div>

                    <div x-show="confirmId !== {{ $sponsor->id }}" class="flex items-center gap-3 shrink-0">
                        <button type="button" @click="editId = (editId === {{ $sponsor->id }} ? null : {{ $sponsor->id }})"
                                class="font-mono text-[11px] uppercase tracking-wide-label text-ink-mute hover:text-pitch">Editar</button>

                        <form method="POST" action="{{ route('admin.torneos.sponsors.toggle', [$tournament, $sponsor]) }}">
                            @csrf @method('PATCH')
                            <button type="submit" class="font-mono text-[11px] uppercase tracking-wide-label text-ink-mute hover:text-pitch">
                                {{ $sponsor->is_active ? 'Inactivar' : 'Activar' }}
                            </button>
                        </form>

                        <button type="button" @click="confirmId = {{ $sponsor->id }}"
                                class="font-mono text-[11px] uppercase tracking-wide-label text-ink-mute hover:text-alerta">Eliminar</button>
                    </div>

                    <form method="POST" action="{{ route('admin.torneos.sponsors.destroy', [$tournament, $sponsor]) }}"
                          x-show="confirmId === {{ $sponsor->id }}" x-cloak class="flex items-center gap-2 shrink-0">
                        @csrf @method('DELETE')
                        <button type="submit" class="font-mono text-[11px] uppercase tracking-wide-label text-alerta font-bold">Confirmar</button>
                        <button type="button" @click="confirmId = null" class="font-mono text-[11px] uppercase tracking-wide-label text-ink-mute">Cancelar</button>
                    </form>
                </div>

                {{-- Edición inline --}}
                <div x-show="editId === {{ $sponsor->id }}" x-cloak class="px-4 pb-4">
                    <form method="POST" action="{{ route('admin.torneos.sponsors.update', [$tournament, $sponsor]) }}"
                          enctype="multipart/form-data"
                          class="bg-bone-soft border border-line rounded-md p-4 space-y-3">
                        @csrf @method('PUT')
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div class="flex flex-col gap-1.5">
                                <label class="font-mono text-[11px] tracking-wide-label uppercase text-ink-soft">Nombre *</label>
                                <input name="name" type="text" required value="{{ $sponsor->name }}"
                                       class="h-[40px] px-3 bg-white border-[1.5px] border-line rounded-md text-[14px] focus:border-pitch focus:ring-0">
                            </div>
                            <div class="flex flex-col gap-1.5">
                                <label class="font-mono text-[11px] tracking-wide-label uppercase text-ink-soft">Orden</label>
                                <input name="sort_order" type="number" min="0" max="999" value="{{ $sponsor->sort_order }}"
                                       class="h-[40px] px-3 bg-white border-[1.5px] border-line rounded-md text-[14px] focus:border-pitch focus:ring-0">
                            </div>
                            <div class="flex flex-col gap-1.5">
                                <label class="font-mono text-[11px] tracking-wide-label uppercase text-ink-soft">Logo (reemplazar)</label>
                                <input name="logo" type="file" accept="image/png,image/jpeg,image/webp"
                                       class="text-[13px] file:mr-3 file:h-[34px] file:px-3 file:rounded-md file:border-0 file:bg-white file:font-display file:font-bold file:text-pitch h-[40px] px-1 bg-white border-[1.5px] border-line rounded-md flex items-center">
                            </div>
                            <div class="flex flex-col gap-1.5">
                                <label class="font-mono text-[11px] tracking-wide-label uppercase text-ink-soft">Enlace (URL)</label>
                                <input name="link_url" type="url" placeholder="https://..." value="{{ $sponsor->link_url }}"
                                       class="h-[40px] px-3 bg-white border-[1.5px] border-line rounded-md text-[14px] focus:border-pitch focus:ring-0">
                            </div>
                        </div>
                        <div class="flex justify-end gap-2">
                            <button type="button" @click="editId = null" class="font-mono text-[11px] uppercase tracking-wide-label text-ink-mute px-3 py-2">Cancelar</button>
                            <x-btn type="submit" variant="primary" size="sm">Guardar cambios</x-btn>
                        </div>
                    </form>
                </div>
            </div>
        @empty
            <p class="px-4 py-8 text-center text-ink-soft text-[14px]">Todavía no hay patrocinadores. Agregá el primero arriba.</p>
        @endforelse
    </div>

    <p class="text-[12px] text-ink-mute mt-4">
        Los patrocinadores activos se muestran en el portal público del torneo. (Espacio de monetización: sin cobro ni facturación.)
    </p>
</div>
@endsection
