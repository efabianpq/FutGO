@extends('layouts.app')
@section('title', 'Validar credencial')

@section('content')
@php
    $statusMeta = [
        'draft' => 'Borrador', 'open' => 'Inscripción', 'in_progress' => 'En juego',
        'finished' => 'Finalizado', 'cancelled' => 'Cancelado',
    ];
@endphp

<div class="max-w-2xl mx-auto px-4 sm:px-6 py-8">

    <p class="eyebrow">🛡️ Control de identidad</p>
    <h1 class="font-display font-bold text-display-s sm:text-display-m text-pitch uppercase mt-2 mb-1">Validar credencial</h1>
    <p class="text-[14px] text-ink-soft mb-6">
        Escanea el QR de la credencial o ingresa el identificador FUTGO a mano.
        Elige el torneo para verificar si el jugador está habilitado.
    </p>

    {{-- Formulario de validación manual --}}
    <form method="POST" action="{{ route('torneos.validar.run') }}"
          class="bg-white border border-line rounded-md shadow-card p-5 sm:p-6 space-y-4">
        @csrf
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="flex flex-col gap-1.5">
                <label for="fg" class="font-mono text-[11px] tracking-wide-label uppercase text-ink-soft">Identificador FUTGO</label>
                <input id="fg" name="fg" type="text" required autofocus placeholder="FG-XXXXXX"
                       value="{{ old('fg', $queriedFg) }}"
                       class="h-[46px] px-3.5 bg-white border-[1.5px] {{ $errors->has('fg') ? 'border-alerta' : 'border-line' }} rounded-md text-[15px] font-mono uppercase tracking-wider focus:border-pitch focus:ring-0">
                @error('fg')<p class="text-[12px] text-alerta">{{ $message }}</p>@enderror
            </div>
            <div class="flex flex-col gap-1.5">
                <label for="tournament_id" class="font-mono text-[11px] tracking-wide-label uppercase text-ink-soft">Torneo (opcional)</label>
                <select id="tournament_id" name="tournament_id"
                        class="h-[46px] px-3.5 bg-white border-[1.5px] border-line rounded-md text-[15px] focus:border-pitch focus:ring-0">
                    <option value="">— Sin torneo —</option>
                    @foreach ($tournaments as $t)
                        <option value="{{ $t->id }}" @selected((string) old('tournament_id', $result ? $result['tournament']?->id : null) === (string) $t->id)>
                            {{ $t->name }} ({{ $statusMeta[$t->status] ?? $t->status }})
                        </option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="flex justify-end">
            <x-btn type="submit" variant="primary">Validar</x-btn>
        </div>
    </form>

    {{-- Resultado de la validación --}}
    @if ($result)
        @php
            $r = $result['result'];
            $palette = [
                'habilitado'    => ['bg' => 'bg-gol/15',    'border' => 'border-gol',    'text' => 'text-pitch-deep', 'label' => '✓ HABILITADO'],
                'no_habilitado' => ['bg' => 'bg-alerta/10',  'border' => 'border-alerta', 'text' => 'text-alerta',     'label' => '✗ NO HABILITADO'],
                'no_encontrado' => ['bg' => 'bg-ink/5',      'border' => 'border-line',   'text' => 'text-ink',        'label' => '? NO ENCONTRADO'],
            ][$r];
        @endphp

        <div class="mt-6 border {{ $palette['border'] }} {{ $palette['bg'] }} rounded-md overflow-hidden">
            {{-- Banner resultado --}}
            <div class="px-5 py-3 border-b {{ $palette['border'] }} flex items-center justify-between gap-3">
                <span class="font-display font-extrabold text-lg {{ $palette['text'] }} uppercase">{{ $palette['label'] }}</span>
                <span class="font-mono text-[10px] uppercase tracking-wide-label text-ink-mute">
                    {{ $result['method'] === 'qr' ? 'Vía QR' : 'Manual' }}
                </span>
            </div>

            <div class="p-5">
                {{-- Aviso de firma para escaneos QR --}}
                @if ($result['method'] === 'qr')
                    @if ($result['signatureValid'])
                        <p class="text-[12px] text-pitch font-semibold mb-3">🔒 Firma del QR verificada (emitido por FUTGO).</p>
                    @else
                        <p class="text-[12px] text-alerta font-semibold mb-3">⚠ Firma del QR inválida o ausente — verifica manualmente la identidad.</p>
                    @endif
                @endif

                @if ($r === 'no_encontrado')
                    <p class="text-[14px] text-ink-soft">
                        El identificador <span class="font-mono font-bold">{{ $result['futgo_id'] }}</span>
                        no corresponde a ningún jugador registrado en FUTGO.
                    </p>
                @else
                    {{-- Identidad del jugador --}}
                    <div class="flex items-center gap-4">
                        <x-avatar :user="$result['user']" size="md" class="sm:hidden shrink-0" />
                        <x-avatar :user="$result['user']" size="lg" class="hidden sm:block shrink-0" />
                        <div class="min-w-0">
                            <p class="font-display font-bold text-lg sm:text-display-s text-ink break-words">{{ $result['user']->name }}</p>
                            <p class="font-mono font-bold text-body text-pitch mt-0.5 tracking-wider">{{ $result['user']->futgo_id }}</p>
                        </div>
                    </div>

                    {{-- Equipos / habilitación --}}
                    <div class="mt-4">
                        <p class="font-mono text-[11px] tracking-wide-label uppercase text-ink-mute mb-2">
                            @if ($result['tournament'])
                                Equipos en {{ $result['tournament']->name }}
                            @else
                                Equipos activos (todos los torneos vigentes)
                            @endif
                        </p>
                        @if ($result['memberships']->isEmpty())
                            <p class="text-[13px] text-ink-soft">
                                @if ($result['tournament'])
                                    No está inscrito ni activo en este torneo.
                                @else
                                    Sin equipos activos.
                                @endif
                            </p>
                        @else
                            <ul class="space-y-1.5">
                                @foreach ($result['memberships'] as $tp)
                                    <li class="flex items-center justify-between gap-3 bg-white border border-line rounded-md px-3 py-2">
                                        <span class="font-display font-bold text-ink truncate">{{ $tp->team->name }}</span>
                                        <span class="font-mono text-[11px] text-ink-mute truncate">{{ $tp->team->tournament->name }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>
@endsection
