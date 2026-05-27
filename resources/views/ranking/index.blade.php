@extends('layouts.app')

@section('title', 'Ranking')

@section('content')
<div class="max-w-5xl mx-auto px-4 py-8"
     x-data="rankingApp({
        initialRows: @js($rows),
        initialPrizes: @js($prizes),
        url: '{{ route('ranking.data') }}',
        userShowBase: '{{ url('/ranking/u') }}',
        currentUserId: {{ (int) auth()->id() }},
     })"
     x-init="init()">

    {{-- Hero --}}
    <div class="mb-6 flex items-end justify-between flex-wrap gap-3">
        <div>
            <p class="eyebrow">Clasificación global</p>
            <h1 class="font-display font-bold text-display-m sm:text-display-l text-pitch uppercase mt-2 leading-[0.96]">
                Ranking
            </h1>
        </div>
        <div class="font-mono text-[11px] tracking-wide-label uppercase text-ink-mute" x-show="lastSync" x-cloak>
            Actualizado <span x-text="lastSync"></span>
        </div>
    </div>

    {{-- Cards de acumulado y premios --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-8">
        <div class="bg-pitch text-bone rounded-md shadow-card p-4 col-span-2 lg:col-span-1">
            <p class="font-mono text-[10.5px] tracking-wide-label uppercase opacity-70">Acumulado total</p>
            <p class="font-display font-extrabold text-display-m leading-none mt-2"
               x-text="prizes.pool === null ? 'Por definir' : formatMoney(prizes.pool)"></p>
        </div>
        <div class="bg-white border border-line rounded-md shadow-card p-4">
            <p class="font-mono text-[10.5px] tracking-wide-label uppercase text-ink-mute">🥇 1er puesto (60%)</p>
            <p class="font-display font-extrabold text-display-s text-gol-deep mt-2"
               x-text="prizes.first === null ? 'Por definir' : formatMoney(prizes.first)"></p>
        </div>
        <div class="bg-white border border-line rounded-md shadow-card p-4">
            <p class="font-mono text-[10.5px] tracking-wide-label uppercase text-ink-mute">🥈 2do puesto (20%)</p>
            <p class="font-display font-extrabold text-display-s text-ink-soft mt-2"
               x-text="prizes.second === null ? 'Por definir' : formatMoney(prizes.second)"></p>
        </div>
        <div class="bg-white border border-line rounded-md shadow-card p-4">
            <p class="font-mono text-[10.5px] tracking-wide-label uppercase text-ink-mute">🥉 3er puesto (10%)</p>
            <p class="font-display font-extrabold text-display-s text-[#b87333] mt-2"
               x-text="prizes.third === null ? 'Por definir' : formatMoney(prizes.third)"></p>
        </div>
    </div>

    {{-- Top 3 destacados --}}
    <div x-show="rows.length >= 3" x-cloak class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-8">
        <template x-for="row in rows.slice(0, 3)" :key="row.user_id">
            <a :href="userShowBase + '/' + row.user_id"
               class="block bg-white border border-line rounded-md shadow-card p-5 text-center hover:shadow-card-2 transition-all duration-fast"
               :class="row.current_position === 1 ? 'ring-2 ring-gol' : ''">
                <div class="font-display font-extrabold text-[40px] leading-none"
                     :class="row.current_position === 1 ? 'text-gol-deep' : (row.current_position === 2 ? 'text-[#8a8a8a]' : 'text-[#b87333]')"
                     x-text="row.current_position === 1 ? '🥇' : (row.current_position === 2 ? '🥈' : '🥉')"></div>
                <p class="font-display font-bold text-display-s text-ink uppercase mt-3 truncate" x-text="row.name"></p>
                <p class="font-display font-extrabold text-display-l text-pitch mt-3 leading-none" x-text="row.total_points + ''"></p>
                <p class="font-mono text-[11px] tracking-wide-label uppercase text-ink-mute mt-2">
                    <span x-text="row.exact_predictions"></span> exactos
                </p>
            </a>
        </template>
    </div>

    {{-- Tabla completa --}}
    <x-leaderboard :alpine="true" />

    <p class="font-mono text-[11px] tracking-wide-label uppercase text-ink-mute mt-4">
        Tip — Hacé clic en cualquier nombre para ver la auditoría partido a partido.
    </p>
</div>

<script>
function rankingApp({initialRows, initialPrizes, url, userShowBase, currentUserId}) {
    return {
        rows: initialRows,
        prizes: initialPrizes,
        url, userShowBase,
        me: currentUserId,
        lastSync: '',

        init() {
            this.poll();
            setInterval(() => this.poll(), 60000);
        },

        async poll() {
            try {
                const res = await fetch(this.url, {headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'}});
                if (!res.ok) return;
                const data = await res.json();
                this.rows = data.rows;
                this.prizes = data.prizes;
                this.lastSync = new Date().toLocaleTimeString('es-CO', {hour: '2-digit', minute: '2-digit', second: '2-digit'});
            } catch (e) {}
        },

        formatMoney(n) {
            try {
                return new Intl.NumberFormat('es-CO', {style: 'currency', currency: 'COP', maximumFractionDigits: 0}).format(n);
            } catch (e) { return '$ ' + n; }
        },
    };
}
</script>
@endsection
