@extends('layouts.app')
@section('title', 'Historial de actividad')

@section('content')
@php
    $actionLabels = [
        'login' => 'Iniciaste sesión',
        'logout' => 'Cerraste sesión',
        'password_changed' => 'Cambiaste tu contraseña',
        'email_changed' => 'Cambiaste tu correo',
        'consent_accepted' => 'Aceptaste una política',
        'privacy_settings_updated' => 'Actualizaste tu privacidad',
        'data_exported' => 'Descargaste tus datos',
        'account_deletion_requested' => 'Solicitaste eliminar tu cuenta',
        'profile_updated' => 'Actualizaste tu perfil',
    ];
@endphp
<div class="max-w-3xl mx-auto px-4 sm:px-6 py-8">
    <h1 class="text-2xl font-bold text-text mb-1">Centro de Privacidad</h1>
    <p class="text-[14px] text-muted mb-6">Registro de las acciones importantes de tu cuenta.</p>

    @include('privacy.center._tabs')

    <div class="bg-surface border border-border rounded-md divide-y divide-border">
        @forelse($logs as $log)
            <div class="flex items-center justify-between gap-4 p-4">
                <div>
                    <span class="font-semibold text-text text-[14px]">{{ $actionLabels[$log->action] ?? $log->action }}</span>
                    <p class="text-[12px] text-muted mt-0.5">IP {{ $log->ip ?? '—' }}</p>
                </div>
                <span class="text-[12px] text-muted whitespace-nowrap">{{ $log->created_at?->format('d/m/Y H:i') }}</span>
            </div>
        @empty
            <p class="p-4 text-[13px] text-muted">Todavía no hay actividad registrada.</p>
        @endforelse
    </div>

    <div class="mt-5">{{ $logs->links() }}</div>
</div>
@endsection
