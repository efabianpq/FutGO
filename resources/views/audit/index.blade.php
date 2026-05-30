@extends('layouts.app')
@section('title', 'Auditoría — Exportar reporte')

@section('content')
@if ($isAdmin && str_starts_with(request()->route()->getName() ?? '', 'admin.'))
    @include('admin._nav')
@endif

<div class="max-w-3xl mx-auto px-4 py-10">
    <p class="eyebrow">Reporte de transparencia</p>
    <h1 class="font-display font-bold text-display-m sm:text-display-l text-pitch uppercase mt-2 mb-4 leading-[0.96]">
        Auditoría
    </h1>
    <p class="font-body text-body text-ink-soft max-w-prose mb-8">
        Exportá un reporte completo de todos los pronósticos calculados hasta la fecha.
        Incluye únicamente partidos finalizados con puntos calculados — los pronósticos
        de partidos abiertos quedan privados hasta el cierre.
    </p>

    <div class="bg-white border border-line rounded-md shadow-card-2 p-6 sm:p-8">
        <div class="grid grid-cols-2 gap-4 mb-6 pb-6 border-b border-line-soft">
            <div>
                <p class="font-mono text-[11px] tracking-wide-label uppercase text-ink-mute">Registros disponibles</p>
                <p class="font-display font-extrabold text-display-m text-pitch mt-1">{{ number_format($totalRows, 0, ',', '.') }}</p>
            </div>
            <div>
                <p class="font-mono text-[11px] tracking-wide-label uppercase text-ink-mute">Fecha de generación</p>
                <p class="font-display font-bold text-body text-ink mt-1">{{ now()->locale('es')->isoFormat('D MMM YYYY HH:mm') }}</p>
            </div>
        </div>

        @if ($totalRows === 0)
            <div class="text-center py-10">
                <p class="text-5xl mb-3">⏳</p>
                <p class="font-display font-bold text-display-s text-ink uppercase">Aún no hay datos para exportar</p>
                <p class="font-mono text-[11px] tracking-wide-label uppercase text-ink-mute mt-2">El reporte aparecerá apenas se calculen los primeros resultados oficiales.</p>
            </div>
        @else
            <p class="font-mono text-[11px] tracking-wide-label uppercase text-ink-soft mb-3">Elegí formato</p>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                {{-- CSV --}}
                <a href="{{ $isAdmin && str_starts_with(request()->route()->getName() ?? '', 'admin.') ? route('admin.audit.csv') : route('audit.csv') }}"
                   class="group flex items-start gap-4 p-5 rounded-md border-2 border-line hover:border-pitch bg-white hover:bg-bone-soft transition-all duration-fast">
                    <div class="shrink-0 w-12 h-12 rounded-md bg-pitch text-bone flex items-center justify-center">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3"/>
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <p class="font-display font-bold text-display-s text-pitch uppercase">Descargar CSV</p>
                        <p class="font-body text-body-s text-ink-soft mt-1">Compatible con Excel, Google Sheets y Numbers. Se abre desde cualquier celular.</p>
                    </div>
                </a>

                {{-- PDF --}}
                <a href="{{ $isAdmin && str_starts_with(request()->route()->getName() ?? '', 'admin.') ? route('admin.audit.pdf') : route('audit.pdf') }}"
                   class="group flex items-start gap-4 p-5 rounded-md border-2 border-line hover:border-pitch bg-white hover:bg-bone-soft transition-all duration-fast">
                    <div class="shrink-0 w-12 h-12 rounded-md bg-gol text-on-green flex items-center justify-center">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <p class="font-display font-bold text-display-s text-pitch uppercase">Descargar PDF</p>
                        <p class="font-body text-body-s text-ink-soft mt-1">Formato carta horizontal con logo y total. Listo para imprimir o compartir.</p>
                    </div>
                </a>
            </div>

            <p class="font-mono text-[11px] tracking-wide-label uppercase text-ink-mute mt-6 text-center">
                Nombre del archivo: <span class="text-pitch">SoyPachonMundial_Auditoria_{{ now()->format('Y-m-d') }}</span>
            </p>
        @endif
    </div>
</div>
@endsection
