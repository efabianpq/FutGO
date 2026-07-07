@extends('layouts.app')
@section('title', 'Descargar mis datos')

@section('content')
<div class="max-w-2xl mx-auto px-4 sm:px-6 py-8">
    <a href="{{ route('privacidad.centro') }}" class="text-[13px] text-muted hover:text-text">&larr; Centro de Privacidad</a>
    <div class="flex items-center gap-2 mt-3 mb-1">
        <h1 class="text-2xl font-bold text-text">Descargar mis datos</h1>
        <x-help-hint topic="privacidad.exportar" />
    </div>
    <p class="text-[14px] text-muted mb-6">Obtén una copia de tu información personal en formato JSON (portabilidad).</p>

    <div class="bg-surface border border-border rounded-md p-5">
        <p class="text-[14px] text-text mb-4">El archivo incluye tu perfil, configuración de privacidad, consentimientos, estadísticas de carrera, logros, oportunidades publicadas y a quién sigues. No contiene datos de otras personas.</p>
        <a href="{{ route('privacidad.exportar.descargar') }}" class="btn btn-primary">Descargar mi archivo (.json)</a>
    </div>

    <p class="text-[13px] text-muted mt-6">
        ¿Necesitas otro formato o tienes una consulta sobre tus datos?
        Escríbenos a <a href="mailto:{{ config('privacy.contact_email') }}" class="text-primary font-semibold">{{ config('privacy.contact_email') }}</a>
        o revisa tus <a href="{{ route('privacidad.habeas') }}" class="text-primary font-semibold">derechos de habeas data</a>.
    </p>
</div>
@endsection
