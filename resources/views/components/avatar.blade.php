@props([
    'user' => null,
    'name' => null,
    'src' => null,
    'size' => 'md',
])

@php
    $sizes = [
        'xs' => 'w-7 h-7 text-[11px]',
        'sm' => 'w-9 h-9 text-[13px]',
        'md' => 'w-12 h-12 text-base',
        'lg' => 'w-20 h-20 text-2xl',
        'xl' => 'w-28 h-28 text-4xl',
    ];
    $cls = $sizes[$size] ?? $sizes['md'];

    $url = $src ?? $user?->avatar_url;
    $initials = $user?->initials() ?? mb_strtoupper(mb_substr(trim($name ?? '?'), 0, 1));
@endphp

@if ($url)
    <img src="{{ $url }}" alt="{{ $user?->name ?? $name ?? 'Avatar' }}"
         {{ $attributes->merge(['class' => "$cls rounded-full object-cover border border-line shrink-0"]) }}>
@else
    <span {{ $attributes->merge(['class' => "$cls rounded-full shrink-0 flex items-center justify-center font-display font-extrabold text-pitch bg-pitch-mist border border-line"]) }}>
        {{ $initials }}
    </span>
@endif
