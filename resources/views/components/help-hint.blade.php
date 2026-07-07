@if($article)
    {{-- Botón de ayuda contextual: resuelto en el servidor (sin fetch). Si no hay
         artículo publicado para este $topic, este componente no imprime nada. --}}
    {{-- normal-case + font-body explícitos: este componente puede terminar anidado
         dentro de encabezados con uppercase/font-display, y sin este reset hereda
         esa tipografía en vez de mostrar texto normal. --}}
    <div class="relative inline-block normal-case font-body font-normal align-middle" x-data="{ open: false }" @click.outside="open = false">
        <button type="button" @click="open = !open"
                aria-label="Ayuda sobre esta sección"
                class="inline-flex items-center justify-center w-5 h-5 rounded-full border border-border text-muted text-[11px] font-bold normal-case hover:text-text hover:border-green transition-colors duration-fast">
            ?
        </button>

        {{-- x-transition.opacity (no el shorthand genérico): el shorthand agrega
             también scale, que compite por la propiedad `transform` con el
             translate-x-1/2 de centrado y se ve como un "salto" al abrir. --}}
        <div x-show="open" x-cloak x-transition.opacity.duration.150ms
             class="absolute left-1/2 -translate-x-1/2 z-50 mt-2 w-72 bg-surface border border-border rounded-md shadow-card-2 p-4 text-left normal-case">
            <p class="font-display font-semibold normal-case text-[14px] text-text mb-1">{{ $article->title }}</p>
            <p class="text-[13px] normal-case font-body text-muted leading-relaxed">
                {{ $article->excerpt ?: \Illuminate\Support\Str::limit(strip_tags($article->content), 180) }}
            </p>
            <a href="{{ route('soporte.knowledge.article', $article->slug) }}"
               class="inline-block mt-3 text-[13px] normal-case font-body font-semibold text-green hover:underline">
                Ver guía completa →
            </a>
        </div>
    </div>
@endif
