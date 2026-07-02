@extends('layouts.app')
@section('title', 'Canchas')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

    <div class="flex flex-wrap items-end justify-between gap-4 mb-6">
        <div>
            <p class="eyebrow">FutGO Social</p>
            <h1 class="font-display font-bold text-display-s sm:text-display-m text-pitch uppercase mt-1">Canchas</h1>
            <p class="text-ink-soft text-[14px] mt-1">Catálogo de instalaciones deportivas registradas por la comunidad.</p>
        </div>
        @auth
            <a href="{{ route('social.canchas.create') }}" class="btn btn-primary btn-sm">+ Registrar cancha</a>
        @else
            <a href="{{ route('login') }}" class="btn btn-primary btn-sm">Ingresa para registrar</a>
        @endauth
    </div>

    @if (session('success'))
        <div class="mb-4 bg-gol/20 border border-gol text-pitch-deep px-4 py-3 rounded-md font-display font-semibold">{{ session('success') }}</div>
    @endif

    {{-- Filtros --}}
    <form method="GET" class="bg-white border border-line rounded-md shadow-card p-4 mb-6 grid grid-cols-1 sm:grid-cols-3 gap-3">
        <div class="flex flex-col gap-1">
            <label class="font-mono text-[10px] tracking-wide-label uppercase text-ink-mute">Ciudad</label>
            <select name="ciudad" class="h-[40px] px-2 bg-white border-[1.5px] border-line rounded-md text-[14px]">
                <option value="">Todas las ciudades</option>
                @foreach ($cities as $c)
                    <option value="{{ $c }}" @selected($city === $c)>{{ $c }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex flex-col gap-1">
            <label class="font-mono text-[10px] tracking-wide-label uppercase text-ink-mute">Buscar</label>
            <input type="text" name="q" value="{{ $search }}" placeholder="Nombre o dirección"
                   class="h-[40px] px-3 bg-white border-[1.5px] border-line rounded-md text-[14px]">
        </div>
        <div class="flex items-end">
            <button type="submit" class="btn btn-primary btn-sm w-full">Filtrar</button>
        </div>
    </form>

    @if ($venues->isEmpty())
        <div class="text-center py-16 text-ink-soft">
            <p class="text-[16px]">No se encontraron canchas con esos filtros.</p>
            @auth
                <a href="{{ route('social.canchas.create') }}" class="btn btn-primary btn-sm mt-4 inline-block">Registrar la primera</a>
            @endauth
        </div>
    @else
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach ($venues as $venue)
                <a href="{{ route('social.canchas.show', $venue->slug) }}"
                   class="bg-white border border-line rounded-md shadow-card p-4 hover:border-pitch/40 transition-colors block">
                    <div class="flex items-start justify-between gap-2">
                        <h2 class="font-display font-semibold text-[16px] text-pitch leading-snug">{{ $venue->name }}</h2>
                        @if ($venue->surface_type)
                            <span class="shrink-0 text-[11px] font-mono bg-grass-light/20 text-pitch rounded px-2 py-0.5">{{ $venue->surfaceLabel() }}</span>
                        @endif
                    </div>
                    <p class="text-ink-soft text-[13px] mt-1">{{ $venue->city }}{{ $venue->address ? ' · ' . $venue->address : '' }}</p>
                    @if ($venue->approx_capacity)
                        <p class="text-ink-mute text-[12px] mt-1">Capacidad aprox. {{ number_format($venue->approx_capacity) }}</p>
                    @endif
                    @if ($venue->maps_url)
                        <span class="text-[12px] text-pitch/70 mt-2 inline-flex items-center gap-1"><x-icon name="map-pin" class="w-3.5 h-3.5" /> Ver en mapa</span>
                    @endif
                </a>
            @endforeach
        </div>

        <div class="mt-6">
            {{ $venues->links() }}
        </div>
    @endif
</div>
@endsection
