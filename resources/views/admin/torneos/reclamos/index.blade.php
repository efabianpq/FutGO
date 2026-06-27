@extends('layouts.app')
@section('title', 'Reclamos de perfil · Admin')

@section('content')
@include('admin._nav')

<div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

    <div class="mb-6">
        <p class="eyebrow">Administración</p>
        <h1 class="font-display font-bold text-display-s text-pitch uppercase mt-1">Reclamos de perfil escalados</h1>
        <p class="text-ink-soft text-[14px] mt-1">
            Reclamos de equipos sin capitán activo. Resolvelos en nombre de la plataforma:
            aprobar vincula al jugador y le transfiere el historial.
        </p>
    </div>

    {{-- ── Escalados ──────────────────────────────────────────────────────── --}}
    <section class="mb-10">
        <p class="font-mono text-[11px] tracking-wide-label uppercase text-ink-mute mb-3">Escalados ({{ $escalated->count() }})</p>

        @if ($escalated->isEmpty())
            <div class="bg-white border border-line rounded-md shadow-card p-6 text-center text-ink-soft text-[14px]">
                No hay reclamos escalados.
            </div>
        @else
            <div class="flex flex-col gap-4">
                @foreach ($escalated as $row)
                    @php $claim = $row['claim']; @endphp
                    <div class="bg-white border border-line rounded-md shadow-card p-5">
                        <div class="mb-3">
                            <p class="font-display font-bold text-pitch text-[16px]">{{ $claim->user?->name }}</p>
                            <p class="text-ink-soft text-[13px] mt-0.5">
                                Quiere vincularse a <span class="font-semibold">{{ $claim->clubPlayer?->full_name ?? $claim->document }}</span>
                                de <span class="font-semibold">{{ $claim->club?->name }}</span>
                            </p>
                            <p class="text-ink-mute text-[12px] mt-0.5">
                                Documento: {{ $claim->document }}
                                @if ($claim->user?->futgo_id) · {{ $claim->user->futgo_id }} @endif
                            </p>
                            @if ($row['tournaments']->isNotEmpty())
                                <p class="text-ink-mute text-[12px] mt-1">Historial en: {{ $row['tournaments']->pluck('name')->join(', ') }}</p>
                            @endif
                        </div>

                        <div class="flex flex-wrap items-center gap-2 border-t border-line pt-3">
                            <form method="POST" action="{{ route('admin.torneos.reclamos.approve', $claim) }}"
                                  onsubmit="return confirm('¿Aprobar y vincular al jugador?');">
                                @csrf
                                <button type="submit" class="btn btn-primary btn-sm">Aprobar y vincular</button>
                            </form>
                            <form method="POST" action="{{ route('admin.torneos.reclamos.reject', $claim) }}"
                                  class="flex items-center gap-2"
                                  onsubmit="return confirm('¿Rechazar este reclamo?');">
                                @csrf
                                <input type="text" name="note" maxlength="255" placeholder="Motivo (opcional)"
                                       class="h-9 px-3 bg-white border border-line rounded-md text-[13px] focus:border-pitch focus:ring-0">
                                <button type="submit" class="btn btn-ghost btn-sm text-alerta">Rechazar</button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </section>

    {{-- ── Historial ──────────────────────────────────────────────────────── --}}
    <section>
        <p class="font-mono text-[11px] tracking-wide-label uppercase text-ink-mute mb-3">Resueltos recientes</p>

        @if ($resolved->isEmpty())
            <div class="bg-white border border-line rounded-md shadow-card p-6 text-center text-ink-soft text-[14px]">
                Sin historial todavía.
            </div>
        @else
            <div class="bg-white border border-line rounded-md shadow-card divide-y divide-line">
                @foreach ($resolved as $claim)
                    <div class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 text-[13px]">
                        <div>
                            <span class="font-semibold text-pitch">{{ $claim->user?->name }}</span>
                            <span class="text-ink-mute">· {{ $claim->club?->name }}</span>
                        </div>
                        <div class="flex items-center gap-2 text-ink-soft">
                            <span class="inline-block px-2 py-0.5 rounded-full border text-[11px] font-semibold
                                {{ $claim->status === 'approved' ? 'bg-green-100 text-green-700 border-green-300' : 'bg-alerta/15 text-alerta-deep border-alerta/40' }}">
                                {{ $claim->status === 'approved' ? 'Aprobado' : 'Rechazado' }}
                            </span>
                            <span class="text-ink-mute">{{ $claim->resolved_at?->format('d/m/Y') }}</span>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </section>
</div>
@endsection
