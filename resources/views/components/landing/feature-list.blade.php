{{-- Contenedor de una lista de <x-landing.feature> --}}
<div {{ $attributes->merge(['class' => 'grid gap-0.5 mt-6']) }}>
    {{ $slot }}
</div>
