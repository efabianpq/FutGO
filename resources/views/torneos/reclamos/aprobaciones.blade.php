@extends('layouts.app')
@section('title', 'Reclamos por aprobar')

@section('content')
<div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

    <div class="mb-6">
        <p class="eyebrow">Capitán</p>
        <h1 class="font-display font-bold text-display-s text-pitch uppercase mt-1">Reclamos por aprobar</h1>
        <p class="text-ink-soft text-[14px] mt-1">
            Jugadores que quieren vincular su cuenta a un registro de tu plantilla. Si aprobás, el jugador
            hereda el historial de ese registro. Si no lo reconocés, rechazalo: el registro queda sin cambios.
        </p>
    </div>

    @if ($pending->isEmpty())
        <div class="bg-white border border-line rounded-md shadow-card p-8 text-center text-ink-soft">
            No tenés reclamos pendientes.
        </div>
    @else
        <div class="flex flex-col gap-4">
            @foreach ($pending as $row)
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
                        <form method="POST" action="{{ route('torneos.reclamos.approve', $claim) }}"
                              onsubmit="return confirm('¿Aprobar? El jugador quedará vinculado y heredará el historial de este registro.');">
                            @csrf
                            <button type="submit" class="btn btn-primary btn-sm">Aprobar y vincular</button>
                        </form>
                        <form method="POST" action="{{ route('torneos.reclamos.reject', $claim) }}"
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
</div>
@endsection
