@props([
    'label' => null,
    'value' => null,
    'sub' => null,
    'accent' => 'pitch',  // pitch, gol, alerta
])

@php
    $accentBorder = match ($accent) {
        'gol'    => 'border-l-gol',
        'alerta' => 'border-l-alerta',
        default  => 'border-l-pitch',
    };
    $valueColor = match ($accent) {
        'gol'    => 'text-gol-deep',
        'alerta' => 'text-alerta',
        default  => 'text-pitch',
    };
@endphp

<div {{ $attributes->class(['bg-white border border-line rounded-md shadow-card overflow-hidden border-l-4', $accentBorder]) }}>
    <div class="px-4 py-4">
        @if ($label)
            <p class="font-mono text-[10.5px] tracking-wide-label uppercase text-ink-mute mb-1">{{ $label }}</p>
        @endif
        <p class="font-display font-extrabold text-[32px] leading-none {{ $valueColor }}">{{ $value ?? $slot }}</p>
        @if ($sub)
            <p class="text-body-s text-ink-soft mt-1">{{ $sub }}</p>
        @endif
    </div>
</div>
