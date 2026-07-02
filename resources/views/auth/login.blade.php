@extends('layouts.app')

@section('title', 'Iniciar sesión')

@section('content')
<div class="max-w-md mx-auto px-4 py-12 sm:py-16">
    <div class="text-center mb-8">
        <p class="eyebrow justify-center">Login</p>
        <h1 class="font-display font-bold text-display-m sm:text-display-l text-pitch uppercase mt-3">Iniciar sesión</h1>
        <p class="text-body-s text-ink-soft mt-2">Ingresa tus credenciales para continuar.</p>
    </div>

    <div class="bg-white border border-line rounded-md shadow-card-2 p-6 sm:p-8">
        <form method="POST" action="{{ route('login.store') }}" class="space-y-5">
            @csrf

            <div class="flex flex-col gap-1.5">
                <label for="email" class="font-mono text-[11px] tracking-wide-label uppercase text-ink-soft">Correo electrónico</label>
                <input id="email" name="email" type="email" required autofocus value="{{ old('email') }}"
                       class="h-[46px] px-3.5 bg-white border-[1.5px] {{ $errors->has('email') ? 'border-alerta' : 'border-line' }} rounded-md text-[15px] focus:border-pitch focus:ring-0">
                @error('email')<p class="text-[12px] text-alerta">{{ $message }}</p>@enderror
            </div>

            <div class="flex flex-col gap-1.5">
                <label for="password" class="font-mono text-[11px] tracking-wide-label uppercase text-ink-soft">Contraseña</label>
                <input id="password" name="password" type="password" required
                       class="h-[46px] px-3.5 bg-white border-[1.5px] {{ $errors->has('password') ? 'border-alerta' : 'border-line' }} rounded-md text-[15px] focus:border-pitch focus:ring-0">
                @error('password')<p class="text-[12px] text-alerta">{{ $message }}</p>@enderror
            </div>

            <div class="flex items-center justify-between flex-wrap gap-3">
                <label class="inline-flex items-center gap-2 text-body-s">
                    <input type="checkbox" name="remember" class="w-[18px] h-[18px] rounded-sm accent-pitch border-line">
                    <span class="text-ink">Recordarme</span>
                </label>
                <a href="{{ route('password.request') }}" class="font-mono text-[11px] tracking-wide-label uppercase text-pitch hover:underline">¿Olvidaste tu contraseña?</a>
            </div>

            <x-btn type="submit" variant="primary" size="lg" class="w-full">Iniciar sesión</x-btn>
        </form>

        <p class="text-body-s text-ink-soft text-center mt-6">
            ¿No tienes cuenta?
            <a href="{{ route('register') }}" class="text-pitch font-display font-bold uppercase tracking-wide-cta text-[13px] hover:underline">Crear cuenta</a>
        </p>
    </div>
</div>
@endsection
