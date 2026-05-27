@extends('layouts.app')

@section('title', 'Activar código')

@section('content')
<div class="max-w-md mx-auto px-4 py-12 sm:py-16">
    <div class="text-center mb-8">
        <p class="eyebrow justify-center">Activación</p>
        <h1 class="font-display font-bold text-display-m sm:text-display-l text-pitch uppercase mt-3">Código de invitación</h1>
        <p class="text-body text-ink-soft mt-3">
            Hola <span class="font-display font-bold text-pitch">{{ explode(' ', auth()->user()->name)[0] }}</span>,
            ingresá el código que recibiste para activar tu cuenta.
        </p>
    </div>

    <div class="bg-white border border-line rounded-md shadow-card-2 p-6 sm:p-8">
        <form method="POST" action="{{ route('activate.store') }}" class="space-y-5">
            @csrf
            <div class="flex flex-col gap-1.5">
                <label for="code" class="font-mono text-[11px] tracking-wide-label uppercase text-ink-soft">Código</label>
                <input id="code" name="code" type="text" required autofocus
                       placeholder="SPM-XXXX" value="{{ old('code') }}"
                       class="h-14 px-4 text-center font-mono font-bold text-[20px] tracking-[.2em] uppercase bg-white border-[1.5px] {{ $errors->has('code') ? 'border-alerta' : 'border-line' }} rounded-md focus:border-pitch focus:ring-0">
                @error('code')<p class="text-[12px] text-alerta text-center">{{ $message }}</p>@enderror
            </div>

            <x-btn type="submit" variant="accent" size="lg" class="w-full">Activar cuenta</x-btn>
        </form>

        <form method="POST" action="{{ route('logout') }}" class="text-center mt-5 pt-5 border-t border-line-soft">
            @csrf
            <button type="submit" class="font-mono text-[11px] tracking-wide-label uppercase text-ink-mute hover:text-alerta">Cerrar sesión</button>
        </form>
    </div>
</div>
@endsection
