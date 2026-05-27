@extends('layouts.app')

@section('title', 'Nueva contraseña')

@section('content')
<div class="max-w-md mx-auto px-4 py-12 sm:py-16">
    <div class="text-center mb-8">
        <p class="eyebrow justify-center">Reset</p>
        <h1 class="font-display font-bold text-display-m sm:text-display-l text-pitch uppercase mt-3">Nueva contraseña</h1>
    </div>

    <div class="bg-white border border-line rounded-md shadow-card-2 p-6 sm:p-8">
        <form method="POST" action="{{ route('password.store') }}" class="space-y-5">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">

            <div class="flex flex-col gap-1.5">
                <label for="email" class="font-mono text-[11px] tracking-wide-label uppercase text-ink-soft">Correo electrónico</label>
                <input id="email" name="email" type="email" required value="{{ old('email', $email) }}"
                       class="h-[46px] px-3.5 bg-white border-[1.5px] {{ $errors->has('email') ? 'border-alerta' : 'border-line' }} rounded-md text-[15px] focus:border-pitch focus:ring-0">
                @error('email')<p class="text-[12px] text-alerta">{{ $message }}</p>@enderror
            </div>

            <div class="flex flex-col gap-1.5">
                <label for="password" class="font-mono text-[11px] tracking-wide-label uppercase text-ink-soft">Nueva contraseña</label>
                <input id="password" name="password" type="password" required
                       class="h-[46px] px-3.5 bg-white border-[1.5px] {{ $errors->has('password') ? 'border-alerta' : 'border-line' }} rounded-md text-[15px] focus:border-pitch focus:ring-0">
                @error('password')<p class="text-[12px] text-alerta">{{ $message }}</p>@enderror
            </div>

            <div class="flex flex-col gap-1.5">
                <label for="password_confirmation" class="font-mono text-[11px] tracking-wide-label uppercase text-ink-soft">Confirmar</label>
                <input id="password_confirmation" name="password_confirmation" type="password" required
                       class="h-[46px] px-3.5 bg-white border-[1.5px] border-line rounded-md text-[15px] focus:border-pitch focus:ring-0">
            </div>

            <x-btn type="submit" variant="primary" size="lg" class="w-full">Actualizar contraseña</x-btn>
        </form>
    </div>
</div>
@endsection
