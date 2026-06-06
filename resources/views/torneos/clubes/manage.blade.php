@extends('layouts.app')
@section('title', 'Gestionar · ' . $club->name)

@section('content')
<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

    <nav class="flex items-center gap-2 font-mono text-[12px] text-ink-mute mb-5">
        <a href="{{ route('torneos.mis-equipos') }}" class="hover:text-pitch">Mis Equipos</a>
        <span>›</span>
        <span class="text-pitch font-semibold">{{ $club->name }}</span>
    </nav>

    @if (session('status'))
        <div class="mb-4 bg-gol/20 border border-gol text-pitch-deep px-4 py-3 rounded-md font-display font-semibold">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-4 bg-alerta/15 border border-alerta text-alerta-deep px-4 py-3 rounded-md font-display font-semibold">{{ session('error') }}</div>
    @endif

    {{-- Identidad del equipo --}}
    <div class="bg-white border border-line rounded-md shadow-card-2 p-5 sm:p-6 mb-6">
        <div class="flex items-start gap-4">
            <x-avatar :name="$club->name" :src="$club->shield_url" size="xl" />
            <div class="min-w-0 flex-1">
                <h1 class="font-display font-bold text-display-s sm:text-display-m text-pitch uppercase break-words">{{ $club->name }}</h1>
                <p class="font-mono text-[12px] text-ink-mute mt-1">Capitán: <span class="text-pitch font-semibold">{{ $club->captain?->name ?? '—' }}</span></p>
            </div>
        </div>

        @if ($lockedForEdit)
            <div class="mt-4 bg-bone-soft border border-line rounded-md px-4 py-3 text-[13px] text-ink-soft">
                El nombre, color y escudo no se pueden cambiar mientras el equipo participa en un torneo activo.
            </div>
        @else
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-5 pt-5 border-t border-line-soft">
                {{-- Nombre / color --}}
                <form method="POST" action="{{ route('torneos.clubes.update', $club) }}" class="space-y-3">
                    @csrf @method('PATCH')
                    <div class="flex flex-col gap-1">
                        <label class="font-mono text-[10.5px] tracking-wide-label uppercase text-ink-mute">Nombre</label>
                        <input type="text" name="name" value="{{ old('name', $club->name) }}" required minlength="3" maxlength="80"
                               class="h-[40px] px-3 bg-white border-[1.5px] {{ $errors->has('name') ? 'border-alerta' : 'border-line' }} rounded-md text-[14px] focus:border-pitch focus:ring-0">
                        @error('name') <p class="text-[12px] text-alerta">{{ $message }}</p> @enderror
                    </div>
                    <div class="flex items-end gap-3">
                        <div class="flex flex-col gap-1 w-28">
                            <label class="font-mono text-[10.5px] tracking-wide-label uppercase text-ink-mute">Color</label>
                            <input type="color" name="color" value="{{ old('color', $club->color ?? '#1f6f43') }}"
                                   class="h-[40px] w-full bg-white border-[1.5px] border-line rounded-md cursor-pointer">
                        </div>
                        <x-btn type="submit" variant="primary" size="sm">Guardar</x-btn>
                    </div>
                </form>

                {{-- Escudo --}}
                <form method="POST" action="{{ route('torneos.clubes.shield', $club) }}" enctype="multipart/form-data" class="space-y-2">
                    @csrf
                    <label class="font-mono text-[10.5px] tracking-wide-label uppercase text-ink-mute">Escudo (JPG/PNG/WEBP · máx 2 MB)</label>
                    <input type="file" name="shield" accept="image/jpeg,image/png,image/webp" required
                           class="block text-[13px] file:mr-3 file:py-2 file:px-4 file:rounded-md file:border-0 file:bg-pitch file:text-bone file:font-display file:font-semibold file:uppercase file:text-[12px] file:cursor-pointer">
                    @error('shield') <p class="text-[12px] text-alerta">{{ $message }}</p> @enderror
                    <x-btn type="submit" variant="primary" size="sm">Subir escudo</x-btn>
                </form>
            </div>
        @endif
    </div>

    {{-- Plantilla permanente --}}
    <section x-data="{ mode: 'registered' }" class="bg-white border border-line rounded-md shadow-card-2 overflow-hidden mb-6">
        <div class="bg-pitch-mist border-b border-line px-4 py-3">
            <p class="font-mono text-[11px] tracking-wide-label uppercase text-pitch">Plantilla ({{ $players->count() }})</p>
        </div>

        {{-- Alta de jugador --}}
        <div class="border-b border-line-soft bg-bone-soft px-4 py-4">
            <div class="flex gap-2 mb-4">
                <button type="button" @click="mode = 'registered'"
                        :class="mode === 'registered' ? 'bg-pitch text-bone' : 'bg-white text-pitch border border-line'"
                        class="px-3 py-1.5 rounded-md font-display font-semibold text-[12px] uppercase tracking-wide-label">Con cuenta</button>
                <button type="button" @click="mode = 'guest'"
                        :class="mode === 'guest' ? 'bg-pitch text-bone' : 'bg-white text-pitch border border-line'"
                        class="px-3 py-1.5 rounded-md font-display font-semibold text-[12px] uppercase tracking-wide-label">Sin cuenta (por verificar)</button>
            </div>

            <form x-show="mode === 'registered'" method="POST" action="{{ route('torneos.clubes.players.add', $club) }}"
                  class="flex flex-wrap items-end gap-3"
                  x-data="{
                      query: '', results: [], selected: null, open: false, searching: false,
                      async search() {
                          if (this.query.length < 2) { this.results = []; this.open = false; return; }
                          this.searching = true;
                          try {
                              const res = await fetch(`{{ route('torneos.jugadores.buscar') }}?q=${encodeURIComponent(this.query)}`, { headers: { 'Accept': 'application/json' } });
                              this.results = await res.json();
                              this.open = true;
                          } finally { this.searching = false; }
                      },
                      choose(r) { this.selected = r; this.query = r.name; this.open = false; },
                      clearSel() { this.selected = null; }
                  }">
                @csrf
                <input type="hidden" name="user_id" :value="selected ? selected.id : ''">
                <div class="flex flex-col gap-1 flex-1 min-w-[220px] relative">
                    <label class="font-mono text-[10.5px] tracking-wide-label uppercase text-ink-mute">Buscar jugador por nombre *</label>
                    <input type="text" x-model="query" @input.debounce.300ms="clearSel(); search()" @focus="open = results.length > 0"
                           autocomplete="off" placeholder="Ej: Fabian Pachón"
                           class="h-[40px] px-3 bg-white border-[1.5px] {{ $errors->has('user_id') ? 'border-alerta' : 'border-line' }} rounded-md text-[14px] focus:border-pitch focus:ring-0">
                    {{-- Sugerencias --}}
                    <div x-show="open && results.length" x-cloak @click.outside="open = false"
                         class="absolute top-full left-0 z-30 mt-1 w-full bg-white border border-line rounded-md shadow-card-2 max-h-60 overflow-y-auto">
                        <template x-for="r in results" :key="r.id">
                            <button type="button" @click="choose(r)" class="w-full text-left px-3 py-2 hover:bg-bone-soft border-b border-line-soft last:border-0">
                                <span class="font-semibold text-pitch text-[14px]" x-text="r.name"></span>
                                <span class="font-mono text-[11px] text-ink-mute block" x-text="r.email"></span>
                            </button>
                        </template>
                    </div>
                    <p x-show="query.length >= 2 && open && results.length === 0 && !searching" x-cloak class="text-[12px] text-ink-mute mt-1">Sin coincidencias por ese nombre.</p>
                    <p x-show="selected" x-cloak class="text-[12px] text-gol-deep mt-1">Seleccionado: <span class="font-semibold" x-text="selected ? selected.name : ''"></span></p>
                    @error('user_id') <p class="text-[12px] text-alerta">{{ $message }}</p> @enderror
                </div>
                <div class="flex flex-col gap-1 w-20">
                    <label class="font-mono text-[10.5px] tracking-wide-label uppercase text-ink-mute">Dorsal</label>
                    <input type="number" name="jersey_number" min="1" max="99" class="h-[40px] px-3 bg-white border-[1.5px] border-line rounded-md text-[14px] font-mono focus:border-pitch focus:ring-0">
                </div>
                <div class="flex flex-col gap-1 w-32">
                    <label class="font-mono text-[10.5px] tracking-wide-label uppercase text-ink-mute">Posición</label>
                    <input type="text" name="position" maxlength="30" placeholder="Delantero" class="h-[40px] px-3 bg-white border-[1.5px] border-line rounded-md text-[14px] focus:border-pitch focus:ring-0">
                </div>
                <x-btn type="submit" variant="primary" size="sm">Agregar</x-btn>
            </form>

            <form x-show="mode === 'guest'" x-cloak method="POST" action="{{ route('torneos.clubes.players.addGuest', $club) }}" class="flex flex-wrap items-end gap-3">
                @csrf
                <div class="flex flex-col gap-1 flex-1 min-w-[180px]">
                    <label class="font-mono text-[10.5px] tracking-wide-label uppercase text-ink-mute">Nombre completo *</label>
                    <input type="text" name="full_name" value="{{ old('full_name') }}" maxlength="120" placeholder="Juan Pérez"
                           class="h-[40px] px-3 bg-white border-[1.5px] {{ $errors->has('full_name') ? 'border-alerta' : 'border-line' }} rounded-md text-[14px] focus:border-pitch focus:ring-0">
                    @error('full_name') <p class="text-[12px] text-alerta">{{ $message }}</p> @enderror
                </div>
                <div class="flex flex-col gap-1 w-32">
                    <label class="font-mono text-[10.5px] tracking-wide-label uppercase text-ink-mute">Documento</label>
                    <input type="text" name="document" value="{{ old('document') }}" maxlength="40" placeholder="Opcional"
                           class="h-[40px] px-3 bg-white border-[1.5px] {{ $errors->has('document') ? 'border-alerta' : 'border-line' }} rounded-md text-[14px] font-mono focus:border-pitch focus:ring-0">
                    @error('document') <p class="text-[12px] text-alerta">{{ $message }}</p> @enderror
                </div>
                <div class="flex flex-col gap-1 w-20">
                    <label class="font-mono text-[10.5px] tracking-wide-label uppercase text-ink-mute">Dorsal</label>
                    <input type="number" name="jersey_number" min="1" max="99" class="h-[40px] px-3 bg-white border-[1.5px] border-line rounded-md text-[14px] font-mono focus:border-pitch focus:ring-0">
                </div>
                <x-btn type="submit" variant="primary" size="sm">Agregar</x-btn>
            </form>
            <p class="font-mono text-[11px] text-ink-mute mt-2">Si el equipo ya está jugando un torneo en curso, los jugadores nuevos quedan pendientes de aprobación del organizador de ese torneo.</p>
        </div>

        {{-- Lista --}}
        <ul class="divide-y divide-line-soft">
            @foreach ($players as $cp)
                <li x-data="{ confirming: false }" class="flex items-center justify-between px-4 py-3 hover:bg-bone-soft">
                    <div class="flex items-center gap-3 min-w-0">
                        <span class="font-mono text-[13px] text-ink-mute w-8 text-right shrink-0">{{ $cp->jersey_number ? '#' . $cp->jersey_number : '—' }}</span>
                        <x-avatar :user="$cp->user" :name="$cp->displayName()" size="sm" />
                        <div class="min-w-0">
                            <p class="font-semibold text-[14px] text-pitch">{{ $cp->displayName() }}</p>
                            <p class="font-mono text-[11px] text-ink-mute flex items-center gap-1.5">
                                {{ $cp->position ?? 'Sin posición' }}
                                @if ($cp->isCaptain()) <x-badge variant="win">Capitán</x-badge> @endif
                                @if ($cp->isPorVerificar()) <x-badge variant="upcoming">Por verificar</x-badge> @endif
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center gap-2 shrink-0">
                        {{-- Asignar capitán --}}
                        @if (! $cp->isCaptain() && $cp->user_id && $cp->isRegistered())
                            <form method="POST" action="{{ route('torneos.clubes.captain', $club) }}">
                                @csrf @method('PATCH')
                                <input type="hidden" name="user_id" value="{{ $cp->user_id }}">
                                <button type="submit" class="font-display font-semibold text-[12px] uppercase text-pitch hover:underline tracking-wide-cta">Hacer capitán</button>
                            </form>
                        @endif
                        {{-- Quitar --}}
                        @unless ($cp->isCaptain())
                            <span class="text-ink-mute">·</span>
                            <template x-if="!confirming">
                                <button type="button" @click="confirming = true" class="font-display font-semibold text-[12px] uppercase text-alerta hover:underline tracking-wide-cta">Quitar</button>
                            </template>
                            <template x-if="confirming">
                                <span class="flex items-center gap-2">
                                    <form method="POST" action="{{ route('torneos.clubes.players.remove', [$club, $cp]) }}">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="font-display font-bold text-[12px] uppercase text-alerta hover:underline tracking-wide-cta">Sí, quitar</button>
                                    </form>
                                    <button type="button" @click="confirming = false" class="font-display font-semibold text-[12px] uppercase text-pitch hover:underline tracking-wide-cta">No</button>
                                </span>
                            </template>
                        @endunless
                    </div>
                </li>
            @endforeach
        </ul>
    </section>
</div>
@endsection
