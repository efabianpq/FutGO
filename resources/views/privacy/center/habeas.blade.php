@extends('layouts.app')
@section('title', 'Habeas data')

@section('content')
<div class="max-w-2xl mx-auto px-4 sm:px-6 py-8">
    <a href="{{ route('privacidad.centro') }}" class="text-[13px] text-muted hover:text-text">&larr; Centro de Privacidad</a>
    <h1 class="text-2xl font-bold text-text mt-3 mb-1">Tus derechos sobre tus datos</h1>
    <p class="text-[14px] text-muted mb-6">Bajo la Ley 1581 de 2012 puedes ejercer estos derechos en cualquier momento.</p>

    <div class="space-y-3">
        <div class="bg-surface border border-border rounded-md p-4">
            <h2 class="font-bold text-text text-[15px]">Consultar / obtener una copia</h2>
            <p class="text-[13px] text-muted mt-1 mb-2">Descarga toda tu información personal.</p>
            <a href="{{ route('privacidad.exportar') }}" class="text-[13px] text-primary font-semibold">Descargar mis datos &rarr;</a>
        </div>

        <div class="bg-surface border border-border rounded-md p-4">
            <h2 class="font-bold text-text text-[15px]">Actualizar / corregir</h2>
            <p class="text-[13px] text-muted mt-1 mb-2">Edita tu perfil, ciudad, nivel, teléfono y documento.</p>
            <a href="{{ route('profile.show') }}" class="text-[13px] text-primary font-semibold">Editar mi perfil &rarr;</a>
        </div>

        <div class="bg-surface border border-border rounded-md p-4">
            <h2 class="font-bold text-text text-[15px]">Suprimir</h2>
            <p class="text-[13px] text-muted mt-1 mb-2">Elimina tu cuenta y anonimiza tus datos personales.</p>
            <a href="{{ route('privacidad.eliminar') }}" class="text-[13px] text-alerta font-semibold">Eliminar mi cuenta &rarr;</a>
        </div>

        <div class="bg-surface border border-border rounded-md p-4">
            <h2 class="font-bold text-text text-[15px]">Revocar consentimientos</h2>
            <p class="text-[13px] text-muted mt-1 mb-2">Gestiona qué comunicaciones quieres recibir.</p>
            <a href="{{ route('privacidad.consentimientos') }}" class="text-[13px] text-primary font-semibold">Mis consentimientos &rarr;</a>
        </div>
    </div>

    <p class="text-[13px] text-muted mt-6">
        Para cualquier otra solicitud, escríbenos a
        <a href="mailto:{{ config('privacy.contact_email') }}" class="text-primary font-semibold">{{ config('privacy.contact_email') }}</a>.
    </p>
</div>
@endsection
