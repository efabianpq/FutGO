@props([
    'id' => null,
    'variant' => 'default',   // default | tint | dark
    'eyebrow' => null,
    'pill' => null,           // etiqueta pequeña junto al eyebrow
])

@php
    $isDark = $variant === 'dark';
    $wrap = match ($variant) {
        'tint' => 'bg-surface border-y border-border',
        'dark' => 'bg-navy text-white',
        default => '',
    };
    $eyebrowColor = $isDark ? 'color:var(--color-green)' : 'color:var(--color-green)';
@endphp

<section @if($id) id="{{ $id }}" @endif {{ $attributes->merge(['class' => "py-[76px] scroll-mt-20 $wrap"]) }}>
    <div class="max-w-[1120px] mx-auto px-6">
        @if($eyebrow)
            <span class="font-mono text-[12px] uppercase inline-flex items-center gap-2 mb-3.5"
                  style="letter-spacing:.14em; {{ $eyebrowColor }}">
                {{ $eyebrow }}
                @if($pill)
                    <span class="rounded-pill px-2.5 py-0.5 text-[11px] font-medium"
                          style="background:var(--color-green-tint); color:var(--color-green-strong)">{{ $pill }}</span>
                @endif
            </span>
        @endif

        {{ $slot }}
    </div>
</section>
