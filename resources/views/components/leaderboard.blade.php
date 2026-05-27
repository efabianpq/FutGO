{{--
    x-leaderboard — Tabla completa con header pitch + filas con destacado para top 3.
    Soporta dos modos: Alpine (uso vivo) o PHP estático.
--}}
@props([
    'rows' => [],         // array de filas (PHP); cada fila tiene name, total_points, exact_predictions, current_position, previous_position, user_id
    'me' => null,         // id de usuario actual (resalta su fila)
    'alpine' => false,    // si true, asume que el padre tiene rows en x-data
    'userUrlBase' => null, // ej. '/ranking/u'
])

@if ($alpine)
    <div class="bg-white border border-line rounded-md overflow-hidden shadow-card">
        {{-- Header --}}
        <div class="grid grid-cols-[44px_1fr_72px] sm:grid-cols-[44px_1fr_72px_80px] gap-3 px-4 py-2.5 bg-pitch text-bone font-mono text-[10.5px] tracking-wide-label uppercase">
            <span>#</span>
            <span>Participante</span>
            <span class="hidden sm:block text-right">Exactos</span>
            <span class="text-right">Puntos</span>
        </div>

        {{-- Rows (Alpine) --}}
        <template x-for="(row, idx) in rows" :key="row.user_id">
            <a :href="userUrlBase + '/' + row.user_id"
               class="grid grid-cols-[44px_1fr_72px] sm:grid-cols-[44px_1fr_72px_80px] gap-3 px-4 py-3 items-center border-b border-line-soft last:border-b-0 hover:bg-bone-soft transition-colors duration-fast"
               :class="row.user_id === me ? 'bg-[#fef9e3]' : ''">
                <span class="font-display font-extrabold text-[20px]"
                      :class="row.current_position === 1 ? 'text-gol-deep' : (row.current_position === 2 ? 'text-[#8a8a8a]' : (row.current_position === 3 ? 'text-[#b87333]' : 'text-ink'))"
                      x-text="row.current_position ?? '—'"></span>

                <div class="flex items-center gap-3 min-w-0">
                    <div class="w-8 h-8 rounded-pill flex items-center justify-center bg-pitch-light text-bone font-display font-bold text-[13px] shrink-0"
                         x-text="row.name.split(' ').map(p=>p[0]).join('').slice(0,2).toUpperCase()"></div>
                    <div class="min-w-0">
                        <p class="font-medium text-body-s text-ink truncate" x-text="row.name"></p>
                        <p class="font-mono text-[11px] text-ink-mute truncate"
                           x-text="'@' + row.name.toLowerCase().replace(/\s+/g,'')"></p>
                    </div>
                    <template x-if="row.previous_position && row.previous_position !== row.current_position">
                        <span class="font-mono text-[11px] ml-1"
                              :class="row.previous_position > row.current_position ? 'text-pitch-light' : 'text-alerta'"
                              x-text="row.previous_position > row.current_position ? '↑' : '↓'"></span>
                    </template>
                </div>

                <span class="hidden sm:block text-right font-mono text-[13px] text-gol-deep" x-text="row.exact_predictions"></span>
                <span class="text-right font-display font-extrabold text-[20px]"
                      :class="row.user_id === me ? 'text-gol-deep' : 'text-pitch'"
                      x-text="row.total_points"></span>
            </a>
        </template>

        <div x-show="rows.length === 0" x-cloak class="px-4 py-8 text-center text-ink-mute italic font-mono text-body-s">
            Aún no hay participantes en el ranking.
        </div>
    </div>
@else
    {{-- Static PHP mode --}}
    <div class="bg-white border border-line rounded-md overflow-hidden shadow-card">
        <div class="grid grid-cols-[44px_1fr_72px] sm:grid-cols-[44px_1fr_72px_80px] gap-3 px-4 py-2.5 bg-pitch text-bone font-mono text-[10.5px] tracking-wide-label uppercase">
            <span>#</span>
            <span>Participante</span>
            <span class="hidden sm:block text-right">Exactos</span>
            <span class="text-right">Puntos</span>
        </div>
        @forelse ($rows as $row)
            @php
                $rankColor = match ((int) ($row['current_position'] ?? 0)) {
                    1 => 'text-gol-deep',
                    2 => 'text-[#8a8a8a]',
                    3 => 'text-[#b87333]',
                    default => 'text-ink',
                };
                $isMe = $me && (int) $row['user_id'] === (int) $me;
                $initials = collect(explode(' ', $row['name']))->map(fn($p) => substr($p, 0, 1))->take(2)->join('');
            @endphp
            <a href="{{ $userUrlBase ? $userUrlBase . '/' . $row['user_id'] : '#' }}"
               class="grid grid-cols-[44px_1fr_72px] sm:grid-cols-[44px_1fr_72px_80px] gap-3 px-4 py-3 items-center border-b border-line-soft last:border-b-0 hover:bg-bone-soft transition-colors duration-fast {{ $isMe ? 'bg-[#fef9e3]' : '' }}">
                <span class="font-display font-extrabold text-[20px] {{ $rankColor }}">{{ $row['current_position'] ?? '—' }}</span>
                <div class="flex items-center gap-3 min-w-0">
                    <div class="w-8 h-8 rounded-pill flex items-center justify-center bg-pitch-light text-bone font-display font-bold text-[13px] shrink-0">{{ strtoupper($initials) }}</div>
                    <div class="min-w-0">
                        <p class="font-medium text-body-s text-ink truncate">{{ $row['name'] }}</p>
                    </div>
                </div>
                <span class="hidden sm:block text-right font-mono text-[13px] text-gol-deep">{{ $row['exact_predictions'] }}</span>
                <span class="text-right font-display font-extrabold text-[20px] {{ $isMe ? 'text-gol-deep' : 'text-pitch' }}">{{ $row['total_points'] }}</span>
            </a>
        @empty
            <div class="px-4 py-8 text-center text-ink-mute italic font-mono text-body-s">Aún no hay participantes en el ranking.</div>
        @endforelse
    </div>
@endif
