{{-- Franja de logos "Confían en FutGO" --}}
<section class="py-9 border-t border-b border-border bg-surface">
    <div class="max-w-[1200px] mx-auto px-6 flex flex-wrap items-center justify-center gap-8">
        <span class="font-mono text-[11px] tracking-[.14em] uppercase text-subtle shrink-0">
            Confían en FutGO
        </span>
        <div class="flex flex-wrap gap-9 items-center justify-center">
            @foreach(['Liga Pachón', 'UrbanFutbol', 'CanchaPro', 'Deportiva MX', 'Gol&amp;Gol'] as $logo)
                <span class="font-display-x font-extrabold text-[20px] text-muted opacity-80">
                    {!! $logo !!}
                </span>
            @endforeach
        </div>
    </div>
</section>
