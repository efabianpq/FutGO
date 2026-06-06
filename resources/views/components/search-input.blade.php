@props([
    'model'       => 'q',
    'placeholder' => 'Buscar por nombre…',
])

{{-- Input de búsqueda para filtrar tablas en el navegador (Alpine).
     Requiere un x-data padre con la propiedad indicada en :model. --}}
<div {{ $attributes->merge(['class' => 'relative sm:w-72']) }}>
    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-ink-mute pointer-events-none">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="M21 21l-4.3-4.3"/>
        </svg>
    </span>
    <input type="text" x-model="{{ $model }}" placeholder="{{ $placeholder }}"
           class="w-full h-[40px] pl-9 pr-3 bg-white border-[1.5px] border-line rounded-md text-[14px] focus:border-pitch focus:ring-0">
</div>
