@extends('layouts.app')
@section('title', 'Oportunidades')

@php
    $levelLabels = [
        'recreativo'    => 'Recreativo',
        'intermedio'    => 'Intermedio',
        'competitivo'   => 'Competitivo',
        'elite_amateur' => 'Élite amateur',
    ];
    $typeBadge = [
        'BUSCAR_RIVAL'    => 'bg-pitch text-bone',
        'BUSCAR_JUGADOR'  => 'bg-gol/20 text-pitch-deep',
        'BUSCAR_REFUERZO' => 'bg-amber-100 text-amber-800',
        'BUSCAR_EQUIPO'   => 'bg-blue-100 text-blue-800',
    ];
@endphp

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

    <div class="flex flex-wrap items-end justify-between gap-4 mb-6">
        <div>
            <p class="eyebrow">FutGO Social</p>
            <h1 class="font-display font-bold text-display-s sm:text-display-m text-pitch uppercase mt-1">Oportunidades</h1>
            <p class="text-ink-soft text-[14px] mt-1">Conseguí rival, jugador o refuerzo — o mostrate disponible para que te convoquen.</p>
        </div>
        @auth
            <div class="flex gap-2">
                <a href="{{ route('social.oportunidades.mine') }}" class="btn btn-secondary btn-sm">Mis oportunidades</a>
                <a href="{{ route('social.oportunidades.express') }}" class="btn btn-secondary btn-sm">⚡ Modo rápido</a>
                <a href="{{ route('social.oportunidades.create') }}" class="btn btn-primary btn-sm">+ Publicar</a>
            </div>
        @else
            <a href="{{ route('login') }}" class="btn btn-primary btn-sm">Ingresá para publicar</a>
        @endauth
    </div>

    @if (session('status'))
        <div class="mb-4 bg-gol/20 border border-gol text-pitch-deep px-4 py-3 rounded-md font-display font-semibold">{{ session('status') }}</div>
    @endif

    {{-- Filtros --}}
    <form method="GET" class="bg-white border border-line rounded-md shadow-card p-4 mb-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
        <div class="flex flex-col gap-1">
            <label class="font-mono text-[10px] tracking-wide-label uppercase text-ink-mute">Tipo</label>
            <select name="tipo" class="h-[40px] px-2 bg-white border-[1.5px] border-line rounded-md text-[14px]">
                <option value="">Todos</option>
                @foreach ($types as $t)
                    <option value="{{ $t }}" @selected(($filters['type'] ?? '') === $t)>{{ \App\Models\Social\Opportunity::TYPE_LABELS[$t] }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex flex-col gap-1">
            <label class="font-mono text-[10px] tracking-wide-label uppercase text-ink-mute">Ciudad</label>
            <input type="text" name="ciudad" value="{{ $filters['city'] ?? '' }}" placeholder="Asunción"
                   class="h-[40px] px-3 bg-white border-[1.5px] border-line rounded-md text-[14px]">
        </div>
        <div class="flex flex-col gap-1">
            <label class="font-mono text-[10px] tracking-wide-label uppercase text-ink-mute">Nivel</label>
            <select name="nivel" class="h-[40px] px-2 bg-white border-[1.5px] border-line rounded-md text-[14px]">
                <option value="">Mi nivel</option>
                <option value="todos" @selected(($filters['level'] ?? '') === 'todos')>Todos los niveles</option>
                @foreach ($levels as $l)
                    <option value="{{ $l }}" @selected(($filters['level'] ?? '') === $l)>{{ $levelLabels[$l] ?? $l }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex flex-col gap-1">
            <label class="font-mono text-[10px] tracking-wide-label uppercase text-ink-mute">Desde</label>
            <input type="date" name="fecha" value="{{ $filters['date'] ?? '' }}"
                   class="h-[40px] px-3 bg-white border-[1.5px] border-line rounded-md text-[14px]">
        </div>
        <div class="flex items-end gap-2">
            <button type="submit" class="btn btn-primary btn-sm flex-1">Filtrar</button>
            <a href="{{ route('social.oportunidades.index') }}" class="btn btn-secondary btn-sm">Limpiar</a>
        </div>
    </form>

    @if ($effectiveLevel && ($filters['level'] ?? '') !== 'todos')
        <p class="font-mono text-[11px] text-ink-mute mb-4">
            Mostrando nivel <span class="font-semibold text-pitch">{{ $levelLabels[$effectiveLevel] ?? $effectiveLevel }}</span>
            (y sin nivel). <a href="{{ request()->fullUrlWithQuery(['nivel' => 'todos']) }}" class="text-pitch underline">Ver todos los niveles</a>.
        </p>
    @endif

    {{-- Resultados --}}
    @if ($opportunities->isEmpty())
        <div class="bg-white border border-line rounded-md shadow-card p-10 text-center">
            <p class="text-ink-soft">No hay oportunidades que coincidan con tu búsqueda.</p>
            @auth
                <a href="{{ route('social.oportunidades.create') }}" class="btn btn-primary btn-sm mt-4">Publicá la primera</a>
            @endauth
        </div>
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
            @foreach ($opportunities as $op)
                <a href="{{ route('social.oportunidades.show', $op) }}"
                   class="bg-white border rounded-md shadow-card-2 p-5 flex flex-col gap-3 hover:border-pitch transition-all duration-fast {{ $op->isExpress() ? 'border-alerta ring-1 ring-alerta/30' : 'border-line' }}">
                    <div class="flex items-center justify-between gap-2">
                        <div class="flex items-center gap-1.5">
                            <span class="px-2.5 py-1 rounded-pill font-display font-bold text-[10.5px] uppercase tracking-wide-label {{ $typeBadge[$op->type] ?? 'bg-bone-soft text-ink' }}">
                                {{ $op->typeLabel() }}
                            </span>
                            @if ($op->isExpress())
                                <span class="px-2 py-1 rounded-pill font-display font-bold text-[10px] uppercase tracking-wide-label bg-alerta text-white animate-pulse">⚡ Urgente</span>
                            @endif
                        </div>
                        @if ($op->required_level)
                            <span class="font-mono text-[10px] text-ink-mute uppercase">{{ $levelLabels[$op->required_level] ?? $op->required_level }}</span>
                        @endif
                    </div>

                    <div class="flex items-center gap-3">
                        <x-avatar :name="$op->authorName()" :src="$op->club?->shield_url ?? $op->user?->avatar_url" size="md" />
                        <div class="min-w-0">
                            <p class="font-display font-bold text-pitch text-[15px] truncate">{{ $op->authorName() }}</p>
                            <p class="font-mono text-[11px] text-ink-mute truncate">{{ $op->city }}{{ $op->zone ? ' · ' . $op->zone : '' }}</p>
                        </div>
                    </div>

                    @if (! empty($op->payload['descripcion']))
                        <p class="text-[13px] text-ink-soft line-clamp-2">{{ $op->payload['descripcion'] }}</p>
                    @endif

                    <div class="mt-auto flex items-center justify-between font-mono text-[11px] text-ink-mute pt-2 border-t border-line-soft">
                        <span>{{ $op->window_start ? $op->window_start->format('d/m H:i') : 'Sin fecha' }}</span>
                        <span>{{ $op->responses_count }} resp.</span>
                    </div>
                </a>
            @endforeach
        </div>

        <div class="mt-8">{{ $opportunities->links() }}</div>
    @endif
</div>
@endsection
