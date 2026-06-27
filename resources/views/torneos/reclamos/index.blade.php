@extends('layouts.app')
@section('title', 'Reclamar mi perfil')

@php
    $statusMeta = [
        'pending'   => ['Pendiente del capitán', 'bg-amber-100 text-amber-700 border-amber-300'],
        'escalated' => ['Escalado a la plataforma', 'bg-blue-100 text-blue-700 border-blue-300'],
        'approved'  => ['Aprobado', 'bg-green-100 text-green-700 border-green-300'],
        'rejected'  => ['Rechazado', 'bg-alerta/15 text-alerta-deep border-alerta/40'],
    ];
@endphp

@section('content')
<div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

    <div class="mb-6">
        <p class="eyebrow">Mi perfil</p>
        <h1 class="font-display font-bold text-display-s text-pitch uppercase mt-1">Reclamar mi perfil</h1>
        <p class="text-ink-soft text-[14px] mt-1">
            Si un capitán te anotó en un equipo sin que tuvieras cuenta, acá podés reclamar ese registro
            para heredar tu historial (partidos, goles, tarjetas) y obtener tu credencial digital.
        </p>
    </div>

    @if (empty(auth()->user()->document))
        <div class="bg-white border border-line rounded-md shadow-card p-6 text-center text-ink-soft text-[14px]">
            Cargá tu documento en tu <a href="{{ route('profile.show') }}" class="text-pitch font-semibold hover:underline">perfil</a>
            para que podamos buscar registros a tu nombre.
        </div>
    @endif

    {{-- ── Candidatos a reclamar ──────────────────────────────────────────── --}}
    <section class="mb-10">
        <p class="font-mono text-[11px] tracking-wide-label uppercase text-ink-mute mb-3">Registros a tu nombre ({{ $candidates->count() }})</p>

        @if ($candidates->isEmpty())
            <div class="bg-white border border-line rounded-md shadow-card p-6 text-center text-ink-soft text-[14px]">
                No encontramos registros pendientes que coincidan con tu documento.
            </div>
        @else
            <div class="flex flex-col gap-4">
                @foreach ($candidates as $row)
                    @php $cp = $row['clubPlayer']; @endphp
                    <div class="bg-white border border-line rounded-md shadow-card p-5">
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div>
                                <p class="font-display font-bold text-pitch text-[16px]">{{ $cp->full_name ?? $cp->displayName() }}</p>
                                <p class="text-ink-soft text-[13px] mt-0.5">
                                    Equipo: <span class="font-semibold">{{ $row['club']?->name ?? '—' }}</span>
                                    · Documento: {{ $cp->document }}
                                </p>
                                @if ($row['tournaments']->isNotEmpty())
                                    <p class="text-ink-mute text-[12px] mt-1">
                                        Aparece en: {{ $row['tournaments']->pluck('name')->join(', ') }}
                                    </p>
                                @endif
                            </div>
                            <form method="POST" action="{{ route('torneos.reclamos.store') }}" class="shrink-0">
                                @csrf
                                <input type="hidden" name="club_player_id" value="{{ $cp->id }}">
                                <button type="submit" class="btn btn-primary btn-sm">Reclamar este perfil</button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </section>

    {{-- ── Mis reclamos ───────────────────────────────────────────────────── --}}
    <section>
        <p class="font-mono text-[11px] tracking-wide-label uppercase text-ink-mute mb-3">Mis reclamos ({{ $myClaims->count() }})</p>

        @if ($myClaims->isEmpty())
            <div class="bg-white border border-line rounded-md shadow-card p-6 text-center text-ink-soft text-[14px]">
                Todavía no iniciaste ningún reclamo.
            </div>
        @else
            <div class="flex flex-col gap-3">
                @foreach ($myClaims as $claim)
                    @php [$label, $cls] = $statusMeta[$claim->status] ?? ['—', 'bg-line text-ink-soft border-line']; @endphp
                    <div class="bg-white border border-line rounded-md shadow-card p-4 flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <p class="font-display font-semibold text-pitch text-[14px]">
                                {{ $claim->clubPlayer?->full_name ?? $claim->document }}
                                <span class="text-ink-mute font-normal">· {{ $claim->club?->name ?? '—' }}</span>
                            </p>
                            @if ($claim->status === 'rejected' && $claim->resolution_note)
                                <p class="text-ink-mute text-[12px] mt-0.5">Motivo: {{ $claim->resolution_note }}</p>
                            @endif
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="inline-block px-2.5 py-1 rounded-full border text-[11px] font-semibold {{ $cls }}">{{ $label }}</span>
                            @if ($claim->status === 'pending')
                                <form method="POST" action="{{ route('torneos.reclamos.escalate', $claim) }}"
                                      onsubmit="return confirm('¿Escalar este reclamo a la plataforma? Útil si el capitán no responde.');">
                                    @csrf
                                    <button type="submit" class="btn btn-ghost btn-sm">Escalar</button>
                                </form>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </section>
</div>
@endsection
