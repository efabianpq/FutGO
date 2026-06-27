@extends('layouts.app')
@section('title', 'Mi agenda deportiva')

@section('content')
@php
    use App\Services\Social\SportsAgendaService;

    $dayLabel = function (string $key) {
        if ($key === 'sin-fecha') {
            return 'Sin fecha definida';
        }
        $d = \Illuminate\Support\Carbon::parse($key);
        $today = now()->startOfDay();
        if ($d->isSameDay($today)) return 'Hoy · ' . $d->translatedFormat('d M');
        if ($d->isSameDay($today->copy()->addDay())) return 'Mañana · ' . $d->translatedFormat('d M');
        return ucfirst($d->translatedFormat('l d M Y'));
    };
@endphp

<div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

    <div class="mb-6 flex items-end justify-between gap-4 flex-wrap">
        <div>
            <p class="eyebrow">FutGO</p>
            <h1 class="font-display font-bold text-display-s text-pitch uppercase mt-1">Mi agenda</h1>
            <p class="font-mono text-[12px] text-ink-mute mt-1">Todo lo que tenés programado o pendiente, en un solo lugar.</p>
        </div>
        <a href="{{ route('torneos.mi-carrera') }}" class="font-mono text-[12px] text-ink-mute hover:text-pitch">← Mi carrera</a>
    </div>

    @if (session('status'))
        <div class="mb-4 bg-gol/15 border border-gol/40 text-pitch-deep px-4 py-3 rounded-md font-display font-semibold text-[14px]">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-4 bg-alerta/15 border border-alerta text-alerta-deep px-4 py-3 rounded-md font-display font-semibold text-[14px]">{{ session('error') }}</div>
    @endif

    @if ($total === 0)
        <div class="bg-white border border-line rounded-md shadow-card p-8 text-center">
            <p class="font-display font-semibold text-pitch text-[16px]">No tenés nada agendado por ahora.</p>
            <p class="text-ink-soft text-[13px] mt-2">Cuando seas convocado a un partido, confirmes un amistoso o publiques una oportunidad, vas a verlo acá.</p>
            <div class="mt-4 flex justify-center gap-2">
                <a href="{{ route('social.oportunidades.index') }}" class="btn btn-primary btn-sm">Ver oportunidades</a>
                <a href="{{ route('social.amistosos.index') }}" class="btn btn-secondary btn-sm">Mis amistosos</a>
            </div>
        </div>
    @else
        <div class="flex flex-col gap-6">
            @foreach ($grouped as $dayKey => $items)
                <section>
                    <p class="font-mono text-[11px] tracking-wide-label uppercase text-ink-mute mb-2 pl-1">{{ $dayLabel($dayKey) }}</p>
                    <div class="bg-white border border-line rounded-md shadow-card-2 overflow-hidden divide-y divide-line-soft">
                        @foreach ($items as $item)
                            <div class="px-4 py-3 flex items-start gap-3">
                                {{-- Marcador de tipo --}}
                                @php
                                    [$icon, $accent] = match ($item->kind) {
                                        SportsAgendaService::KIND_TOURNAMENT_MATCH => ['🏆', 'border-l-pitch'],
                                        SportsAgendaService::KIND_FRIENDLY          => ['🤝', 'border-l-gol'],
                                        SportsAgendaService::KIND_OPPORTUNITY       => ['📣', 'border-l-amber-400'],
                                        default                                     => ['•', 'border-l-line'],
                                    };
                                @endphp
                                <span class="text-lg leading-none mt-0.5 shrink-0">{{ $icon }}</span>

                                <div class="min-w-0 flex-1">
                                    <p class="font-display font-semibold text-pitch text-[14px] truncate">{{ $item->title }}</p>
                                    <p class="font-mono text-[11px] text-ink-mute truncate">
                                        @if ($item->date)
                                            {{ $item->date->format('H:i') }} ·
                                        @endif
                                        {{ $item->subtitle }}
                                    </p>

                                    {{-- Estado + acciones según el tipo --}}
                                    @if ($item->kind === SportsAgendaService::KIND_TOURNAMENT_MATCH)
                                        <div class="mt-2 flex items-center gap-3 flex-wrap">
                                            @if ($item->status === 'convocatoria_pendiente')
                                                <span class="font-mono text-[11px] text-gol-deep">¡Estás convocado! Respondé:</span>
                                                <form method="POST" action="{{ route('torneos.convocatoria.respond', [$item->tournament, $item->match]) }}" class="inline">
                                                    @csrf <input type="hidden" name="response" value="confirmado">
                                                    <button type="submit" class="font-display font-bold text-[12px] uppercase text-gol-deep hover:underline tracking-wide-cta">Confirmar</button>
                                                </form>
                                                <form method="POST" action="{{ route('torneos.convocatoria.respond', [$item->tournament, $item->match]) }}" class="inline">
                                                    @csrf <input type="hidden" name="response" value="declinado">
                                                    <button type="submit" class="font-display font-semibold text-[12px] uppercase text-alerta hover:underline tracking-wide-cta">Declinar</button>
                                                </form>
                                            @elseif ($item->status === 'confirmado')
                                                <x-badge variant="win">Asistencia confirmada</x-badge>
                                            @elseif ($item->status === 'declinado')
                                                <x-badge variant="default">Declinaste</x-badge>
                                            @elseif ($item->match?->status === 'live')
                                                <x-badge variant="live">En vivo</x-badge>
                                            @else
                                                <x-badge variant="upcoming">Programado</x-badge>
                                            @endif
                                        </div>
                                    @elseif ($item->kind === SportsAgendaService::KIND_FRIENDLY)
                                        <div class="mt-2 flex items-center gap-3 flex-wrap">
                                            @if ($item->status === 'resultado_pendiente')
                                                <span class="font-mono text-[11px] text-amber-700">Ya se jugó: cargá el resultado.</span>
                                                <a href="{{ route('social.amistosos.index') }}" class="font-display font-bold text-[12px] uppercase text-pitch hover:underline tracking-wide-cta">Cargar resultado →</a>
                                            @else
                                                <x-badge variant="win">Amistoso confirmado</x-badge>
                                            @endif
                                        </div>
                                    @elseif ($item->kind === SportsAgendaService::KIND_OPPORTUNITY)
                                        <div class="mt-2 flex items-center gap-3 flex-wrap">
                                            <span class="font-mono text-[11px] text-amber-700">Vence {{ $item->deadline->diffForHumans() }}</span>
                                            <a href="{{ route('social.oportunidades.show', $item->opportunity) }}" class="font-display font-bold text-[12px] uppercase text-pitch hover:underline tracking-wide-cta">Ver →</a>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endforeach
        </div>
    @endif

</div>
@endsection
