@extends('layouts.app')
@section('title', 'Autorización pendiente')

@section('content')
<div class="max-w-md mx-auto px-4 py-12 text-center">
    <h1 class="text-2xl font-bold text-text mb-2">Falta la autorización de tu representante</h1>
    <p class="text-[15px] text-muted mb-1">Le enviamos un correo a <strong>{{ $user->guardian_email }}</strong> para confirmar tu registro.</p>
    <p class="text-[14px] text-muted mb-6">Mientras tanto, algunas acciones están limitadas. En cuanto confirme, se activan solas.</p>

    <form method="POST" action="{{ route('parental.resend') }}">
        @csrf
        <button type="submit" class="btn btn-secondary">Reenviar el correo</button>
    </form>

    @if(session('status'))
        <p class="mt-4 text-[13px] text-primary">{{ session('status') }}</p>
    @endif
</div>
@endsection
