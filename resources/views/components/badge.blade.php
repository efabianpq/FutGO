@props([
    'variant' => 'default',  // default, live, win, upcoming, points, info, warning
    'pulse' => false,
])

@php
    // Mapeo al sistema FutGO de badges.
    $classes = match ($variant) {
        'live'     => 'badge-live',
        'win'      => 'badge-green',
        'points'   => 'badge-solid',
        'info'     => 'badge-info',
        'warning'  => 'badge-warning',
        'upcoming' => '',
        default    => '',
    };
@endphp

<span {{ $attributes->class(['badge', $classes]) }}>
    @if (($variant === 'live' && false) || ($pulse && $variant !== 'live'))
        <span class="inline-block w-1.5 h-1.5 rounded-full bg-current animate-pulse-live"></span>
    @endif
    {{ $slot }}
</span>
