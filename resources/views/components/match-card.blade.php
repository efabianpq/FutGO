{{--
    x-match-card
    Tarjeta de pronóstico — 3 estados: abierto, bloqueado, finalizado.
    Uso típico dentro de Alpine.js para predictions/index.

    Espera que el partido se pase ya como objeto Alpine en x-data padre.
    Para uso standalone (PHP-only), pasar $match (array o stdClass) y opcionales.
--}}
@props([
    'match' => null,        // partido (objeto Alpine o array PHP)
    'alpine' => false,      // si true, usar bindings x-* (recomendado en predictions/index)
])

@if ($alpine)
    {{-- ── Alpine mode: se renderiza desde el dataset Alpine ────────────── --}}
    <div class="bg-white border border-line rounded-md shadow-card overflow-hidden">
        {{-- Header --}}
        <div class="flex items-center justify-between px-4 py-2.5 font-mono text-[11px] tracking-wide-label uppercase"
             :class="match.status === 'live' || (match.is_locked && match.status !== 'finished') ? 'bg-alerta text-white' : 'bg-pitch text-bone'">
            <span class="flex items-center gap-2">
                <span x-show="match.group_name" class="bg-gol text-pitch px-2 py-0.5 rounded-sm font-display font-bold text-[11px]"
                      x-text="'Grupo ' + match.group_name"></span>
                <span class="opacity-90" x-text="match.date_label"></span>
            </span>
            <span>
                <template x-if="!match.is_locked && match.status !== 'finished'">
                    <span class="inline-flex items-center gap-1 font-mono text-[10.5px] tracking-wide-label uppercase px-2 py-0.5 rounded-pill bg-bone/15">Abierto</span>
                </template>
                <template x-if="match.is_locked && match.status !== 'finished'">
                    <span class="inline-flex items-center gap-1 font-mono text-[10.5px] tracking-wide-label uppercase px-2 py-0.5 rounded-pill bg-white/15">
                        <span class="inline-block w-1.5 h-1.5 rounded-full bg-current animate-pulse-live"></span>
                        Bloqueado
                    </span>
                </template>
                <template x-if="match.status === 'finished'">
                    <span class="inline-flex items-center gap-1 font-mono text-[10.5px] tracking-wide-label uppercase px-2 py-0.5 rounded-pill bg-gol text-pitch font-display font-bold"
                          x-text="(match.points_earned ?? 0) + ' pts'"></span>
                </template>
            </span>
        </div>

        {{-- Body --}}
        <div class="grid grid-cols-[1fr_auto_1fr] items-center px-4 sm:px-5 py-5 gap-3 sm:gap-4">
            {{-- Local --}}
            <div class="flex flex-col items-center gap-2 text-center">
                <div class="w-12 h-12 sm:w-14 sm:h-14 rounded-pill bg-line-soft border-2 border-line flex items-center justify-center text-[26px]"
                     x-text="match.home_flag || '🏳️'"></div>
                <div class="font-display font-bold text-[15px] sm:text-[17px] uppercase tracking-[.02em] leading-tight" x-text="match.home_team"></div>
                <div x-show="match.status === 'finished'" class="font-display font-extrabold text-[28px] sm:text-[32px] leading-none text-pitch"
                     x-text="match.home_score_official"></div>
            </div>

            {{-- Centro: inputs de marcador o resultado --}}
            <div class="flex items-center gap-1.5">
                <template x-if="match.status !== 'finished'">
                    <div class="flex items-center gap-1.5">
                        <input type="number" min="0" max="20" inputmode="numeric"
                               x-model.number="match.home_score"
                               :disabled="match.is_locked"
                               @blur="save(match)"
                               @keydown.enter.prevent="$event.target.blur()"
                               class="w-12 sm:w-14 h-14 sm:h-16 text-center font-display font-extrabold text-[28px] sm:text-[36px] text-pitch bg-bone-soft border-[1.5px] border-line rounded-md disabled:bg-line-soft disabled:text-ink-soft focus:bg-white focus:border-pitch focus:ring-0 transition-all duration-fast"
                               :placeholder="match.is_locked && match.home_score === null ? '—' : ''">
                        <span class="font-mono text-[11px] tracking-wide-label text-ink-mute">vs</span>
                        <input type="number" min="0" max="20" inputmode="numeric"
                               x-model.number="match.away_score"
                               :disabled="match.is_locked"
                               @blur="save(match)"
                               @keydown.enter.prevent="$event.target.blur()"
                               class="w-12 sm:w-14 h-14 sm:h-16 text-center font-display font-extrabold text-[28px] sm:text-[36px] text-pitch bg-bone-soft border-[1.5px] border-line rounded-md disabled:bg-line-soft disabled:text-ink-soft focus:bg-white focus:border-pitch focus:ring-0 transition-all duration-fast"
                               :placeholder="match.is_locked && match.away_score === null ? '—' : ''">
                    </div>
                </template>
                <template x-if="match.status === 'finished'">
                    <span class="font-mono text-[11px] tracking-wide-label text-ink-mute px-1">vs</span>
                </template>
            </div>

            {{-- Visita --}}
            <div class="flex flex-col items-center gap-2 text-center">
                <div class="w-12 h-12 sm:w-14 sm:h-14 rounded-pill bg-line-soft border-2 border-line flex items-center justify-center text-[26px]"
                     x-text="match.away_flag || '🏳️'"></div>
                <div class="font-display font-bold text-[15px] sm:text-[17px] uppercase tracking-[.02em] leading-tight" x-text="match.away_team"></div>
                <div x-show="match.status === 'finished'" class="font-display font-extrabold text-[28px] sm:text-[32px] leading-none text-pitch"
                     x-text="match.away_score_official"></div>
            </div>
        </div>

        {{-- Footer --}}
        <div class="px-4 sm:px-5 py-3 bg-bone-soft border-t border-line-soft font-mono text-[11px] tracking-wide-eyebrow uppercase text-ink-mute flex items-center justify-between gap-3 flex-wrap">
            <span class="truncate min-w-0">📍 <span x-text="match.venue"></span></span>
            <div class="flex items-center gap-3 flex-wrap justify-end">
                <span x-show="match.savedFlash" x-cloak class="text-pitch font-display font-bold">Guardado ✓</span>
                <span x-show="match.saveError" x-cloak class="text-alerta font-display font-bold" x-text="match.saveError"></span>
                <span x-show="match.is_locked && match.status !== 'finished' && !match.has_prediction" class="italic">Sin pronóstico</span>
                <span x-show="match.has_prediction && match.status !== 'finished' && !match.is_locked" class="text-pitch font-display font-bold">Pronóstico guardado</span>

                {{-- Botón Ver Pronósticos: solo visible si está bloqueado o finalizado --}}
                <button type="button"
                        x-show="match.is_locked || match.status === 'finished'"
                        @click="$root.openPredictionsModal(match.id)"
                        class="font-display font-bold text-[11px] uppercase tracking-wide-cta px-3 py-1.5 rounded-md bg-pitch text-bone hover:bg-pitch-deep transition-all duration-fast">
                    👁 Ver pronósticos
                </button>
            </div>
        </div>
    </div>
@endif
