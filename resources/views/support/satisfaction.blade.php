@extends('layouts.public')
@section('title', 'Gracias por tu respuesta — FutGO')

@section('content')
<div class="max-w-lg mx-auto px-4 py-16 text-center">
    @if($response === 'positiva')
        <div class="text-5xl mb-4">🎉</div>
        <h1 class="font-display text-2xl font-bold text-text mb-2">¡Gracias!</h1>
        <p class="text-muted">Nos alegra haber resuelto tu consulta: <span class="text-text font-medium">«{{ $ticket->subject }}»</span>.</p>
    @else
        <div class="text-5xl mb-4">🙏</div>
        <h1 class="font-display text-2xl font-bold text-text mb-2">Lamentamos que siga sin resolverse</h1>
        <p class="text-muted">Reabrimos tu caso <span class="text-text font-medium">«{{ $ticket->subject }}»</span> y el equipo de FutGO lo va a revisar de nuevo.</p>
    @endif

    <div class="mt-8">
        @auth
            <a href="{{ route('soporte.my-tickets.show', $ticket) }}" class="btn btn-primary btn-sm">Ver mi caso</a>
        @else
            <a href="{{ route('home') }}" class="btn btn-primary btn-sm">Volver al inicio</a>
        @endauth
    </div>
</div>
@endsection
