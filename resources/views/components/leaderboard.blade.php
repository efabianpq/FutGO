{{--
    x-leaderboard — Tabla del ranking.
    Las posiciones 1, 2 y 3 muestran 🥇🥈🥉 con color de medalla.
    La navegación al detalle se hace con un botón explícito "Ver pronósticos"
    al final de cada fila (en móvil queda como icono compacto →).

    Soporta modo Alpine (uso vivo en /ranking) y modo PHP estático.
--}}
@props([
    'rows' => [],
    'me' => null,
    'alpine' => false,
    'userUrlBase' => null,
])

@if ($alpine)
    <div class="bg-white border border-line rounded-md overflow-hidden shadow-card">
        {{-- Header --}}
        <div class="grid grid-cols-[48px_1fr_64px_88px] sm:grid-cols-[56px_1fr_72px_72px_160px] gap-2 sm:gap-3 px-3 sm:px-4 py-2.5 bg-pitch text-bone font-mono text-[10.5px] tracking-wide-label uppercase">
            <span>Pos</span>
            <span>Participante</span>
            <span class="hidden sm:block text-right">Exactos</span>
            <span class="text-right">Puntos</span>
            <span class="text-right">Acción</span>
        </div>

        {{-- Filas (Alpine) --}}
        <template x-for="row in rows" :key="row.user_id">
            <div class="grid grid-cols-[48px_1fr_64px_88px] sm:grid-cols-[56px_1fr_72px_72px_160px] gap-2 sm:gap-3 px-3 sm:px-4 py-3 items-center border-b border-line-soft last:border-b-0"
                 :class="row.user_id === me ? 'bg-[#fef9e3]' : ''">

                {{-- Posición con medalla en top 3 --}}
                <div class="flex items-center justify-start">
                    <template x-if="row.current_position === 1">
                        <span class="font-display font-extrabold text-[22px] text-gol-deep">🥇</span>
                    </template>
                    <template x-if="row.current_position === 2">
                        <span class="font-display font-extrabold text-[22px] text-[#8a8a8a]">🥈</span>
                    </template>
                    <template x-if="row.current_position === 3">
                        <span class="font-display font-extrabold text-[22px] text-[#b87333]">🥉</span>
                    </template>
                    <template x-if="!row.current_position || row.current_position > 3">
                        <span class="font-display font-extrabold text-[20px] text-ink-soft" x-text="row.current_position ?? '—'"></span>
                    </template>
                </div>

                {{-- Participante --}}
                <div class="flex items-center gap-2 sm:gap-3 min-w-0">
                    <div class="w-7 h-7 sm:w-8 sm:h-8 rounded-pill flex items-center justify-center bg-pitch-light text-bone font-display font-bold text-[12px] sm:text-[13px] shrink-0"
                         x-text="row.name.split(' ').map(p=>p[0]).join('').slice(0,2).toUpperCase()"></div>
                    <div class="min-w-0">
                        <p class="font-semibold text-body-s text-ink truncate" x-text="row.name"></p>
                        <template x-if="row.previous_position && row.previous_position !== row.current_position">
                            <p class="font-mono text-[10px] tracking-wide-eyebrow uppercase"
                               :class="row.previous_position > row.current_position ? 'text-pitch-light' : 'text-alerta'">
                                <span x-text="row.previous_position > row.current_position ? '↑ subió' : '↓ bajó'"></span>
                            </p>
                        </template>
                    </div>
                </div>

                {{-- Exactos (oculto en mobile) --}}
                <span class="hidden sm:block text-right font-mono text-[13px] text-gol-deep" x-text="row.exact_predictions"></span>

                {{-- Puntos --}}
                <span class="text-right font-display font-extrabold text-[20px]"
                      :class="row.user_id === me ? 'text-gol-deep' : 'text-pitch'"
                      x-text="row.total_points"></span>

                {{-- Botón Ver pronósticos (mobile: icono ; desktop: texto) --}}
                <div class="text-right">
                    <a :href="userShowBase + '/' + row.user_id"
                       class="inline-flex items-center justify-center gap-1.5 font-display font-bold text-[11px] uppercase tracking-wide-cta px-2.5 sm:px-3 py-1.5 rounded-md bg-pitch text-bone hover:bg-pitch-deep transition-all duration-fast"
                       :title="'Ver pronósticos de ' + row.name">
                        <span class="hidden sm:inline">Ver pronósticos</span>
                        <span class="sm:hidden">Ver →</span>
                    </a>
                </div>
            </div>
        </template>

        <div x-show="rows.length === 0" x-cloak class="px-4 py-8 text-center text-ink-mute italic font-mono text-body-s">
            Aún no hay participantes en el ranking.
        </div>
    </div>
@else
    {{-- Static PHP mode --}}
    <div class="bg-white border border-line rounded-md overflow-hidden shadow-card">
        <div class="grid grid-cols-[48px_1fr_64px_88px] sm:grid-cols-[56px_1fr_72px_72px_160px] gap-2 sm:gap-3 px-3 sm:px-4 py-2.5 bg-pitch text-bone font-mono text-[10.5px] tracking-wide-label uppercase">
            <span>Pos</span>
            <span>Participante</span>
            <span class="hidden sm:block text-right">Exactos</span>
            <span class="text-right">Puntos</span>
            <span class="text-right">Acción</span>
        </div>
        @forelse ($rows as $row)
            @php
                $pos = (int) ($row['current_position'] ?? 0);
                $isMe = $me && (int) $row['user_id'] === (int) $me;
                $initials = collect(explode(' ', $row['name']))->map(fn($p) => substr($p, 0, 1))->take(2)->join('');
            @endphp
            <div class="grid grid-cols-[48px_1fr_64px_88px] sm:grid-cols-[56px_1fr_72px_72px_160px] gap-2 sm:gap-3 px-3 sm:px-4 py-3 items-center border-b border-line-soft last:border-b-0 {{ $isMe ? 'bg-[#fef9e3]' : '' }}">
                <div>
                    @if ($pos === 1) <span class="font-display font-extrabold text-[22px] text-gol-deep">🥇</span>
                    @elseif ($pos === 2) <span class="font-display font-extrabold text-[22px] text-[#8a8a8a]">🥈</span>
                    @elseif ($pos === 3) <span class="font-display font-extrabold text-[22px] text-[#b87333]">🥉</span>
                    @else <span class="font-display font-extrabold text-[20px] text-ink-soft">{{ $pos ?: '—' }}</span>
                    @endif
                </div>
                <div class="flex items-center gap-2 sm:gap-3 min-w-0">
                    <div class="w-7 h-7 sm:w-8 sm:h-8 rounded-pill flex items-center justify-center bg-pitch-light text-bone font-display font-bold text-[12px] sm:text-[13px] shrink-0">{{ strtoupper($initials) }}</div>
                    <div class="min-w-0">
                        <p class="font-semibold text-body-s text-ink truncate">{{ $row['name'] }}</p>
                    </div>
                </div>
                <span class="hidden sm:block text-right font-mono text-[13px] text-gol-deep">{{ $row['exact_predictions'] }}</span>
                <span class="text-right font-display font-extrabold text-[20px] {{ $isMe ? 'text-gol-deep' : 'text-pitch' }}">{{ $row['total_points'] }}</span>
                <div class="text-right">
                    <a href="{{ $userUrlBase ? $userUrlBase . '/' . $row['user_id'] : '#' }}"
                       class="inline-flex items-center justify-center gap-1.5 font-display font-bold text-[11px] uppercase tracking-wide-cta px-2.5 sm:px-3 py-1.5 rounded-md bg-pitch text-bone hover:bg-pitch-deep transition-all duration-fast"
                       title="Ver pronósticos de {{ $row['name'] }}">
                        <span class="hidden sm:inline">Ver pronósticos</span>
                        <span class="sm:hidden">Ver →</span>
                    </a>
                </div>
            </div>
        @empty
            <div class="px-4 py-8 text-center text-ink-mute italic font-mono text-body-s">Aún no hay participantes en el ranking.</div>
        @endforelse
    </div>
@endif
