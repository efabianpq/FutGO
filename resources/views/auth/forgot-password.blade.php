@extends('layouts.app')

@section('title', 'Recuperar contraseña')

@section('content')
<div class="max-w-md mx-auto px-4 py-12 sm:py-16">
    <div class="text-center mb-8">
        <p class="eyebrow justify-center">Recuperación</p>
        <h1 class="font-display font-bold text-display-m sm:text-display-l text-pitch uppercase mt-3">¿Olvidaste tu clave?</h1>
        <p class="text-body-s text-ink-soft mt-3">Ingresá tu correo y te enviaremos un enlace para reiniciarla.</p>
    </div>

    <div class="bg-white border border-line rounded-md shadow-card-2 p-6 sm:p-8">
        <form method="POST" action="{{ route('password.email') }}" class="space-y-5">
            @csrf
            <div class="flex flex-col gap-1.5">
                <label for="email" class="font-mono text-[11px] tracking-wide-label uppercase text-ink-soft">Correo electrónico</label>
                <input id="email" name="email" type="email" required autofocus value="{{ old('email') }}"
                       class="h-[46px] px-3.5 bg-white border-[1.5px] {{ $errors->has('email') ? 'border-alerta' : 'border-line' }} rounded-md text-[15px] focus:border-pitch focus:ring-0">
                @error('email')<p class="text-[12px] text-alerta">{{ $message }}</p>@enderror
            </div>

            <x-btn type="submit" variant="primary" size="lg" class="w-full">Enviar enlace</x-btn>
        </form>

        <p class="text-body-s text-center mt-6">
            <a href="{{ route('login') }}" class="font-display font-bold uppercase tracking-wide-cta text-[13px] text-pitch hover:underline">← Volver al login</a>
        </p>
    </div>
</div>
@endsection
