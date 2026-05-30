@props([
    'size' => 'md',   // sm | md | lg
    'word' => true,   // mostrar el wordmark FutGO
])

@php
    $mark = match ($size) {
        'sm' => 28,
        'lg' => 56,
        default => 36,
    };
    $wordSize = match ($size) {
        'sm' => 20,
        'lg' => 40,
        default => 26,
    };
@endphp

<span {{ $attributes->merge(['class' => 'logo inline-flex items-center gap-3']) }}>
    <svg class="mark" viewBox="0 0 48 48" fill="none" style="width:{{ $mark }}px;height:{{ $mark }}px" aria-hidden="true">
        <path d="M24 45.5C24 45.5 8 30 8 18A16 16 0 1 1 40 18C40 30 24 45.5 24 45.5Z" fill="var(--color-green)"/>
        <circle cx="24" cy="17.5" r="10" fill="#fff"/>
        <polygon points="24,12.3 28.95,15.89 27.06,21.71 20.94,21.71 19.05,15.89" fill="var(--color-green)"/>
    </svg>
    @if ($word)
        <span class="word font-display font-display-x" style="font-size:{{ $wordSize }}px;letter-spacing:-.02em;line-height:1">Fut<b style="color:var(--color-green)">GO</b></span>
    @endif
</span>
