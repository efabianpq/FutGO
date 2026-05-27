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
        },
        csrf: document.querySelector('meta[name=csrf-token]').content,
     })"
     x-init="init()">

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
