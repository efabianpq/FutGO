@extends('layouts.app')
@section('title', 'Mis consentimientos')

@section('content')
@php
    $labels = [
        'terms'     => 'Términos y Condiciones',
        'privacy'   => 'Política de Privacidad',
        'marketing' => 'Comunicaciones comerciales',
        'parental'  => 'Consentimiento del representante',
    ];
@endphp
<div class="max-w-3xl mx-auto px-4 sm:px-6 py-8">
    <h1 class="text-2xl font-bold text-text mb-1">Centro de Privacidad</h1>
    <p class="text-[14px] text-muted mb-6">Tu historial de aceptaciones y tus preferencias.</p>

    @include('privacy.center._tabs')

    {{-- Preferencia de marketing --}}
    <form method="POST" action="{{ route('privacidad.consentimientos.marketing') }}" class="bg-surface border border-border rounded-md p-4 mb-6">
        @csrf
        @method('PATCH')
        <label class="flex items-center justify-between gap-4 cursor-pointer">
            <span>
                <span class="block font-semibold text-text text-[14px]">Recibir comunicaciones de FutGO</span>
                <span class="block text-[12px] text-muted">Novedades y avisos comerciales. Puedes cambiarlo cuando quieras.</span>
            </span>
            <input type="checkbox" name="marketing" value="1" @checked($marketingActive)
                   onchange="this.form.submit()"
                   class="h-5 w-5 rounded border-border text-primary focus:ring-primary shrink-0">
        </label>
    </form>

    {{-- Historial --}}
    <div class="bg-surface border border-border rounded-md p-5">
        <h2 class="font-bold text-text mb-3">Historial de consentimientos</h2>
        @forelse($history as $type => $events)
            <div class="py-3 border-t border-border/60 first:border-t-0">
                <div class="flex items-center justify-between">
                    <span class="font-semibold text-text text-[14px]">{{ $labels[$type] ?? $type }}</span>
                    @php $last = $events->first(); @endphp
                    <span class="text-[12px] {{ $last->accepted ? 'text-primary' : 'text-muted' }}">
                        {{ $last->accepted ? 'Aceptado' : 'Revocado' }}
                        @if($last->document_version) · v{{ $last->document_version }} @endif
                    </span>
                </div>
                <p class="text-[12px] text-muted mt-1">Última actualización: {{ $last->accepted_at?->format('d/m/Y H:i') }}</p>
            </div>
        @empty
            <p class="text-[13px] text-muted">Sin registros de consentimiento.</p>
        @endforelse
    </div>
</div>
@endsection
