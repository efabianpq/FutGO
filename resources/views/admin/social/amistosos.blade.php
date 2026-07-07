@extends('layouts.app')
@section('title', 'Amistosos · Admin')

@section('content')
@include('admin._nav')
<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

    <div class="mb-6">
        <p class="eyebrow">Administración</p>
        <div class="flex items-center gap-2 mt-1">
            <h1 class="font-display font-bold text-display-s text-pitch uppercase">Amistosos — disputas</h1>
            <x-help-hint topic="admin.amistosos.index" />
        </div>
        <p class="text-ink-soft text-[14px] mt-1">Resolución de resultados en disputa e historial de cancelaciones.</p>
    </div>

    @if (session('status'))
        <div class="mb-4 bg-gol/20 border border-gol text-pitch-deep px-4 py-3 rounded-md font-display font-semibold">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-4 bg-alerta/15 border border-alerta text-alerta-deep px-4 py-3 rounded-md font-display font-semibold">{{ session('error') }}</div>
    @endif

    {{-- Disputas --}}
    <section class="mb-8">
        <p class="font-mono text-[11px] tracking-wide-label uppercase text-ink-mute mb-3">En disputa ({{ $disputes->count() }})</p>
        @if ($disputes->isEmpty())
            <div class="bg-white border border-line rounded-md shadow-card p-6 text-center text-ink-soft text-[14px]">No hay amistosos en disputa.</div>
        @else
            <div class="flex flex-col gap-3">
                @foreach ($disputes as $m)
                    <div class="bg-white border border-line rounded-md shadow-card p-4 {{ $m->isEscalada() ? 'border-l-4 border-l-alerta' : '' }}">
                        <div class="flex items-center justify-between gap-2 mb-2">
                            <p class="font-display font-bold text-pitch text-[15px]">{{ $m->homeClub?->name }} vs {{ $m->awayClub?->name }}</p>
                            <span class="font-mono text-[11px] text-ink-mute">{{ $m->scheduled_at?->format('d/m/Y H:i') ?? 'Sin fecha' }}</span>
                        </div>
                        <p class="text-[13px] text-ink-soft mb-3">
                            Local reportó <strong>{{ $m->home_reported_home_score }}–{{ $m->home_reported_away_score }}</strong> ·
                            Visitante reportó <strong>{{ $m->away_reported_home_score }}–{{ $m->away_reported_away_score }}</strong>
                            @if ($m->isEscalada()) · <span class="text-alerta-deep font-semibold">escalado por {{ $m->escalatedByClub?->name }}</span> @endif
                        </p>
                        <form method="POST" action="{{ route('admin.amistosos.resolve', $m) }}" class="flex flex-wrap items-end gap-2">
                            @csrf
                            <span class="font-mono text-[11px] text-ink-mute uppercase">Resultado oficial:</span>
                            <div class="flex items-center gap-2">
                                <span class="font-mono text-[11px] text-ink-mute">{{ $m->homeClub?->name }}</span>
                                <input type="number" name="home_score" min="0" max="99" required
                                       class="w-14 h-[38px] px-2 text-center bg-white border-[1.5px] border-line rounded-md text-[14px]">
                                <span class="font-mono text-ink-mute">–</span>
                                <input type="number" name="away_score" min="0" max="99" required
                                       class="w-14 h-[38px] px-2 text-center bg-white border-[1.5px] border-line rounded-md text-[14px]">
                                <span class="font-mono text-[11px] text-ink-mute">{{ $m->awayClub?->name }}</span>
                            </div>
                            <button type="submit" class="btn btn-primary btn-sm">Fijar resultado</button>
                        </form>
                    </div>
                @endforeach
            </div>
        @endif
    </section>

    {{-- Cancelaciones --}}
    <section>
        <p class="font-mono text-[11px] tracking-wide-label uppercase text-ink-mute mb-3">Historial de cancelaciones ({{ $cancellations->count() }})</p>
        @if ($cancellations->isEmpty())
            <div class="bg-white border border-line rounded-md shadow-card p-6 text-center text-ink-soft text-[14px]">Sin cancelaciones registradas.</div>
        @else
            <div class="overflow-x-auto bg-white border border-line rounded-md shadow-card">
                <table class="w-full min-w-[560px] text-left text-[13px]">
                    <thead>
                        <tr class="font-mono text-[11px] tracking-wide-label uppercase text-ink-mute border-b border-line">
                            <th class="px-4 py-2">Partido</th>
                            <th class="px-4 py-2">Canceló</th>
                            <th class="px-4 py-2">Motivo</th>
                            <th class="px-4 py-2">Fecha</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line-soft">
                        @foreach ($cancellations as $m)
                            <tr class="hover:bg-bone-soft">
                                <td class="px-4 py-3 font-display font-semibold text-pitch">{{ $m->homeClub?->name }} vs {{ $m->awayClub?->name }}</td>
                                <td class="px-4 py-3 text-ink-soft">{{ $m->cancelledByClub?->name ?? '—' }}</td>
                                <td class="px-4 py-3 text-ink-soft">{{ $m->cancellation_reason ?? '—' }}</td>
                                <td class="px-4 py-3 font-mono text-ink-mute">{{ $m->cancelled_at?->format('d/m/Y H:i') ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</div>
@endsection
