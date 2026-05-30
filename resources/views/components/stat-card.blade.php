@props([
    'label' => null,
    'value' => null,
    'sub' => null,
    'accent' => 'pitch',  // pitch, gol, alerta (heredado) → todos resaltan en verde
])

@php
    $valueColor = match ($accent) {
        'alerta' => 'text-danger',
        default  => 'text-text',
    };
@endphp

<div {{ $attributes->class(['stat']) }}>
    @if ($label)
        <p class="lbl">{{ $label }}</p>
    @endif
    <p class="val {{ $valueColor }}">{{ $value ?? $slot }}</p>
    @if ($sub)
        <p class="text-body-s text-muted mt-1.5">{{ $sub }}</p>
    @endif
</div>
