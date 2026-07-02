@extends('layouts.landing')
@section('title', 'Baja de comunicaciones')

@section('content')
<div class="max-w-md mx-auto px-4 py-16 text-center">
    <h1 class="text-2xl font-bold text-text mb-2">Listo, te diste de baja</h1>
    <p class="text-[15px] text-muted">Ya no vas a recibir comunicaciones comerciales de FutGO. Vas a seguir recibiendo avisos importantes de tu cuenta (como recordatorios de partidos).</p>
    <p class="text-[13px] text-muted mt-4">Puedes volver a activarlas cuando quieras desde tu Centro de Privacidad.</p>
    <a href="{{ route('home') }}" class="btn btn-primary mt-6">Ir a FutGO</a>
</div>
@endsection
