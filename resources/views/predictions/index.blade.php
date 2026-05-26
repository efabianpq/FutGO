@extends('layouts.app')

@section('title', 'Mis Pronósticos')

@push('head')
<style>
    [x-cloak] { display: none !important; }
</style>
@endpush

@section('content')
<div class="max-w-5xl mx-auto px-4 py-6"
     x-data="predictionsApp({
        phases: @js($phases),
        groups: @js($groups),
        urls: {
            update: '{{ url('/predictions') }}',
            states: '{{ route('predictions.states') }}',
        },
        csrf: document.querySelector('meta[name=csrf-token]').content,
     })"
     x-init="init()">

    <div class="flex items-center justify-between mb-4">
        <h1 class="text-2xl font-bold text-pachon-green">⚽ Mis Pronósticos</h1>
        <div class="text-xs text-gray-500" x-show="lastSync" x-cloak>
            Sincronizado: <span x-text="lastSync"></span>
        </div>
    </div>

    <!-- Filtros -->
    <div class="bg-white rounded-lg shadow-sm p-3 mb-6 flex flex-wrap items-center gap-2 sticky top-0 z-10">
        <button type="button"
                @click="filter = 'all'"
                :class="filter === 'all' ? 'bg-pachon-green text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'"
                class="px-3 py-1.5 text-sm font-medium rounded-md transition">
            Todos
        </button>
        <button type="button"
                @click="filter = 'pending'"
                :class="filter === 'pending' ? 'bg-pachon-green text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'"
                class="px-3 py-1.5 text-sm font-medium rounded-md transition">
            Solo abiertos sin pronóstico
        </button>

        <label class="ml-auto text-sm flex items-center gap-2">
            <span class="text-gray-600">Grupo:</span>
            <select x-model="groupFilter"
                    class="rounded-md border-gray-300 text-sm focus:ring-pachon-green focus:border-pachon-green">
                <option value="">Todos</option>
                <template x-for="g in groups" :key="g">
                    <option :value="g" x-text="'Grupo ' + g"></option>
                </template>
            </select>
        </label>
    </div>

    <!-- Resumen vacío -->
    <div x-show="visibleCount === 0" x-cloak class="bg-white rounded-lg shadow p-8 text-center text-gray-500">
        No hay partidos que coincidan con el filtro seleccionado.
    </div>

    <!-- Fases -->
    <template x-for="phase in phases" :key="phase.key">
        <section x-show="phaseVisibleCount(phase) > 0" x-cloak class="mb-8">
            <h2 class="text-lg font-bold text-pachon-green-dark mb-3 flex items-center gap-2">
                <span x-text="phase.label"></span>
                <span class="text-xs font-normal bg-pachon-green/10 text-pachon-green px-2 py-0.5 rounded-full"
                      x-text="phaseVisibleCount(phase) + ' partidos'"></span>
            </h2>

            <div class="space-y-3">
                <template x-for="match in phase.matches" :key="match.id">
                    <div x-show="isVisible(match)" x-cloak
                         class="bg-white rounded-lg shadow-sm border overflow-hidden"
                         :class="cardBorderClass(match)">

                        <!-- Header del partido -->
                        <div class="flex items-center justify-between px-4 py-2 text-xs bg-gray-50 border-b">
                            <div class="flex items-center gap-2 text-gray-600">
                                <span class="font-mono">#<span x-text="match.match_number"></span></span>
                                <span x-show="match.group_name" class="font-semibold">Grupo <span x-text="match.group_name"></span></span>
                                <span>·</span>
                                <span x-text="match.date_label + ' GMT-5'"></span>
                            </div>
                            <div>
                                <span x-show="!match.is_locked && match.status !== 'finished'" class="text-green-600 font-semibold">🟢 Abierto</span>
                                <span x-show="match.is_locked && match.status !== 'finished'" class="text-red-600 font-semibold">🔴 Bloqueado</span>
                                <span x-show="match.status === 'finished'" class="text-gray-700 font-semibold">⚫ Finalizado</span>
                            </div>
                        </div>

                        <!-- Cuerpo del partido -->
                        <div class="p-4 grid grid-cols-1 md:grid-cols-3 gap-4 items-center">
                            <!-- Equipo local -->
                            <div class="text-right">
                                <p class="font-semibold">
                                    <span x-text="match.home_team"></span>
                                    <span class="ml-1 text-xl" x-text="match.home_flag"></span>
                                </p>
                            </div>

                            <!-- Marcador -->
                            <div class="flex items-center justify-center gap-2">
                                <input type="number" min="0" max="20"
                                       x-model.number="match.home_score"
                                       :disabled="match.is_locked || match.status === 'finished'"
                                       @blur="save(match)"
                                       @keydown.enter.prevent="$event.target.blur()"
                                       class="w-14 text-center text-xl font-bold rounded-md border-gray-300 disabled:bg-gray-100 disabled:text-gray-500 focus:ring-pachon-green focus:border-pachon-green"
                                       :placeholder="match.is_locked && match.home_score === null ? '—' : ''">

                                <span class="text-gray-400 font-bold">:</span>

                                <input type="number" min="0" max="20"
                                       x-model.number="match.away_score"
                                       :disabled="match.is_locked || match.status === 'finished'"
                                       @blur="save(match)"
                                       @keydown.enter.prevent="$event.target.blur()"
                                       class="w-14 text-center text-xl font-bold rounded-md border-gray-300 disabled:bg-gray-100 disabled:text-gray-500 focus:ring-pachon-green focus:border-pachon-green"
                                       :placeholder="match.is_locked && match.away_score === null ? '—' : ''">
                            </div>

                            <!-- Equipo visitante -->
                            <div>
                                <p class="font-semibold">
                                    <span class="mr-1 text-xl" x-text="match.away_flag"></span>
                                    <span x-text="match.away_team"></span>
                                </p>
                            </div>
                        </div>

                        <!-- Footer: estadio + feedback de guardado / resultado oficial / puntos -->
                        <div class="px-4 py-2 bg-gray-50 border-t text-xs text-gray-600 flex flex-wrap items-center justify-between gap-2">
                            <span class="truncate">📍 <span x-text="match.venue"></span></span>

                            <!-- Sin pronóstico cuando bloqueado -->
                            <span x-show="match.is_locked && match.status !== 'finished' && !match.has_prediction"
                                  class="text-gray-500 italic">Sin pronóstico</span>

                            <!-- Estado guardado -->
                            <span x-show="match.savedFlash" x-cloak
                                  x-transition.opacity
                                  class="text-pachon-green font-semibold">Guardado ✓</span>
                            <span x-show="match.saveError" x-cloak class="text-red-600 font-semibold" x-text="match.saveError"></span>

                            <!-- Resultado oficial + puntos cuando finalizado -->
                            <template x-if="match.status === 'finished'">
                                <div class="flex items-center gap-3">
                                    <span>
                                        Resultado oficial:
                                        <span class="font-mono font-bold" x-text="(match.home_score_official ?? '?') + ' - ' + (match.away_score_official ?? '?')"></span>
                                    </span>
                                    <span :class="pointsClass(match.points_earned)"
                                          class="px-2 py-0.5 rounded font-bold border text-sm">
                                        <span x-text="(match.points_earned ?? 0) + ' pts'"></span>
                                    </span>
                                </div>
                            </template>
                        </div>
                    </div>
                </template>
            </div>
        </section>
    </template>
</div>

<script>
function predictionsApp({phases, groups, urls, csrf}) {
    return {
        phases,
        groups,
        urls,
        csrf,
        filter: 'all',
        groupFilter: '',
        lastSync: '',

        init() {
            // Inicializar campos por partido
            this.phases.forEach(p => p.matches.forEach(m => {
                m.savedFlash = false;
                m.saveError = '';
            }));
            // Polling cada 30 segundos
            this.pollStates();
            setInterval(() => this.pollStates(), 30000);
        },

        get visibleCount() {
            return this.phases.reduce((acc, p) => acc + this.phaseVisibleCount(p), 0);
        },

        phaseVisibleCount(phase) {
            return phase.matches.filter(m => this.isVisible(m)).length;
        },

        isVisible(match) {
            if (this.groupFilter && match.group_name !== this.groupFilter) return false;
            if (this.filter === 'pending') {
                return !match.is_locked && match.status !== 'finished' && !match.has_prediction;
            }
            return true;
        },

        cardBorderClass(match) {
            if (match.status === 'finished') return 'border-gray-300';
            if (match.is_locked) return 'border-red-300';
            return 'border-green-300';
        },

        pointsClass(p) {
            const v = parseInt(p ?? 0, 10);
            if (v === 5) return 'bg-amber-100 text-amber-900 border-amber-400';
            if (v === 3) return 'bg-emerald-100 text-emerald-900 border-emerald-400';
            if (v === 2) return 'bg-blue-100 text-blue-900 border-blue-400';
            if (v === 1) return 'bg-yellow-100 text-yellow-900 border-yellow-400';
            return 'bg-gray-100 text-gray-700 border-gray-300';
        },

        async save(match) {
            if (match.is_locked || match.status === 'finished') return;
            if (match.home_score === null || match.home_score === '' ||
                match.away_score === null || match.away_score === '') {
                return; // No autoguardar parciales
            }

            match.saveError = '';

            try {
                const res = await fetch(`${this.urls.update}/${match.id}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': this.csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({
                        home_score: match.home_score,
                        away_score: match.away_score,
                    }),
                });

                if (!res.ok) {
                    const data = await res.json().catch(() => ({}));
                    match.saveError = data.error || 'Error al guardar';
                    return;
                }

                match.has_prediction = true;
                match.savedFlash = true;
                setTimeout(() => match.savedFlash = false, 1800);
            } catch (e) {
                match.saveError = 'Error de red';
            }
        },

        async pollStates() {
            try {
                const res = await fetch(this.urls.states, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                if (!res.ok) return;
                const data = await res.json();

                const stateById = {};
                data.matches.forEach(s => stateById[s.id] = s);

                this.phases.forEach(p => p.matches.forEach(m => {
                    const s = stateById[m.id];
                    if (!s) return;
                    m.is_locked = s.is_locked;
                    m.status = s.status;
                    m.home_score_official = s.home_score_official;
                    m.away_score_official = s.away_score_official;
                    if (s.points_earned !== undefined) {
                        m.points_earned = s.points_earned;
                    }
                }));

                this.lastSync = new Date().toLocaleTimeString('es-CO', {hour: '2-digit', minute: '2-digit', second: '2-digit'});
            } catch (e) {
                // silencio
            }
        },
    };
}
</script>
@endsection
