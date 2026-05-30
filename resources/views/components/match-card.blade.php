{{--
    x-match-card
    Tarjeta de pronóstico — 3 estados: abierto, bloqueado, finalizado.
    Alpine-driven. Espera `match` en el scope padre.

    Colorimetría unificada de puntos (también usada en ranking/show audit y modal):
      5 → bg-gol text-on-green       (oro · exacto)
      3 → bg-pitch text-bone      (verde fuerte · ganador + 1 exacto)
      2 → bg-pitch-mist text-pitch (verde pálido · solo ganador)
      1 → bg-gol/30 text-pitch-deep (oro tinte · un marcador)
      0 → bg-line-soft text-ink-mute (gris · falla)
--}}
@props([
    'match' => null,
    'alpine' => false,
])

@if ($alpine)
    <div class="bg-white border border-line rounded-md shadow-card overflow-hidden">
        {{-- ─── HEADER ─── --}}
        <div class="flex items-center justify-between px-4 py-2.5 font-mono text-[11px] tracking-wide-label uppercase"
             :class="match.status === 'live' || (match.is_locked && match.status !== 'finished') ? 'bg-alerta text-white' : 'bg-pitch text-bone'">
            <span class="flex items-center gap-2 min-w-0">
                <span x-show="match.group_name" class="bg-gol text-on-green px-2 py-0.5 rounded-sm font-display font-bold text-[11px] shrink-0"
                      x-text="'Grupo ' + match.group_name"></span>
                <span class="opacity-90 truncate" x-text="match.date_label"></span>
            </span>

            {{-- Badge de estado (siempre visible, claro) --}}
            <span class="flex items-center gap-2 shrink-0">
                <template x-if="!match.is_locked && match.status !== 'finished'">
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-pill bg-gol text-on-green font-display font-bold text-[10.5px]">Abierto</span>
                </template>
                <template x-if="match.is_locked && match.status !== 'finished'">
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-pill bg-white/15 text-white font-display font-bold text-[10.5px]">
                        <span class="inline-block w-1.5 h-1.5 rounded-full bg-current animate-pulse-live"></span>
                        Bloqueado
                    </span>
                </template>
                <template x-if="match.status === 'finished'">
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-pill bg-bone/20 text-bone font-display font-bold text-[10.5px]">Finalizado</span>
                </template>
            </span>
        </div>

        {{-- ─── BODY: equipos + scores ─── --}}
        <div class="grid grid-cols-[1fr_auto_1fr] items-center px-4 sm:px-5 py-5 gap-3 sm:gap-4">
            {{-- LOCAL --}}
            <div class="flex flex-col items-center gap-2 text-center">
                <div class="w-12 h-12 sm:w-14 sm:h-14 rounded-pill bg-line-soft border-2 border-line flex items-center justify-center text-[26px]"
                     x-text="match.home_flag || '🏳️'"></div>
                <div class="font-display font-bold text-[15px] sm:text-[17px] uppercase tracking-[.02em] leading-tight" x-text="match.home_team"></div>
            </div>

            {{-- CENTRO: inputs (abierto/bloqueado) o resultado oficial (finished) --}}
            <div class="flex flex-col items-center gap-2">
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

                {{-- FINISHED: resultado oficial grande, etiquetado --}}
                <template x-if="match.status === 'finished'">
                    <div class="flex flex-col items-center gap-1">
                        <p class="font-mono text-[10px] tracking-wide-label uppercase text-ink-mute">Resultado oficial</p>
                        <div class="flex items-center gap-2">
                            <span class="font-display font-extrabold text-[32px] sm:text-[40px] leading-none text-pitch" x-text="match.home_score_official"></span>
                            <span class="font-mono text-[12px] text-ink-mute">–</span>
                            <span class="font-display font-extrabold text-[32px] sm:text-[40px] leading-none text-pitch" x-text="match.away_score_official"></span>
                        </div>
                    </div>
                </template>
            </div>

            {{-- VISITANTE --}}
            <div class="flex flex-col items-center gap-2 text-center">
                <div class="w-12 h-12 sm:w-14 sm:h-14 rounded-pill bg-line-soft border-2 border-line flex items-center justify-center text-[26px]"
                     x-text="match.away_flag || '🏳️'"></div>
                <div class="font-display font-bold text-[15px] sm:text-[17px] uppercase tracking-[.02em] leading-tight" x-text="match.away_team"></div>
            </div>
        </div>

        {{-- ─── STRIP DE PRONÓSTICO vs RESULTADO (solo finished) ─── --}}
        <template x-if="match.status === 'finished'">
            <div class="px-4 sm:px-5 py-3 bg-bone-soft border-t border-line-soft flex items-center justify-between gap-3 flex-wrap">
                <div class="flex flex-col gap-0.5 min-w-0">
                    <p class="font-mono text-[10px] tracking-wide-label uppercase text-ink-mute">Tu pronóstico</p>
                    <p class="font-display font-extrabold text-[22px] leading-none">
                        <template x-if="match.has_prediction">
                            <span class="text-pitch" x-text="match.home_score + ' – ' + match.away_score"></span>
                        </template>
                        <template x-if="!match.has_prediction">
                            <span class="font-body font-normal text-body-s text-ink-mute italic">Sin pronóstico</span>
                        </template>
                    </p>
                </div>
                {{-- Badge de puntos con colorimetría unificada --}}
                <span class="inline-flex items-center font-display font-bold border rounded-pill tracking-wide-label uppercase px-3 py-1.5 text-[12px] shrink-0"
                      :class="(match.points_earned ?? 0) === 5 ? 'bg-gol text-on-green border-gol-deep' :
                              ((match.points_earned ?? 0) === 3 ? 'bg-pitch text-bone border-pitch-deep' :
                              ((match.points_earned ?? 0) === 2 ? 'bg-pitch-mist text-pitch border-pitch' :
                              ((match.points_earned ?? 0) === 1 ? 'bg-gol/30 text-pitch-deep border-gol/50' :
                              'bg-line-soft text-ink-mute border-line')))"
                      x-text="(match.points_earned ?? 0) + ' pts'"></span>
            </div>
        </template>

        {{-- ─── FOOTER ─── --}}
        <div class="px-4 sm:px-5 py-3 bg-bone-soft border-t border-line-soft font-mono text-[11px] tracking-wide-eyebrow uppercase text-ink-mute flex items-center justify-between gap-3 flex-wrap">
            <span class="truncate min-w-0">📍 <span x-text="match.venue"></span></span>
            <div class="flex items-center gap-3 flex-wrap justify-end">
                <span x-show="match.savedFlash" x-cloak class="text-pitch font-display font-bold">Guardado ✓</span>
                <span x-show="match.saveError" x-cloak class="text-alerta font-display font-bold" x-text="match.saveError"></span>
                <span x-show="match.is_locked && match.status !== 'finished' && !match.has_prediction" class="italic">Sin pronóstico</span>
                <span x-show="match.has_prediction && match.status !== 'finished' && !match.is_locked" class="text-pitch font-display font-bold">Pronóstico guardado</span>

                {{-- Botón Ver pronósticos — visible si bloqueado o finalizado --}}
                <button type="button"
                        x-show="match.is_locked || match.status === 'finished'"
                        @click="$dispatch('open-predictions-modal', {matchId: match.id})"
                        class="font-display font-bold text-[11px] uppercase tracking-wide-cta px-3 py-1.5 rounded-md bg-pitch text-bone hover:bg-pitch-deep transition-all duration-fast">
                    👁 Ver pronósticos
                </button>
            </div>
        </div>
    </div>
@endif
