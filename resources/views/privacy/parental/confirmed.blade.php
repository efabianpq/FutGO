@extends('layouts.landing')
@section('title', 'Consentimiento confirmado')

@section('content')
<div class="max-w-md mx-auto px-4 py-16 text-center">
    <div class="w-14 h-14 mx-auto rounded-full bg-primary/15 flex items-center justify-center mb-4">
        <svg class="w-7 h-7 text-primary" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
    </div>
    <h1 class="text-2xl font-bold text-text mb-2">¡Autorización confirmada!</h1>
    <p class="text-[15px] text-muted">Gracias. La cuenta de <strong>{{ $minor->name }}</strong> ya quedó activa por completo en FutGO.</p>
    <a href="{{ route('home') }}" class="btn btn-primary mt-6">Ir a FutGO</a>
</div>
@endsection
