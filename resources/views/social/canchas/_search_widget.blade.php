{{--
  Widget de búsqueda/selección de cancha con autocompletado Alpine.js.
  Props:
    $fieldName   → nombre del input hidden que guarda el venue_id (default: 'venue_id')
    $cityField   → nombre del campo de ciudad del form padre para filtrar (default: 'city')
    $selectedId  → venue_id pre-seleccionado (edición)
    $selectedName → nombre pre-seleccionado
--}}
@props([
    'fieldName'    => 'venue_id',
    'cityField'    => 'city',
    'selectedId'   => null,
    'selectedName' => null,
])

<div x-data="venueSearch({
        initialId:   {{ $selectedId ? (int) $selectedId : 'null' }},
        initialName: {{ $selectedName ? json_encode($selectedName) : 'null' }},
        searchUrl:   {{ json_encode(route('social.canchas.search')) }},
    })"
     class="space-y-1.5">

    <label class="block font-mono text-[10px] tracking-wide-label uppercase text-ink-mute">
        Cancha (opcional)
    </label>

    {{-- Cancha seleccionada --}}
    <template x-if="selected">
        <div class="flex items-center gap-2 p-2 bg-gol/10 border border-gol/30 rounded-md">
            <span class="text-[13px] text-pitch-deep font-semibold flex-1" x-text="selected.name"></span>
            <button type="button" @click="clear()" class="text-ink-mute hover:text-red-500 text-[11px]">✕ Quitar</button>
        </div>
    </template>

    {{-- Buscador --}}
    <template x-if="!selected">
        <div class="relative">
            <input
                type="text"
                x-model="query"
                @input.debounce.300ms="fetchResults()"
                @keydown.escape="open = false"
                placeholder="Buscar cancha por nombre o dirección…"
                class="w-full h-[40px] px-3 bg-white border-[1.5px] border-line rounded-md text-[14px] focus:outline-none focus:border-pitch"
            >

            {{-- Resultados --}}
            <div x-show="open && results.length > 0"
                 x-cloak
                 class="absolute z-50 w-full mt-1 bg-white border border-line rounded-md shadow-card divide-y divide-line max-h-60 overflow-y-auto">
                <template x-for="venue in results" :key="venue.id">
                    <button type="button"
                            @click="pick(venue)"
                            class="w-full text-left px-3 py-2 hover:bg-pitch/5 transition-colors">
                        <span class="font-semibold text-[13px] text-pitch block" x-text="venue.name"></span>
                        <span class="text-[11px] text-ink-mute" x-text="venue.city + (venue.address ? ' · ' + venue.address : '') + (venue.surface ? ' — ' + venue.surface : '')"></span>
                    </button>
                </template>
            </div>

            {{-- Sin resultados --}}
            <div x-show="open && results.length === 0 && query.length >= 2"
                 x-cloak
                 class="absolute z-50 w-full mt-1 bg-white border border-line rounded-md shadow-card px-3 py-3">
                <p class="text-[13px] text-ink-mute">No se encontró la cancha.</p>
                <a href="{{ route('social.canchas.create') }}" target="_blank"
                   class="text-[12px] text-pitch hover:underline mt-1 inline-block">+ Registrarla ahora</a>
            </div>
        </div>
    </template>

    {{-- Hidden que persiste el venue_id seleccionado --}}
    <input type="hidden" name="{{ $fieldName }}" :value="selected ? selected.id : ''">
</div>

@once
@push('scripts')
<script>
function venueSearch({ initialId, initialName, searchUrl }) {
    return {
        query:    '',
        results:  [],
        open:     false,
        selected: initialId ? { id: initialId, name: initialName } : null,

        async fetchResults() {
            if (this.query.length < 2) { this.open = false; return; }
            const params = new URLSearchParams({ q: this.query });
            // Intentar leer ciudad del form padre
            const cityInput = document.querySelector('[name="{{ $cityField }}"]');
            if (cityInput?.value) params.set('ciudad', cityInput.value);

            const res = await fetch(searchUrl + '?' + params);
            this.results = await res.json();
            this.open    = true;
        },

        pick(venue) {
            this.selected = venue;
            this.open     = false;
            this.query    = '';
        },

        clear() {
            this.selected = null;
        },
    };
}
</script>
@endpush
@endonce
