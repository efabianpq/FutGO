@extends('layouts.app')

@section('title', 'Mis Pronósticos')

@section('content')
<div class="max-w-6xl mx-auto px-4 py-6"
     x-data="predictionsApp({
        phases: @js($phases),
        groups: @js($groups),
        urls: {
            update: '{{ url('/predictions') }}',
            states: '{{ route('predictions.states') }}',
            byMatch: '{{ url('/partidos') }}',
        },
        csrf: document.querySelector('meta[name=csrf-token]').content,
     })"
     x-init="init()"
     @open-predictions-modal.window="openPredictionsModal($event.detail.matchId)">

    {{-- Hero pequeño --}}
    <div class="mb-6 flex items-end justify-between flex-wrap gap-3">
        <div>
            <p class="eyebrow">Tus pronósticos</p>
            <h1 class="font-display font-bold text-display-m sm:text-display-l text-pitch uppercase mt-2 leading-[0.96]">
                Mis pronósticos
            </h1>
        </div>
        <div class="font-mono text-[11px] tracking-wide-label uppercase text-ink-mute" x-show="lastSync" x-cloak>
            Sincronizado <span x-text="lastSync"></span>
        </div>
    </div>

    {{-- Filtro como segmented control --}}
    <div class="bg-white border border-line rounded-md p-1 mb-6 inline-flex items-center gap-2 sticky top-2 z-10 shadow-card">
        <label class="font-mono text-[11px] tracking-wide-label uppercase text-ink-soft pl-3">Filtro</label>
        <select x-model="filter"
                class="font-display font-semibold text-[13px] uppercase tracking-[.10em] bg-pitch text-bone border-pitch rounded-md py-2 px-3 focus:ring-0 focus:border-pitch-deep">
            <option value="all">Todos los partidos</option>
            <option value="pending">Partidos pendientes de pronosticar</option>
            <template x-for="g in groups" :key="g">
                <option :value="'group:' + g" x-text="'Grupo ' + g"></option>
            </template>
            <option value="phase:dieciseisavos">Dieciseisavos de Final</option>
            <option value="phase:octavos">Octavos de Final</option>
            <option value="phase:cuartos">Cuartos de Final</option>
            <option value="phase:semifinal">Semifinales</option>
            <option value="phase:3er_puesto">Tercer y Cuarto Puesto</option>
            <option value="phase:final">Final</option>
        </select>
    </div>

    {{-- Empty state --}}
    <div x-show="visibleCount === 0" x-cloak class="bg-white border border-line rounded-md p-10 text-center">
        <p class="font-display font-bold text-display-s text-ink uppercase">No hay partidos para mostrar</p>
        <p class="font-mono text-[11px] tracking-wide-label uppercase text-ink-mute mt-2">Probá con otro filtro</p>
    </div>

    {{-- ──────────── MODAL: Pronósticos del partido ──────────── --}}
    <div x-show="modal.open" x-cloak x-transition.opacity
         class="fixed inset-0 z-50 flex sm:items-center justify-center bg-pitch/70 backdrop-blur-sm"
         @click.self="closePredictionsModal()"
         @keydown.escape.window="closePredictionsModal()">

        <div class="bg-bone w-full sm:max-w-2xl sm:rounded-lg shadow-modal flex flex-col max-h-screen sm:max-h-[90vh] overflow-hidden">
            {{-- Modal header --}}
            <header class="bg-pitch text-bone px-5 py-4 flex items-start justify-between gap-3 flex-shrink-0">
                <div class="min-w-0">
                    <p class="font-mono text-[10.5px] tracking-wide-label uppercase opacity-70">Pronósticos del partido</p>
                    <template x-if="modal.match">
                        <h2 class="font-display font-bold text-display-s uppercase mt-1 truncate">
                            <span x-text="(modal.match.home_flag || '') + ' ' + modal.match.home_team"></span>
                            <span class="text-gol">vs</span>
                            <span x-text="(modal.match.away_flag || '') + ' ' + modal.match.away_team"></span>
                        </h2>
                    </template>
                    <template x-if="modal.match && modal.match.is_finished">
                        <p class="font-display font-extrabold text-display-m text-gol mt-2">
                            <span x-text="modal.match.home_score_official + ' - ' + modal.match.away_score_official"></span>
                        </p>
                    </template>
                    <template x-if="modal.match && !modal.match.is_finished">
                        <p class="font-mono text-[11px] tracking-wide-label uppercase text-bone/70 mt-2">
                            Resultado pendiente · <span x-text="modal.match.date_label"></span>
                        </p>
                    </template>
                </div>
                <button type="button" @click="closePredictionsModal()" class="text-bone/70 hover:text-bone p-1 flex-shrink-0" aria-label="Cerrar">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </header>

            {{-- Body --}}
            <div class="flex-1 overflow-y-auto bg-white">
                <div x-show="modal.loading" class="px-5 py-10 text-center">
                    <p class="font-mono text-[11px] tracking-wide-label uppercase text-ink-mute">Cargando pronósticos…</p>
                </div>
                <div x-show="modal.error" x-cloak class="px-5 py-10 text-center">
                    <p class="font-mono text-[11px] tracking-wide-label uppercase text-alerta" x-text="modal.error"></p>
                </div>
                <table x-show="!modal.loading && !modal.error && modal.rows.length > 0" x-cloak class="w-full">
                    <thead class="bg-bone-soft border-b border-line">
                        <tr class="font-mono text-[10.5px] tracking-wide-label uppercase text-ink-soft text-left">
                            <th class="px-5 py-3">Participante</th>
                            <th class="px-5 py-3 text-center">Pronóstico</th>
                            <th class="px-5 py-3 text-right">Puntos</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line-soft">
                        <template x-for="(row, idx) in modal.rows" :key="row.user_id">
                            <tr class="hover:bg-bone-soft transition-colors duration-fast">
                                <td class="px-5 py-3 font-display font-semibold text-ink" x-text="row.name"></td>
                                <td class="px-5 py-3 text-center">
                                    <span x-show="row.prediction" class="font-display font-extrabold text-display-s text-pitch" x-text="row.prediction"></span>
                                    <span x-show="!row.prediction" class="font-body italic text-body-s text-ink-mute">Sin pronóstico</span>
                                </td>
                                <td class="px-5 py-3 text-right">
                                    {{-- Mismas clases que match-card y ranking/show para coherencia visual --}}
                                    <template x-if="row.points_earned !== null">
                                        <span class="inline-flex items-center font-display font-bold border rounded-pill tracking-wide-label uppercase px-2.5 py-1 text-[11px]"
                                              :class="row.points_earned === 5 ? 'bg-gol text-on-green border-gol-deep' :
                                                      (row.points_earned === 3 ? 'bg-pitch text-bone border-pitch-deep' :
                                                      (row.points_earned === 2 ? 'bg-pitch-mist text-pitch border-pitch' :
                                                      (row.points_earned === 1 ? 'bg-gol/30 text-pitch-deep border-gol/50' :
                                                      'bg-line-soft text-ink-mute border-line')))"
                                              x-text="row.points_earned + ' pts'"></span>
                                    </template>
                                    <template x-if="row.points_earned === null">
                                        <span class="font-mono text-[10.5px] tracking-wide-label uppercase text-ink-mute italic">Pendiente</span>
                                    </template>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
                <div x-show="!modal.loading && !modal.error && modal.rows.length === 0" x-cloak class="px-5 py-10 text-center">
                    <p class="font-mono text-[11px] tracking-wide-label uppercase text-ink-mute">No hay participantes para mostrar.</p>
                </div>
            </div>

            {{-- Footer del modal --}}
            <footer class="px-5 py-3 border-t border-line bg-bone-soft flex items-center justify-between gap-3 flex-shrink-0">
                <span class="font-mono text-[11px] tracking-wide-label uppercase text-ink-mute">
                    <span x-text="modal.rows.length"></span> participantes
                </span>
                <button type="button" @click="closePredictionsModal()"
                        class="font-display font-bold text-[13px] uppercase tracking-wide-cta px-3.5 py-2 rounded-md bg-pitch text-bone hover:bg-pitch-deep transition-all duration-fast">
                    Cerrar
                </button>
            </footer>
        </div>
    </div>

    {{-- Fases --}}
    <template x-for="phase in phases" :key="phase.key">
        <section x-show="phaseVisibleCount(phase) > 0" x-cloak class="mb-10">
            <header class="flex items-end justify-between mb-4 pb-2 border-b-2 border-pitch">
                <h2 class="font-display font-bold text-display-s text-pitch uppercase" x-text="phase.label"></h2>
                <span class="font-mono text-[11px] tracking-wide-label uppercase text-ink-mute">
                    <span x-text="phaseVisibleCount(phase)"></span> partidos
                </span>
            </header>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <template x-for="match in phase.matches" :key="match.id">
                    <div x-show="isVisible(match)" x-cloak>
                        <x-match-card :alpine="true" />
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
        lastSync: '',
        modal: { open: false, loading: false, error: '', match: null, rows: [] },

        init() {
            this.phases.forEach(p => p.matches.forEach(m => {
                m.savedFlash = false;
                m.saveError = '';
            }));
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
            if (this.filter === 'all') return true;
            if (this.filter === 'pending') {
                return !match.is_locked && match.status !== 'finished' && !match.has_prediction;
            }
            if (this.filter.startsWith('group:')) {
                return match.group_name === this.filter.slice(6);
            }
            if (this.filter.startsWith('phase:')) {
                return match.phase === this.filter.slice(6);
            }
            return true;
        },

        async save(match) {
            if (match.is_locked || match.status === 'finished') return;
            if (match.home_score === null || match.home_score === '' ||
                match.away_score === null || match.away_score === '') {
                return;
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
                    body: JSON.stringify({home_score: match.home_score, away_score: match.away_score}),
                });
                if (!res.ok) {
                    const data = await res.json().catch(() => ({}));
                    match.saveError = data.error || 'Error al guardar';
                    return;
                }
                match.has_prediction = true;
                match.savedFlash = true;
                setTimeout(() => match.savedFlash = false, 1800);
            } catch (e) { match.saveError = 'Error de red'; }
        },

        async openPredictionsModal(matchId) {
            this.modal.open = true;
            this.modal.loading = true;
            this.modal.error = '';
            this.modal.match = null;
            this.modal.rows = [];
            try {
                const res = await fetch(`${this.urls.byMatch}/${matchId}/pronosticos`, {
                    headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
                });
                const data = await res.json();
                if (!res.ok) {
                    this.modal.error = data.error || 'No se pueden ver los pronósticos.';
                    return;
                }
                this.modal.match = data.match;
                this.modal.rows = data.predictions;
            } catch (e) {
                this.modal.error = 'Error de red al cargar pronósticos.';
            } finally {
                this.modal.loading = false;
            }
        },

        closePredictionsModal() {
            this.modal.open = false;
        },

        async pollStates() {
            try {
                const res = await fetch(this.urls.states, {
                    headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
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
                    if (s.points_earned !== undefined) m.points_earned = s.points_earned;
                }));
                this.lastSync = new Date().toLocaleTimeString('es-CO', {hour: '2-digit', minute: '2-digit', second: '2-digit'});
            } catch (e) {}
        },
    };
}
</script>
@endsection
