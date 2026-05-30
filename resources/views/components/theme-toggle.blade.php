{{-- Toggle de tema FutGO (dark-first). Usa el store global Alpine 'theme'. --}}
<button
    type="button"
    @click="$store.theme.toggle()"
    class="btn btn-icon btn-ghost"
    :aria-label="$store.theme.isDark ? 'Activar modo claro' : 'Activar modo oscuro'"
    title="Cambiar tema"
>
    {{-- Luna (visible en oscuro → ofrece pasar a claro) --}}
    <svg x-show="$store.theme.isDark" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79Z"/>
    </svg>
    {{-- Sol (visible en claro → ofrece pasar a oscuro) --}}
    <svg x-show="!$store.theme.isDark" x-cloak fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <circle cx="12" cy="12" r="4"/>
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 2v2m0 16v2M4.93 4.93l1.41 1.41m11.32 11.32 1.41 1.41M2 12h2m16 0h2M4.93 19.07l1.41-1.41m11.32-11.32 1.41-1.41"/>
    </svg>
</button>
