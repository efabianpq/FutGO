@props([
    'variant' => 'primary',  // primary, accent, ghost, danger, link
    'size' => 'md',          // sm, md, lg
    'type' => 'button',
    'href' => null,
    'disabled' => false,
    'loading' => false,
])

@php
    $variantClasses = match ($variant) {
        'accent'  => 'bg-gol text-pitch hover:bg-gol-deep',
        'ghost'   => 'bg-transparent text-pitch border border-pitch hover:bg-pitch hover:text-bone',
        'danger'  => 'bg-alerta text-white hover:bg-alerta-deep',
        'link'    => 'bg-transparent text-pitch hover:underline px-0 py-2',
        default   => 'bg-pitch text-bone hover:bg-pitch-deep',
    };

    $sizeClasses = match ($size) {
        'sm' => 'px-3.5 py-2 text-[13px]',
        'lg' => 'px-7 py-4 text-[18px]',
        default => 'px-[22px] py-3 text-[16px]',
    };

    $base = 'inline-flex items-center justify-center gap-2 rounded-md font-display font-bold uppercase tracking-wide-cta border border-transparent transition-all duration-fast disabled:opacity-40 disabled:cursor-not-allowed';
@endphp

@if ($href && ! $disabled)
    <a href="{{ $href }}" {{ $attributes->class([$base, $variantClasses, $sizeClasses]) }}>
        @if ($loading)
            <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v3a5 5 0 00-5 5H4z"></path>
            </svg>
        @endif
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" @disabled($disabled || $loading) {{ $attributes->class([$base, $variantClasses, $sizeClasses]) }}>
        @if ($loading)
            <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v3a5 5 0 00-5 5H4z"></path>
            </svg>
        @endif
        {{ $slot }}
    </button>
@endif
