@extends('layouts.app')
@section('title', $team->name . ' · ' . $tournament->name)

@section('content')
<div class="max-w-3xl mx-auto px-4 py-10">

    @php
        $isCaptain  = $team->captain_user_id === auth()->id();
        $canManage  = $isCaptain && $tournament->isOpen();
        $statusMeta = [
            'pending'  => ['Pendiente aprobación', 'upcoming'],
            'approved' => ['Aprobado',             'win'],
            'rejected' => ['Rechazado',             'default'],
        ];
        [$label, $variant] = $statusMeta[$team->status] ?? [$team->status, 'default'];
    @endphp

    @if (session('status'))
        <div class="mb-4 bg-gol/20 border border-gol text-pitch-deep px-4 py-3 rounded-md font-display font-semibold">
            {{ session('status') }}
        </div>
    @endif
    @if (session('error'))
        <div class="mb-4 bg-alerta/15 border border-alerta text-alerta-deep px-4 py-3 rounded-md font-display font-semibold">
            {{ session('error') }}
        </div>
    @endif

    {{-- Encabezado --}}
    <div class="flex items-start gap-4 mb-6">
        @if ($team->color)
            <div class="w-12 h-12 rounded-full border border-line flex-shrink-0 mt-1"
                 style="background:{{ $team->color }}"></div>
        @endif
        <div>
            <h1 class="font-display font-bold text-display-m text-pitch uppercase">{{ $team->name }}</h1>
            <div class="flex items-center gap-2 mt-1">
                <x-badge :variant="$variant">{{ $label }}</x-badge>
                <span class="font-mono text-[12px] text-ink-mute">{{ $tournament->name }}</span>
            </div>
        </div>
    </div>

    {{-- Lista de jugadores + gestión --}}
    <div x-data="{
            showAddForm: false,
            emailError: '',
            resetForm() { this.showAddForm = false; this.emailError = ''; }
         }"
         class="bg-white border border-line rounded-md shadow-card-2 overflow-hidden">

        <div class="bg-pitch-mist border-b border-line px-4 py-3 flex items-center justify-between">
            <p class="font-mono text-[11px] tracking-wide-label uppercase text-pitch">
                Jugadores ({{ $team->players->count() }})
            </p>
            @if ($canManage)
                <button type="button" @click="showAddForm = !showAddForm"
                        class="font-display font-bold text-[13px] uppercase text-pitch hover:underline tracking-wide-cta">
                    + Agregar jugador
                </button>
            @endif
        </div>

        {{-- Formulario inline para agregar jugador --}}
        @if ($canManage)
            <div x-show="showAddForm" x-cloak class="border-b border-line-soft bg-bone-soft px-4 py-4">
                <form method="POST" action="{{ route('torneos.equipo.players.add', $tournament) }}"
                      class="flex flex-wrap items-end gap-3">
                    @csrf

                    <div class="flex flex-col gap-1 flex-1 min-w-[200px]">
                        <label class="font-mono text-[10.5px] tracking-wide-label uppercase text-ink-mute">
                            Email del jugador *
                        </label>
                        <input type="email" name="email" required
                               value="{{ old('email') }}"
                               placeholder="jugador@email.com"
                               class="h-[40px] px-3 bg-white border-[1.5px] {{ $errors->has('email') ? 'border-alerta' : 'border-line' }} rounded-md text-[14px] focus:border-pitch focus:ring-0">
                        @error('email')
                            <p class="text-[12px] text-alerta">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex flex-col gap-1 w-20">
                        <label class="font-mono text-[10.5px] tracking-wide-label uppercase text-ink-mute">
                            Dorsal
                        </label>
                        <input type="number" name="jersey_number" min="1" max="99"
                               value="{{ old('jersey_number') }}"
                               class="h-[40px] px-3 bg-white border-[1.5px] border-line rounded-md text-[14px] font-mono focus:border-pitch focus:ring-0">
                    </div>

                    <div class="flex flex-col gap-1 w-32">
                        <label class="font-mono text-[10.5px] tracking-wide-label uppercase text-ink-mute">
                            Posición
                        </label>
                        <input type="text" name="position" maxlength="30"
                               value="{{ old('position') }}"
                               placeholder="Delantero"
                               class="h-[40px] px-3 bg-white border-[1.5px] border-line rounded-md text-[14px] focus:border-pitch focus:ring-0">
                    </div>

                    <div class="flex gap-2">
                        <x-btn type="submit" variant="primary" size="sm">Agregar</x-btn>
                        <button type="button" @click="resetForm()"
                                class="px-3.5 py-2 text-[13px] font-display font-bold uppercase tracking-wide-cta text-pitch border border-pitch rounded-md hover:bg-pitch hover:text-bone transition-all duration-fast">
                            Cancelar
                        </button>
                    </div>
                </form>
            </div>
        @endif

        {{-- Lista de jugadores --}}
        @if ($team->players->isEmpty())
            <div class="p-8 text-center text-ink-soft">Sin jugadores aún.</div>
        @else
            <ul class="divide-y divide-line-soft">
                @foreach ($team->players as $tp)
                    <li x-data="{ confirming: false }"
                        class="flex items-center justify-between px-4 py-3 hover:bg-bone-soft">
                        <div class="flex items-center gap-3">
                            <span class="font-mono text-[13px] text-ink-mute w-8 text-right">
                                {{ $tp->jersey_number ? '#' . $tp->jersey_number : '—' }}
                            </span>
                            <div>
                                <p class="font-semibold text-[14px]">{{ $tp->user->name }}</p>
                                <p class="font-mono text-[11px] text-ink-mute">
                                    {{ $tp->position ?? '' }}
                                    @if ($tp->user_id === $team->captain_user_id)
                                        <x-badge variant="win">Capitán</x-badge>
                                    @endif
                                </p>
                            </div>
                        </div>

                        {{-- Quitar jugador (solo capitán, solo si no es él mismo) --}}
                        @if ($canManage && $tp->user_id !== $team->captain_user_id)
                            <div>
                                <template x-if="!confirming">
                                    <button type="button" @click="confirming = true"
                                            class="font-display font-semibold text-[12px] uppercase text-alerta hover:underline tracking-wide-cta">
                                        Quitar
                                    </button>
                                </template>
                                <template x-if="confirming">
                                    <div class="flex items-center gap-2">
                                        <span class="text-[12px] text-ink-soft">¿Quitar?</span>
                                        <form method="POST"
                                              action="{{ route('torneos.equipo.players.remove', [$tournament, $tp]) }}">
                                            @csrf @method('DELETE')
                                            <button type="submit"
                                                    class="font-display font-bold text-[12px] uppercase text-alerta hover:underline tracking-wide-cta">
                                                Sí
                                            </button>
                                        </form>
                                        <button type="button" @click="confirming = false"
                                                class="font-display font-semibold text-[12px] uppercase text-pitch hover:underline tracking-wide-cta">
                                            No
                                        </button>
                                    </div>
                                </template>
                            </div>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    @if (! $canManage && $isCaptain)
        <p class="mt-4 text-[13px] text-ink-mute text-center">
            La gestión de jugadores solo está disponible mientras el torneo está en inscripción.
        </p>
    @endif
</div>
@endsection
