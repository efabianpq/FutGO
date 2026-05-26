@extends('layouts.app')

@section('title', 'Iniciar sesión')

@section('content')
<div class="min-h-[calc(100vh-8rem)] flex items-center justify-center px-4 py-10">
    <div class="w-full max-w-md">
        <div class="text-center mb-6">
            <div class="text-4xl mb-2">⚽</div>
            <h1 class="text-2xl font-bold text-pachon-green">Soy Pachón Mundial</h1>
            <p class="text-sm text-gray-500">Iniciá sesión para continuar</p>
        </div>

        <div class="bg-white shadow-md rounded-lg p-6">
            <form method="POST" action="{{ route('login.store') }}" class="space-y-4">
                @csrf

                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700">Correo electrónico</label>
                    <input id="email" name="email" type="email" required autofocus value="{{ old('email') }}"
                           class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:ring-pachon-green focus:border-pachon-green">
                    @error('email')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700">Contraseña</label>
                    <input id="password" name="password" type="password" required
                           class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:ring-pachon-green focus:border-pachon-green">
                    @error('password')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>

                <div class="flex items-center justify-between text-sm">
                    <label class="inline-flex items-center gap-2">
                        <input type="checkbox" name="remember" class="rounded border-gray-300 text-pachon-green focus:ring-pachon-green">
                        <span class="text-gray-700">Recordarme</span>
                    </label>
                    <a href="{{ route('password.request') }}" class="text-pachon-green hover:underline">¿Olvidaste tu contraseña?</a>
                </div>

                <button type="submit" class="w-full bg-pachon-green hover:bg-pachon-green-dark text-white font-semibold py-2 px-4 rounded-md transition">
                    Iniciar sesión
                </button>
            </form>

            <p class="mt-4 text-center text-sm text-gray-600">
                ¿No tienes cuenta?
                <a href="{{ route('register') }}" class="text-pachon-green hover:underline font-medium">Crear cuenta</a>
            </p>
        </div>
    </div>
</div>
@endsection
