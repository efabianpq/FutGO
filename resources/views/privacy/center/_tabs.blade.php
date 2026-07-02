@php
    $tabs = [
        'privacidad.centro'          => 'Resumen',
        'privacidad.configuracion'   => 'Privacidad',
        'privacidad.consentimientos' => 'Consentimientos',
        'privacidad.sesiones'        => 'Sesiones',
        'privacidad.actividad'       => 'Actividad',
    ];
@endphp
<div class="flex flex-wrap gap-1 border-b border-border mb-6 overflow-x-auto">
    @foreach($tabs as $route => $label)
        <a href="{{ route($route) }}"
           class="px-3.5 py-2.5 text-[14px] font-semibold whitespace-nowrap border-b-2 -mb-px transition-all
                  {{ request()->routeIs($route) ? 'border-primary text-primary' : 'border-transparent text-muted hover:text-text' }}">
            {{ $label }}
        </a>
    @endforeach
</div>

@if(session('status'))
    <div class="mb-5 p-3 rounded-sm bg-primary/10 text-primary text-sm">{{ session('status') }}</div>
@endif
