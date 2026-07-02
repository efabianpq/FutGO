@php
    $formatLabels = [
        'groups_and_knockout' => 'Grupos + Eliminación',
        'knockout_only'       => 'Solo eliminación',
        'round_robin'         => 'Todos contra todos',
        'liga'                => 'Liga / Abierto (fixture manual)',
    ];
    $categoryLabels = [
        'libre'     => 'Libre',
        'veteranos' => 'Veteranos',
        'sub15'     => 'Sub-15',
        'sub17'     => 'Sub-17',
        'sub20'     => 'Sub-20',
        'femenino'  => 'Femenino',
        'mixto'     => 'Mixto',
    ];
    $knockoutLabels = [
        'penalties'            => 'Penales',
        'extra_time_penalties' => 'Tiempo extra + penales',
        'replay'               => 'Repetición del partido',
    ];
    $tiebreakerLabels = [
        'goal_difference' => 'Diferencia de gol',
        'goals_for'       => 'Goles a favor',
        'head_to_head'    => 'Enfrentamiento directo',
        'fair_play'       => 'Fair play',
        'drawing'         => 'Sorteo',
    ];
    $statLabels = [
        'goals'          => 'Goles',
        'assists'        => 'Asistencias',
        'yellow_cards'   => 'Tarjetas amarillas',
        'red_cards'      => 'Tarjetas rojas',
        // H11: "Minutos jugados" se retiró como estadística trackeable.
    ];
    $selectedStats = old('stats', $tournament->stats_config ?? []);
    $fmt = fn ($d) => $d ? \Illuminate\Support\Carbon::parse($d)->format('Y-m-d\TH:i') : '';

    // Orden de desempate: habilitados primero (en orden), luego el resto deshabilitado.
    $tbDefault = $tournament->tiebreaker_order ?: $tournament->getDefaultTiebreakerOrder();
    $tbEnabled = old('tiebreaker_order', $tbDefault);
    $tbItems = [];
    foreach ($tbEnabled as $k) {
        if (isset($tiebreakerLabels[$k])) {
            $tbItems[] = ['key' => $k, 'label' => $tiebreakerLabels[$k], 'enabled' => true];
        }
    }
    foreach ($tiebreakerLabels as $k => $lbl) {
        if (! in_array($k, $tbEnabled, true)) {
            $tbItems[] = ['key' => $k, 'label' => $lbl, 'enabled' => false];
        }
    }

    $labelCls = 'font-mono text-[11px] tracking-wide-label uppercase text-ink-soft';
    $inputCls = fn ($field) => 'h-[46px] px-3.5 bg-white border-[1.5px] '
        . ($errors->has($field) ? 'border-alerta' : 'border-line')
        . ' rounded-md text-[15px] focus:border-pitch focus:ring-0';
    $numCls = fn ($field) => 'h-[46px] px-3.5 bg-white border-[1.5px] '
        . ($errors->has($field) ? 'border-alerta' : 'border-line')
        . ' rounded-md text-[15px] font-mono focus:border-pitch focus:ring-0';
@endphp

<div x-data="{ format: '{{ old('format', $tournament->format ?? 'groups_and_knockout') }}', withGroups: @js($formatsWithGroups) }"
     class="space-y-10">

    {{-- ══ SECCIÓN 1 — INFORMACIÓN GENERAL ══════════════════════════════════ --}}
    <section>
        <p class="font-display font-bold text-pitch uppercase text-[16px] mb-1">1 · Información general</p>
        <p class="text-[12px] text-ink-mute mb-4">Datos básicos, ubicación y visibilidad del torneo.</p>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
            <div class="flex flex-col gap-1.5 sm:col-span-2">
                <label for="name" class="{{ $labelCls }}">Nombre del torneo *</label>
                <input type="text" name="name" id="name" maxlength="100" required
                       value="{{ old('name', $tournament->name) }}" class="{{ $inputCls('name') }}">
                @error('name')<p class="text-[12px] text-alerta">{{ $message }}</p>@enderror
            </div>

            <div class="flex flex-col gap-1.5 sm:col-span-2">
                <label for="description" class="{{ $labelCls }}">Descripción</label>
                <textarea name="description" id="description" rows="3"
                          class="px-3.5 py-2 bg-white border-[1.5px] border-line rounded-md text-[15px] focus:border-pitch focus:ring-0">{{ old('description', $tournament->description) }}</textarea>
            </div>

            <div class="flex flex-col gap-1.5">
                <label for="sport" class="{{ $labelCls }}">Deporte *</label>
                <input type="text" name="sport" id="sport" maxlength="50" required
                       value="{{ old('sport', $tournament->sport ?? 'futbol') }}" class="{{ $inputCls('sport') }}">
                @error('sport')<p class="text-[12px] text-alerta">{{ $message }}</p>@enderror
            </div>

            <div class="flex flex-col gap-1.5">
                <label for="category" class="{{ $labelCls }}">Categoría *</label>
                <select name="category" id="category" required class="{{ $inputCls('category') }}">
                    @foreach ($categoryLabels as $value => $label)
                        <option value="{{ $value }}" @selected(old('category', $tournament->category ?? 'libre') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('category')<p class="text-[12px] text-alerta">{{ $message }}</p>@enderror
            </div>

            <div class="flex flex-col gap-1.5">
                <label for="city" class="{{ $labelCls }}">Ciudad</label>
                <input type="text" name="city" id="city" maxlength="80"
                       value="{{ old('city', $tournament->city) }}" class="{{ $inputCls('city') }}">
                @error('city')<p class="text-[12px] text-alerta">{{ $message }}</p>@enderror
            </div>

            <div class="flex flex-col gap-1.5">
                <label for="venue" class="{{ $labelCls }}">Cancha principal</label>
                <input type="text" name="venue" id="venue" maxlength="100"
                       value="{{ old('venue', $tournament->venue) }}" class="{{ $inputCls('venue') }}">
                @error('venue')<p class="text-[12px] text-alerta">{{ $message }}</p>@enderror
            </div>

            <div class="flex flex-col gap-1.5">
                <label for="visibility" class="{{ $labelCls }}">Visibilidad *</label>
                <select name="visibility" id="visibility" required class="{{ $inputCls('visibility') }}">
                    <option value="public" @selected(old('visibility', $tournament->visibility ?? 'public') === 'public')>Público</option>
                    <option value="private" @selected(old('visibility', $tournament->visibility ?? 'public') === 'private')>Privado</option>
                </select>
                @error('visibility')<p class="text-[12px] text-alerta">{{ $message }}</p>@enderror
            </div>

            <div class="flex flex-col gap-1.5">
                <label for="logo" class="{{ $labelCls }}">Logo (imagen)</label>
                @if ($tournament->logo_url)
                    <img src="{{ $tournament->logo_url }}" alt="Logo actual" class="h-16 w-16 object-cover rounded-md border border-line mb-1">
                @endif
                <input type="file" name="logo" id="logo" accept="image/jpeg,image/png,image/webp"
                       class="text-[13px] file:mr-3 file:py-2 file:px-4 file:rounded-md file:border-0 file:bg-pitch file:text-bone file:font-semibold file:text-[12px] file:cursor-pointer">
                <p class="text-[11px] text-ink-mute">JPG, PNG o WEBP. Máx. 2 MB.</p>
                @error('logo')<p class="text-[12px] text-alerta">{{ $message }}</p>@enderror
            </div>

            <div class="flex flex-col gap-1.5">
                <label for="banner" class="{{ $labelCls }}">Banner (imagen)</label>
                @if ($tournament->banner_url)
                    <img src="{{ $tournament->banner_url }}" alt="Banner actual" class="h-16 w-full object-cover rounded-md border border-line mb-1">
                @endif
                <input type="file" name="banner" id="banner" accept="image/jpeg,image/png,image/webp"
                       class="text-[13px] file:mr-3 file:py-2 file:px-4 file:rounded-md file:border-0 file:bg-pitch file:text-bone file:font-semibold file:text-[12px] file:cursor-pointer">
                <p class="text-[11px] text-ink-mute">JPG, PNG o WEBP. Máx. 2 MB.</p>
                @error('banner')<p class="text-[12px] text-alerta">{{ $message }}</p>@enderror
            </div>
        </div>
    </section>

    {{-- ══ SECCIÓN 2 — FORMATO ══════════════════════════════════════════════ --}}
    <section class="border-t border-line-soft pt-8">
        <p class="font-display font-bold text-pitch uppercase text-[16px] mb-1">2 · Formato</p>
        <p class="text-[12px] text-ink-mute mb-4">Modalidad, grupos, puntuación y criterios de desempate.</p>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
            <div class="flex flex-col gap-1.5 sm:col-span-2">
                <label for="format" class="{{ $labelCls }}">Modalidad *</label>
                <select name="format" id="format" x-model="format" required class="{{ $inputCls('format') }}">
                    @foreach ($formatLabels as $value => $label)
                        <option value="{{ $value }}" @selected(old('format', $tournament->format ?? 'groups_and_knockout') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('format')<p class="text-[12px] text-alerta">{{ $message }}</p>@enderror
            </div>
        </div>

        {{-- Configuración de grupos --}}
        <div x-show="withGroups.includes(format)" x-cloak class="mt-5">
            <p class="{{ $labelCls }} mb-3">Configuración de grupos</p>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
                <div class="flex flex-col gap-1.5">
                    <label for="groups_count" class="{{ $labelCls }}">Cantidad de grupos</label>
                    <input type="number" name="groups_count" id="groups_count" min="1" max="16"
                           value="{{ old('groups_count', $tournament->groups_count ?? 1) }}" class="{{ $numCls('groups_count') }}">
                    @error('groups_count')<p class="text-[12px] text-alerta">{{ $message }}</p>@enderror
                </div>
                <div class="flex flex-col gap-1.5">
                    <label for="teams_per_group" class="{{ $labelCls }}">Equipos por grupo</label>
                    <input type="number" name="teams_per_group" id="teams_per_group" min="2" max="8"
                           value="{{ old('teams_per_group', $tournament->teams_per_group ?? 4) }}" class="{{ $numCls('teams_per_group') }}">
                    @error('teams_per_group')<p class="text-[12px] text-alerta">{{ $message }}</p>@enderror
                </div>
                <div class="flex flex-col gap-1.5">
                    <label for="classifies_per_group" class="{{ $labelCls }}">Clasifican por grupo</label>
                    <input type="number" name="classifies_per_group" id="classifies_per_group" min="1" max="7"
                           value="{{ old('classifies_per_group', $tournament->classifies_per_group ?? 2) }}" class="{{ $numCls('classifies_per_group') }}">
                    @error('classifies_per_group')<p class="text-[12px] text-alerta">{{ $message }}</p>@enderror
                </div>
            </div>
            <p class="text-[12px] text-ink-mute mt-2">El máximo de equipos se calcula automáticamente: grupos × equipos por grupo.</p>
            @error('max_teams')<p class="text-[12px] text-alerta">{{ $message }}</p>@enderror
        </div>

        {{-- max_teams para eliminación directa --}}
        <div x-show="format === 'knockout_only'" x-cloak class="mt-5">
            <div class="flex flex-col gap-1.5 sm:max-w-xs">
                <label for="max_teams" class="{{ $labelCls }}">Máximo de equipos</label>
                <input type="number" name="max_teams" id="max_teams" min="2" max="128"
                       value="{{ old('max_teams', $tournament->max_teams ?? 8) }}" class="{{ $numCls('max_teams') }}"
                       x-bind:disabled="format !== 'knockout_only'">
                <p class="text-[12px] text-ink-mute mt-1">Debe ser potencia de 2 (4, 8, 16...).</p>
                @error('max_teams')<p class="text-[12px] text-alerta">{{ $message }}</p>@enderror
            </div>
        </div>

        {{-- H6: configuración del formato LIGA --}}
        <div x-show="format === 'liga'" x-cloak class="mt-5">
            <p class="{{ $labelCls }} mb-3">Configuración de la liga</p>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div class="flex flex-col gap-1.5">
                    <label for="max_teams_liga" class="{{ $labelCls }}">Máximo de equipos</label>
                    <input type="number" id="max_teams_liga" min="2" max="128"
                           value="{{ old('max_teams', $tournament->max_teams ?? 10) }}" class="{{ $numCls('max_teams') }}"
                           x-bind:name="format === 'liga' ? 'max_teams' : ''">
                    <p class="text-[12px] text-ink-mute mt-1">Cantidad de equipos que participan en la tabla.</p>
                </div>
                <div class="flex flex-col gap-1.5">
                    <label for="classifies_liga" class="{{ $labelCls }}">Clasifican a eliminatoria</label>
                    <input type="number" id="classifies_liga" min="0" max="64"
                           value="{{ old('classifies_per_group', $tournament->classifies_per_group ?? 4) }}" class="{{ $numCls('classifies_per_group') }}"
                           x-bind:name="format === 'liga' ? 'classifies_per_group' : ''">
                    <p class="text-[12px] text-ink-mute mt-1">Potencia de 2 (2, 4, 8...). Usa 0 o 1 para liga sin eliminatoria.</p>
                    @error('classifies_per_group')<p class="text-[12px] text-alerta">{{ $message }}</p>@enderror
                </div>
            </div>
            <p class="text-[12px] text-ink-mute mt-2">
                En liga tú armas el calendario: agregas partidos a mano o auto-generas todos contra todos,
                y luego generas la eliminatoria con los mejores de la tabla.
            </p>
            @error('max_teams')<p class="text-[12px] text-alerta">{{ $message }}</p>@enderror
        </div>

        {{-- Tercer puesto --}}
        <label class="flex items-center gap-3 cursor-pointer mt-5">
            <input type="checkbox" name="third_place_match" value="1"
                   @checked(old('third_place_match', $tournament->third_place_match))
                   class="w-5 h-5 rounded border-line text-pitch focus:ring-pitch">
            <span class="font-display font-semibold text-pitch uppercase text-[14px]">Partido por el tercer puesto</span>
        </label>

        {{-- MVP (figura del partido) --}}
        <label class="flex items-center gap-3 cursor-pointer mt-3">
            <input type="checkbox" name="mvp_enabled" value="1"
                   @checked(old('mvp_enabled', $tournament->mvp_enabled))
                   class="w-5 h-5 rounded border-line text-pitch focus:ring-pitch">
            <span class="font-display font-semibold text-pitch uppercase text-[14px]">Registrar MVP (figura del partido)</span>
        </label>

        {{-- Sistema de puntos --}}
        <div class="mt-6">
            <p class="{{ $labelCls }} mb-3">Sistema de puntos</p>
            <div class="grid grid-cols-3 gap-5 sm:max-w-md">
                <div class="flex flex-col gap-1.5">
                    <label for="points_win" class="{{ $labelCls }}">Victoria</label>
                    <input type="number" name="points_win" id="points_win" min="0" max="255"
                           value="{{ old('points_win', $tournament->points_win ?? 3) }}" class="{{ $numCls('points_win') }}">
                </div>
                <div class="flex flex-col gap-1.5">
                    <label for="points_draw" class="{{ $labelCls }}">Empate</label>
                    <input type="number" name="points_draw" id="points_draw" min="0" max="255"
                           value="{{ old('points_draw', $tournament->points_draw ?? 1) }}" class="{{ $numCls('points_draw') }}">
                </div>
                <div class="flex flex-col gap-1.5">
                    <label for="points_loss" class="{{ $labelCls }}">Derrota</label>
                    <input type="number" name="points_loss" id="points_loss" min="0" max="255"
                           value="{{ old('points_loss', $tournament->points_loss ?? 0) }}" class="{{ $numCls('points_loss') }}">
                </div>
            </div>
            @error('points_win')<p class="text-[12px] text-alerta mt-1">{{ $message }}</p>@enderror
            @error('points_draw')<p class="text-[12px] text-alerta mt-1">{{ $message }}</p>@enderror
        </div>

        {{-- Criterios de desempate (ordenables) --}}
        <div class="mt-6" x-data="tiebreakerEditor(@js($tbItems))">
            <p class="{{ $labelCls }} mb-1">Criterios de desempate (ordená por prioridad)</p>
            <p class="text-[12px] text-ink-mute mb-3">Activá los criterios y ordenálos: el primero se aplica primero.</p>

            <ul class="space-y-2 sm:max-w-md">
                <template x-for="(item, index) in items" :key="item.key">
                    <li class="flex items-center gap-3 bg-bone-soft border border-line rounded-md px-3 py-2.5">
                        <input type="checkbox" x-model="item.enabled"
                               class="w-4 h-4 rounded border-line text-pitch focus:ring-pitch">
                        <span class="flex-1 text-[13px] text-ink" x-text="item.label"></span>
                        <span class="font-mono text-[11px] text-ink-mute" x-show="item.enabled" x-text="'#' + (enabledRank(index))"></span>
                        <button type="button" @click="move(index, -1)" :disabled="index === 0"
                                class="px-2 py-1 text-pitch disabled:opacity-30 hover:bg-white rounded">↑</button>
                        <button type="button" @click="move(index, 1)" :disabled="index === items.length - 1"
                                class="px-2 py-1 text-pitch disabled:opacity-30 hover:bg-white rounded">↓</button>
                    </li>
                </template>
            </ul>

            {{-- Hidden inputs en orden, solo habilitados --}}
            <template x-for="item in items" :key="'h-' + item.key">
                <input type="hidden" x-bind:name="item.enabled ? 'tiebreaker_order[]' : ''"
                       x-bind:value="item.enabled ? item.key : ''">
            </template>
        </div>

        {{-- Desempate en eliminación --}}
        <div class="mt-6 flex flex-col gap-1.5 sm:max-w-xs">
            <label for="knockout_tiebreak" class="{{ $labelCls }}">Desempate en eliminación *</label>
            <select name="knockout_tiebreak" id="knockout_tiebreak" required class="{{ $inputCls('knockout_tiebreak') }}">
                @foreach ($knockoutLabels as $value => $label)
                    <option value="{{ $value }}" @selected(old('knockout_tiebreak', $tournament->knockout_tiebreak ?? 'penalties') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            @error('knockout_tiebreak')<p class="text-[12px] text-alerta">{{ $message }}</p>@enderror
        </div>
    </section>

    {{-- ══ SECCIÓN 3 — CONFIGURACIÓN DEPORTIVA ══════════════════════════════ --}}
    <section class="border-t border-line-soft pt-8">
        <p class="font-display font-bold text-pitch uppercase text-[16px] mb-1">3 · Configuración deportiva</p>
        <p class="text-[12px] text-ink-mute mb-4">Reglas de juego por partido y plantel.</p>

        <div class="grid grid-cols-2 sm:grid-cols-4 gap-5">
            <div class="flex flex-col gap-1.5">
                <label for="match_duration" class="{{ $labelCls }}">Duración (min)</label>
                <input type="number" name="match_duration" id="match_duration" min="1" max="255"
                       value="{{ old('match_duration', $tournament->match_duration ?? 90) }}" class="{{ $numCls('match_duration') }}">
                @error('match_duration')<p class="text-[12px] text-alerta">{{ $message }}</p>@enderror
            </div>
            <div class="flex flex-col gap-1.5">
                <label for="min_players_per_team" class="{{ $labelCls }}">Jugadores mín.</label>
                <input type="number" name="min_players_per_team" id="min_players_per_team" min="1" max="255"
                       value="{{ old('min_players_per_team', $tournament->min_players_per_team ?? 7) }}" class="{{ $numCls('min_players_per_team') }}">
                @error('min_players_per_team')<p class="text-[12px] text-alerta">{{ $message }}</p>@enderror
            </div>
            <div class="flex flex-col gap-1.5">
                <label for="max_players_per_team" class="{{ $labelCls }}">Jugadores máx.</label>
                <input type="number" name="max_players_per_team" id="max_players_per_team" min="1" max="255"
                       value="{{ old('max_players_per_team', $tournament->max_players_per_team ?? 25) }}" class="{{ $numCls('max_players_per_team') }}">
                @error('max_players_per_team')<p class="text-[12px] text-alerta">{{ $message }}</p>@enderror
            </div>
            <div class="flex flex-col gap-1.5">
                <label for="max_substitutions" class="{{ $labelCls }}">Cambios</label>
                <input type="number" name="max_substitutions" id="max_substitutions" min="0" max="255"
                       value="{{ old('max_substitutions', $tournament->max_substitutions ?? 5) }}" class="{{ $numCls('max_substitutions') }}">
                @error('max_substitutions')<p class="text-[12px] text-alerta">{{ $message }}</p>@enderror
            </div>
        </div>
    </section>

    {{-- ══ SECCIÓN 4 — INSCRIPCIÓN Y PREMIOS ════════════════════════════════ --}}
    <section class="border-t border-line-soft pt-8">
        <p class="font-display font-bold text-pitch uppercase text-[16px] mb-1">4 · Inscripción y premios</p>
        <p class="text-[12px] text-ink-mute mb-4">Costos, premios y reglamento.</p>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
            <div class="flex flex-col gap-1.5"
                 x-data="{
                    raw: '{{ (int) old('registration_fee', $tournament->registration_fee ?? 0) }}',
                    get formatted() {
                        if (this.raw === '' || this.raw === null) return '';
                        return Number(this.raw).toLocaleString('es-CO');
                    },
                    onInput(e) {
                        this.raw = e.target.value.replace(/\D/g, '');
                        e.target.value = this.formatted;
                    }
                 }">
                <label for="registration_fee_display" class="{{ $labelCls }}">Valor inscripción (COP)</label>
                <div class="relative">
                    <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-ink-mute font-mono pointer-events-none">$</span>
                    <input type="text" inputmode="numeric" id="registration_fee_display"
                           :value="formatted" @input="onInput($event)" placeholder="0"
                           class="w-full pl-7 {{ $numCls('registration_fee') }}">
                </div>
                <input type="hidden" name="registration_fee" :value="raw">
                @error('registration_fee')<p class="text-[12px] text-alerta">{{ $message }}</p>@enderror
            </div>
            <div class="flex flex-col gap-1.5">
                <label for="prize_description" class="{{ $labelCls }}">Descripción del premio</label>
                <input type="text" name="prize_description" id="prize_description" maxlength="200"
                       value="{{ old('prize_description', $tournament->prize_description) }}" class="{{ $inputCls('prize_description') }}">
                @error('prize_description')<p class="text-[12px] text-alerta">{{ $message }}</p>@enderror
            </div>
            <div class="flex flex-col gap-1.5 sm:col-span-2">
                <label for="rules" class="{{ $labelCls }}">Reglamento</label>
                <textarea name="rules" id="rules" rows="4"
                          class="px-3.5 py-2 bg-white border-[1.5px] border-line rounded-md text-[15px] focus:border-pitch focus:ring-0">{{ old('rules', $tournament->rules) }}</textarea>
            </div>
        </div>
    </section>

    {{-- ══ SECCIÓN 5 — CALENDARIO ═══════════════════════════════════════════ --}}
    <section class="border-t border-line-soft pt-8">
        <p class="font-display font-bold text-pitch uppercase text-[16px] mb-1">5 · Calendario</p>
        <p class="text-[12px] text-ink-mute mb-4">Fechas de inscripción y del torneo.</p>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
            <div class="flex flex-col gap-1.5">
                <label for="registration_opens_at" class="{{ $labelCls }}">Apertura inscripciones</label>
                <input type="datetime-local" name="registration_opens_at" id="registration_opens_at"
                       value="{{ old('registration_opens_at', $fmt($tournament->registration_opens_at)) }}" class="{{ $numCls('registration_opens_at') }}">
                @error('registration_opens_at')<p class="text-[12px] text-alerta">{{ $message }}</p>@enderror
            </div>
            <div class="flex flex-col gap-1.5">
                <label for="registration_closes_at" class="{{ $labelCls }}">Cierre inscripciones</label>
                <input type="datetime-local" name="registration_closes_at" id="registration_closes_at"
                       value="{{ old('registration_closes_at', $fmt($tournament->registration_closes_at)) }}" class="{{ $numCls('registration_closes_at') }}">
                @error('registration_closes_at')<p class="text-[12px] text-alerta">{{ $message }}</p>@enderror
            </div>
            <div class="flex flex-col gap-1.5">
                <label for="starts_at" class="{{ $labelCls }}">Inicio del torneo</label>
                <input type="datetime-local" name="starts_at" id="starts_at"
                       value="{{ old('starts_at', $fmt($tournament->starts_at)) }}" class="{{ $numCls('starts_at') }}">
                @error('starts_at')<p class="text-[12px] text-alerta">{{ $message }}</p>@enderror
            </div>
            <div class="flex flex-col gap-1.5">
                <label for="ends_at" class="{{ $labelCls }}">Fin del torneo</label>
                <input type="datetime-local" name="ends_at" id="ends_at"
                       value="{{ old('ends_at', $fmt($tournament->ends_at)) }}" class="{{ $numCls('ends_at') }}">
                @error('ends_at')<p class="text-[12px] text-alerta">{{ $message }}</p>@enderror
            </div>
        </div>
    </section>

    {{-- ══ SECCIÓN 6 — ESTADÍSTICAS A TRACKEAR ══════════════════════════════ --}}
    <section class="border-t border-line-soft pt-8">
        <p class="font-display font-bold text-pitch uppercase text-[16px] mb-1">6 · Estadísticas a trackear</p>
        <p class="text-[12px] text-ink-mute mb-4">Elige qué métricas individuales se registran. (Victorias, empates, derrotas y vallas invictas se calculan siempre.)</p>

        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
            @foreach ($statLabels as $key => $label)
                <label class="flex items-center gap-2.5 bg-bone-soft border border-line rounded-md px-3 py-2.5 cursor-pointer hover:border-pitch transition-colors duration-fast">
                    <input type="checkbox" name="stats[]" value="{{ $key }}"
                           @checked(in_array($key, $selectedStats, true))
                           class="w-4 h-4 rounded border-line text-pitch focus:ring-pitch">
                    <span class="text-[13px] text-ink">{{ $label }}</span>
                </label>
            @endforeach
        </div>
    </section>
</div>

@push('head')
<script>
    function tiebreakerEditor(initial) {
        return {
            items: initial,
            move(index, dir) {
                const target = index + dir;
                if (target < 0 || target >= this.items.length) return;
                const tmp = this.items[index];
                this.items[index] = this.items[target];
                this.items[target] = tmp;
            },
            enabledRank(index) {
                // posición (1-based) entre los habilitados hasta este índice
                let rank = 0;
                for (let i = 0; i <= index; i++) {
                    if (this.items[i].enabled) rank++;
                }
                return rank;
            },
        };
    }
</script>
@endpush
