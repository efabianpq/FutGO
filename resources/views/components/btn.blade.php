@props([
    'variant' => 'primary',  // primary, accent, secondary, ghost, outline, danger, link
    'size' => 'md',          // sm, md, lg
    'type' => 'button',
    'href' => null,
    'disabled' => false,
    'loading' => false,
])

@php
    // Mapeo al sistema FutGO (.btn + variante). Se conservan los nombres de
    // variante heredados para no romper las vistas existentes.
    $variantClass = match ($variant) {
        'accent'    => 'btn-primary',
        'secondary' => 'btn-secondary',
        'ghost'     => 'btn-outline',
        'outline'   => 'btn-outline',
        'danger'    => 'btn-danger',
        'link'      => 'btn-ghost !px-0',
        default     => 'btn-primary',
    };

    $sizeClass = match ($size) {
        'sm' => 'btn-sm',
        'lg' => 'btn-lg',
        default => '',
    };

    $base = "btn $variantClass $sizeClass";
@endphp

@if ($href && ! $disabled)
    <a href="{{ $href }}" {{ $attributes->class($base) }}>
        @if ($loading)
            <span class="spinner !w-4 !h-4 !border-2"></span>
        @endif
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" @disabled($disabled || $loading) {{ $attributes->class($base) }}>
        @if ($loading)
            <span class="spinner !w-4 !h-4 !border-2"></span>
        @endif
        {{ $slot }}
    </button>
@endif
