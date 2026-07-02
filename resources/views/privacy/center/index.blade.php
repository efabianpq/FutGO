@extends('layouts.app')
@section('title', 'Centro de Privacidad')

@section('content')
<div class="max-w-3xl mx-auto px-4 sm:px-6 py-8">
    <h1 class="text-2xl font-bold text-text mb-1">Centro de Privacidad</h1>
    <p class="text-[14px] text-muted mb-6">Controla tus datos, tu privacidad y tus consentimientos.</p>

    @include('privacy.center._tabs')

    {{-- Qué datos tenemos --}}
    <div class="bg-surface border border-border rounded-md p-5 mb-5">
        <h2 class="font-bold text-text mb-3">Qué datos tenemos de ti</h2>
        <dl class="grid grid-cols-2 gap-3 text-[14px]">
            <div><dt class="text-muted text-[12px]">Nombre</dt><dd class="text-text">{{ $user->name }}</dd></div>
            <div><dt class="text-muted text-[12px]">Correo</dt><dd class="text-text">{{ $user->email }}</dd></div>
            <div><dt class="text-muted text-[12px]">Ciudad</dt><dd class="text-text">{{ $user->city ?? '—' }}</dd></div>
            <div><dt class="text-muted text-[12px]">Nivel</dt><dd class="text-text">{{ $user->play_level ?? '—' }}</dd></div>
            <div><dt class="text-muted text-[12px]">Documento</dt><dd class="text-text">{{ $user->document ? 'Registrado (cifrado)' : '—' }}</dd></div>
            <div><dt class="text-muted text-[12px]">Fecha de nacimiento</dt><dd class="text-text">{{ $user->birthdate?->format('d/m/Y') ?? '—' }}</dd></div>
        </dl>
    </div>

    {{-- Políticas aceptadas --}}
    <div class="bg-surface border border-border rounded-md p-5 mb-5">
        <h2 class="font-bold text-text mb-3">Políticas aceptadas</h2>
        <ul class="text-[14px] space-y-1.5">
            <li class="flex justify-between">
                <span class="text-muted">Términos y Condiciones</span>
                <span class="text-text font-mono text-[12px]">v{{ $user->current_terms_version ?? '—' }}</span>
            </li>
            <li class="flex justify-between">
                <span class="text-muted">Política de Privacidad</span>
                <span class="text-text font-mono text-[12px]">v{{ $user->current_privacy_version ?? '—' }}</span>
            </li>
        </ul>
    </div>

    {{-- Accesos directos --}}
    <div class="grid sm:grid-cols-2 gap-3">
        <a href="{{ route('privacidad.configuracion') }}" class="block bg-surface border border-border rounded-md p-4 hover:border-primary transition-all">
            <span class="font-semibold text-text">Configuración de privacidad</span>
            <p class="text-[13px] text-muted mt-1">Elige qué se muestra en tu perfil público.</p>
        </a>
        @if(Route::has('privacidad.exportar'))
        <a href="{{ route('privacidad.exportar') }}" class="block bg-surface border border-border rounded-md p-4 hover:border-primary transition-all">
            <span class="font-semibold text-text">Descargar mis datos</span>
            <p class="text-[13px] text-muted mt-1">Obtén una copia de tu información (portabilidad).</p>
        </a>
        @endif
        <a href="{{ route('privacidad.sesiones') }}" class="block bg-surface border border-border rounded-md p-4 hover:border-primary transition-all">
            <span class="font-semibold text-text">Sesiones y dispositivos</span>
            <p class="text-[13px] text-muted mt-1">Cierra sesión en otros dispositivos.</p>
        </a>
        @if(Route::has('privacidad.eliminar'))
        <a href="{{ route('privacidad.eliminar') }}" class="block bg-surface border border-alerta/40 rounded-md p-4 hover:border-alerta transition-all">
            <span class="font-semibold text-alerta">Eliminar mi cuenta</span>
            <p class="text-[13px] text-muted mt-1">Derecho al olvido, con periodo de gracia.</p>
        </a>
        @endif
    </div>
</div>
@endsection
