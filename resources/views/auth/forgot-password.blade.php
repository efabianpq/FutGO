@extends('layouts.app')

@section('title', 'Recuperar contraseña')

@section('content')
<div class="min-h-[calc(100vh-8rem)] flex items-center justify-center px-4 py-10">
    <div class="w-full max-w-md">
        <div class="text-center mb-6">
            <div class="text-4xl mb-2">🔑</div>
            <h1 class="text-2xl font-bold text-pachon-green">Recuperar contraseña</h1>
            <p class="text-sm text-gray-500 mt-2">
                Ingresá tu correo y te enviaremos un enlace para reiniciar la contraseña.
            </p>
        </div>

        <div class="bg-white shadow-md rounded-lg p-6">
            <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
                @csrf

                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700">Correo electrónico</label>
                    <input id="email" name="email" type="email" required autofocus value="{{ old('email') }}"
                           class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:ring-pachon-green focus:border-pachon-green">
                    @error('email')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>

                <button type="submit" class="w-full bg-pachon-green hover:bg-pachon-green-dark text-white font-semibold py-2 px-4 rounded-md transition">
                    Enviar enlace
                </button>
            </form>

            <p class="mt-4 text-center text-sm text-gray-600">
                <a href="{{ route('login') }}" class="text-pachon-green hover:underline font-medium">← Volver al login</a>
            </p>
        </div>
    </div>
</div>
@endsection
