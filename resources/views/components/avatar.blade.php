@props([
    'user' => null,
    'name' => null,
    'src' => null,
    'size' => 'md',
])

@php
    $sizes = [
        'xs' => 'w-7 h-7 text-[10px]',
        'sm' => 'w-9 h-9 text-[12px]',
        'md' => 'w-12 h-12 text-sm',
        'lg' => 'w-20 h-20 text-xl',
        'xl' => 'w-28 h-28 text-3xl',
    ];
    $cls = $sizes[$size] ?? $sizes['md'];

    $url = $src ?? $user?->avatar_url;
    $rawInitials = $user?->initials() ?? mb_strtoupper(mb_substr(trim($name ?? '?'), 0, 1));
    // xs y sm muestran solo la primera inicial para no desbordar el círculo.
    $initials = in_array($size, ['xs', 'sm']) ? mb_substr($rawInitials, 0, 1) : $rawInitials;
@endphp

@if ($url)
    <img src="{{ $url }}" alt="{{ $user?->name ?? $name ?? 'Avatar' }}"
         {{ $attributes->merge(['class' => "$cls rounded-full object-cover border border-line shrink-0"]) }}>
@else
    <span {{ $attributes->merge(['class' => "$cls rounded-full shrink-0 inline-flex items-center justify-center font-display font-bold text-pitch bg-pitch-mist border border-line overflow-hidden leading-none select-none"]) }}>{{ $initials }}</span>
@endif
