@props([
    'label',           // texto del grupo
    'active' => false, // si el grupo contiene la ruta activa
    'state',           // nombre de la variable Alpine del padre (ej. 'jugarOpen')
    'items' => [],     // [['route','label','starts','desc']]
    'isActive',        // closure fn($starts):bool heredada del nav
])

{{-- Dropdown de grupo del nav principal (v3). El estado vive en el x-data del
     <nav> padre; este componente solo lo referencia por nombre ($state). --}}
<div class="relative" @click.outside="{{ $state }} = false">
    <button type="button" @click="{{ $state }} = !{{ $state }}"
            class="flex items-center gap-1.5 px-3 py-2 rounded-xs text-[14px] font-semibold transition-all duration-fast {{ $active ? 'bg-surface-2 text-text' : 'text-muted hover:text-text hover:bg-surface-2' }}">
        {{ $icon ?? '' }}
        {{ $label }}
        <svg class="w-3.5 h-3.5" :class="{{ $state }} ? 'rotate-180' : ''" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 9l6 6 6-6"/></svg>
    </button>

    <div x-show="{{ $state }}" x-cloak x-transition
         class="absolute left-0 mt-1 w-72 bg-surface border border-border rounded-md shadow-card-2 py-1 z-50">
        @foreach ($items as $item)
            <a href="{{ route($item['route']) }}"
               class="block px-4 py-2.5 {{ $isActive($item['starts']) ? 'bg-surface-2' : 'hover:bg-surface-2' }}">
                <span class="block font-display font-semibold text-[14px] {{ $isActive($item['starts']) ? 'text-text' : 'text-text' }}">{{ $item['label'] }}</span>
                @if (! empty($item['desc']))
                    <span class="block font-mono text-[11px] text-muted mt-0.5">{{ $item['desc'] }}</span>
                @endif
            </a>
        @endforeach
    </div>
</div>
