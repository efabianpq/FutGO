@props([
    'variant' => 'default',  // default, live, win, upcoming, points
    'pulse' => false,
])

@php
    $classes = match ($variant) {
        'live'     => 'bg-alerta text-white',
        'win'      => 'bg-gol text-pitch',
        'upcoming' => 'bg-line-soft text-ink-soft',
        'points'   => 'bg-gol text-pitch',
        default    => 'bg-pitch-mist text-pitch',
    };
@endphp

<span {{ $attributes->class(['inline-flex items-center gap-1.5 font-mono text-[10.5px] tracking-wide-label uppercase px-2.5 py-1 rounded-pill', $classes]) }}>
    @if ($variant === 'live' || $pulse)
        <span class="inline-block w-1.5 h-1.5 rounded-full bg-current animate-pulse-live"></span>
    @endif
    {{ $slot }}
</span>
