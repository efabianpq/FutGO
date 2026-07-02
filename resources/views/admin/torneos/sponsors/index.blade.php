@extends('layouts.app')
@section('title', 'Patrocinadores · ' . $tournament->name)

@section('content')
<div class="max-w-3xl mx-auto px-4 sm:px-6 py-8" x-data="{ confirmId: null }">

    <div class="flex items-center justify-between mb-5">
        <div>
            <p class="eyebrow inline-flex items-center gap-1"><x-icon name="handshake" class="w-3.5 h-3.5" /> Monetización</p>
            <h1 class="font-display font-bold text-display-s sm:text-display-m text-pitch uppercase mt-1">Patrocinadores</h1>
            <p class="text-[13px] text-ink-soft mt-1">{{ $tournament->name }}</p>
        </div>
        <x-btn :href="route('admin.torneos.show', $tournament)" variant="ghost" size="sm">← Volver</x-btn>
    </div>

    @if (session('status'))
        <div class="mb-4 bg-gol/20 border border-gol text-pitch-deep px-4 py-3 rounded-md font-display font-semibold">{{ session('status') }}</div>
    @endif

    {{-- Alta de patrocinador --}}
    <form method="POST" action="{{ route('admin.torneos.sponsors.store', $tournament) }}"
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
                <label for="logo_url" class="font-mono text-[11px] tracking-wide-label uppercase text-ink-soft">Logo (URL)</label>
                <input id="logo_url" name="logo_url" type="url" placeholder="https://..." value="{{ old('logo_url') }}"
                       class="h-[44px] px-3 bg-white border-[1.5px] {{ $errors->has('logo_url') ? 'border-alerta' : 'border-line' }} rounded-md text-[14px] focus:border-pitch focus:ring-0">
                @error('logo_url')<p class="text-[12px] text-alerta">{{ $message }}</p>@enderror
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
            <div class="flex items-center gap-3 px-4 py-3 border-b border-line-soft last:border-0">
                @if ($sponsor->logo_url)
                    <img src="{{ $sponsor->logo_url }}" alt="" class="w-12 h-12 rounded-md object-contain border border-line bg-white shrink-0">
                @else
                    <span class="w-12 h-12 rounded-md bg-bone-soft border border-line flex items-center justify-center font-display font-bold text-pitch shrink-0">{{ mb_strtoupper(mb_substr($sponsor->name, 0, 1)) }}</span>
                @endif
                <div class="min-w-0 flex-1">
                    <p class="font-display font-bold text-ink truncate">{{ $sponsor->name }}</p>
                    @if ($sponsor->link_url)
                        <a href="{{ $sponsor->link_url }}" target="_blank" rel="noopener" class="font-mono text-[11px] text-gol-deep truncate block">{{ $sponsor->link_url }}</a>
                    @endif
                </div>
                <form method="POST" action="{{ route('admin.torneos.sponsors.destroy', [$tournament, $sponsor]) }}"
                      x-show="confirmId === {{ $sponsor->id }}" x-cloak class="flex items-center gap-2">
                    @csrf @method('DELETE')
                    <button type="submit" class="font-mono text-[11px] uppercase tracking-wide-label text-alerta font-bold">Confirmar</button>
                    <button type="button" @click="confirmId = null" class="font-mono text-[11px] uppercase tracking-wide-label text-ink-mute">Cancelar</button>
                </form>
                <button type="button" x-show="confirmId !== {{ $sponsor->id }}" @click="confirmId = {{ $sponsor->id }}"
                        class="font-mono text-[11px] uppercase tracking-wide-label text-ink-mute hover:text-alerta">Eliminar</button>
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
