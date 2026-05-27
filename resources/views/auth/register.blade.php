@extends('layouts.app')

@section('title', 'Crear cuenta')

@section('content')
<div class="max-w-md mx-auto px-4 py-12 sm:py-16">
    <div class="text-center mb-8">
        <p class="eyebrow justify-center">Registro</p>
        <h1 class="font-display font-bold text-display-m sm:text-display-l text-pitch uppercase mt-3">Crear cuenta</h1>
        <p class="text-body-s text-ink-soft mt-2">Necesitás un código de invitación para activar tu acceso.</p>
    </div>

    <div class="bg-white border border-line rounded-md shadow-card-2 p-6 sm:p-8">
        <form method="POST" action="{{ route('register.store') }}" class="space-y-5">
            @csrf

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="flex flex-col gap-1.5">
                    <label for="nombre" class="font-mono text-[11px] tracking-wide-label uppercase text-ink-soft">Nombre</label>
                    <input id="nombre" name="nombre" type="text" required autofocus value="{{ old('nombre') }}"
                           class="h-[46px] px-3.5 bg-white border-[1.5px] {{ $errors->has('nombre') ? 'border-alerta' : 'border-line' }} rounded-md text-[15px] focus:border-pitch focus:ring-0">
                    @error('nombre')<p class="text-[12px] text-alerta">{{ $message }}</p>@enderror
                </div>
                <div class="flex flex-col gap-1.5">
                    <label for="apellido" class="font-mono text-[11px] tracking-wide-label uppercase text-ink-soft">Apellido</label>
                    <input id="apellido" name="apellido" type="text" required value="{{ old('apellido') }}"
                           class="h-[46px] px-3.5 bg-white border-[1.5px] {{ $errors->has('apellido') ? 'border-alerta' : 'border-line' }} rounded-md text-[15px] focus:border-pitch focus:ring-0">
                    @error('apellido')<p class="text-[12px] text-alerta">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="flex flex-col gap-1.5">
                <label for="email" class="font-mono text-[11px] tracking-wide-label uppercase text-ink-soft">Correo electrónico</label>
                <input id="email" name="email" type="email" required value="{{ old('email') }}"
                       class="h-[46px] px-3.5 bg-white border-[1.5px] {{ $errors->has('email') ? 'border-alerta' : 'border-line' }} rounded-md text-[15px] focus:border-pitch focus:ring-0">
                @error('email')<p class="text-[12px] text-alerta">{{ $message }}</p>@enderror
            </div>

            <div class="flex flex-col gap-1.5">
                <label for="telefono" class="font-mono text-[11px] tracking-wide-label uppercase text-ink-soft">Teléfono WhatsApp</label>
                <input id="telefono" name="telefono" type="tel" required inputmode="numeric" pattern="[0-9]{7,15}"
                       value="{{ old('telefono') }}" placeholder="3001234567"
                       class="h-[46px] px-3.5 bg-white border-[1.5px] {{ $errors->has('telefono') ? 'border-alerta' : 'border-line' }} rounded-md text-[15px] focus:border-pitch focus:ring-0">
                <p class="text-[12px] text-ink-mute">Solo números, 7 a 15 dígitos.</p>
                @error('telefono')<p class="text-[12px] text-alerta">{{ $message }}</p>@enderror
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="flex flex-col gap-1.5">
                    <label for="password" class="font-mono text-[11px] tracking-wide-label uppercase text-ink-soft">Contraseña</label>
                    <input id="password" name="password" type="password" required
                           class="h-[46px] px-3.5 bg-white border-[1.5px] {{ $errors->has('password') ? 'border-alerta' : 'border-line' }} rounded-md text-[15px] focus:border-pitch focus:ring-0">
                    @error('password')<p class="text-[12px] text-alerta">{{ $message }}</p>@enderror
                </div>
                <div class="flex flex-col gap-1.5">
                    <label for="password_confirmation" class="font-mono text-[11px] tracking-wide-label uppercase text-ink-soft">Confirmar</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" required
                           class="h-[46px] px-3.5 bg-white border-[1.5px] border-line rounded-md text-[15px] focus:border-pitch focus:ring-0">
                </div>
            </div>

            <x-btn type="submit" variant="primary" size="lg" class="w-full">Crear cuenta</x-btn>
        </form>

        <p class="text-body-s text-ink-soft text-center mt-6">
            ¿Ya tenés cuenta?
            <a href="{{ route('login') }}" class="text-pitch font-display font-bold uppercase tracking-wide-cta text-[13px] hover:underline">Iniciar sesión</a>
        </p>
    </div>
</div>
@endsection
