@extends('layouts.app')

@section('title', 'Ranking — Soy Pachón Mundial')

@push('head')
<style>[x-cloak] { display: none !important; }</style>
@endpush

@section('content')
<div class="max-w-4xl mx-auto px-4 py-8"
     x-data="rankingApp({
        initialRows: @js($rows),
        initialPrizes: @js($prizes),
        url: '{{ route('ranking.data') }}',
        userShowBase: '{{ url('/ranking/u') }}',
     })"
     x-init="init()">

    <div class="flex items-center justify-between flex-wrap gap-2 mb-4">
        <h1 class="text-2xl font-bold text-pachon-green flex items-center gap-2">🏆 Ranking</h1>
        <div class="text-xs text-gray-500" x-show="lastSync" x-cloak>
            Actualizado: <span x-text="lastSync"></span>
        </div>
    </div>

    <!-- Pozo / premios -->
    <div class="bg-white border border-pachon-gold/30 rounded-lg p-4 mb-6 flex flex-wrap items-center gap-6 text-sm">
        <div>
            <p class="text-xs uppercase text-gray-500">Pozo Total</p>
            <p class="font-bold text-lg" x-text="prizes.pool === null ? 'Por definir' : formatMoney(prizes.pool)"></p>
        </div>
        <div>
            <p class="text-xs uppercase text-gray-500">🥇 60%</p>
            <p class="font-bold text-amber-600" x-text="prizes.first === null ? 'Por definir' : formatMoney(prizes.first)"></p>
        </div>
        <div>
            <p class="text-xs uppercase text-gray-500">🥈 20%</p>
            <p class="font-bold text-gray-600" x-text="prizes.second === null ? 'Por definir' : formatMoney(prizes.second)"></p>
        </div>
        <div>
            <p class="text-xs uppercase text-gray-500">🥉 10%</p>
            <p class="font-bold text-amber-800" x-text="prizes.third === null ? 'Por definir' : formatMoney(prizes.third)"></p>
        </div>
    </div>

    <!-- Tabla -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-pachon-green text-white">
                <tr>
                    <th class="px-3 py-3 text-left">Pos.</th>
                    <th class="px-3 py-3 text-left">Usuario</th>
                    <th class="px-3 py-3 text-right">Puntos</th>
                    <th class="px-3 py-3 text-right">🎯 Exactos</th>
                    <th class="px-3 py-3 text-right">Premio Est.</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                <template x-for="(row, idx) in rows" :key="row.user_id">
                    <tr :class="rowClass(row)">
                        <td class="px-3 py-3 font-bold">
                            <span x-text="positionIcon(row.current_position)"></span>
                        </td>
                        <td class="px-3 py-3">
                            <a :href="userShowBase + '/' + row.user_id"
                               class="text-pachon-green hover:underline font-semibold"
                               x-text="row.name"></a>
                            <template x-if="row.previous_position && row.previous_position !== row.current_position">
                                <span class="ml-1 text-xs"
                                      :class="row.previous_position > row.current_position ? 'text-green-600' : 'text-red-600'"
                                      x-text="row.previous_position > row.current_position ? '↑' : '↓'"></span>
                            </template>
                        </td>
                        <td class="px-3 py-3 text-right font-mono font-bold" x-text="row.total_points"></td>
                        <td class="px-3 py-3 text-right text-amber-700" x-text="row.exact_predictions"></td>
                        <td class="px-3 py-3 text-right text-xs" x-text="prizeFor(row.current_position)"></td>
                    </tr>
                </template>
                <tr x-show="rows.length === 0" x-cloak>
                    <td colspan="5" class="px-3 py-6 text-center text-gray-500 italic">
                        Aún no hay participantes activados. El ranking aparecerá aquí cuando los primeros partidos terminen.
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <p class="text-xs text-gray-500 mt-3 italic">
        Tip: hacé clic en cualquier nombre para ver el detalle de pronósticos partido por partido.
    </p>
</div>

<script>
function rankingApp({initialRows, initialPrizes, url, userShowBase}) {
    return {
        rows: initialRows,
        prizes: initialPrizes,
        url,
        userShowBase,
        lastSync: '',

        init() {
            this.poll();
            setInterval(() => this.poll(), 60000);
        },

        async poll() {
            try {
                const res = await fetch(this.url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                if (!res.ok) return;
                const data = await res.json();
                this.rows = data.rows;
                this.prizes = data.prizes;
                this.lastSync = new Date().toLocaleTimeString('es-CO', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
            } catch (e) {
                // silencio
            }
        },

        positionIcon(pos) {
            if (pos === 1) return '🥇 1';
            if (pos === 2) return '🥈 2';
            if (pos === 3) return '🥉 3';
            return pos ?? '—';
        },

        rowClass(row) {
            if (row.current_position === 1) return 'bg-amber-50';
            if (row.current_position === 2) return 'bg-gray-50';
            if (row.current_position === 3) return 'bg-orange-50';
            return '';
        },

        prizeFor(position) {
            if (position === 1) return this.prizes.first === null ? 'Por definir' : this.formatMoney(this.prizes.first);
            if (position === 2) return this.prizes.second === null ? 'Por definir' : this.formatMoney(this.prizes.second);
            if (position === 3) return this.prizes.third === null ? 'Por definir' : this.formatMoney(this.prizes.third);
            return '—';
        },

        formatMoney(n) {
            try {
                return new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 }).format(n);
            } catch (e) {
                return '$ ' + n;
            }
        },
    };
}
</script>
@endsection
