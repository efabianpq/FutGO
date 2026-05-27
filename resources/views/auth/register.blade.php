@extends('layouts.app')

@section('title', 'Crear cuenta')

@section('content')
<div class="min-h-[calc(100vh-8rem)] flex items-center justify-center px-4 py-10">
    <div class="w-full max-w-md">
        <div class="text-center mb-6">
            <div class="text-4xl mb-2">⚽</div>
            <h1 class="text-2xl font-bold text-pachon-green">Soy Pachón Mundial</h1>
            <p class="text-sm text-gray-500">Crea tu cuenta para participar</p>
        </div>

        <div class="bg-white shadow-md rounded-lg p-6">
            <form method="POST" action="{{ route('register.store') }}" class="space-y-4">
                @csrf

                <div>
                    <label for="nombre" class="block text-sm font-medium text-gray-700">Nombre</label>
                    <input id="nombre" name="nombre" type="text" required autofocus value="{{ old('nombre') }}"
                           class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:ring-pachon-green focus:border-pachon-green">
                    @error('nombre')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="apellido" class="block text-sm font-medium text-gray-700">Apellido</label>
                    <input id="apellido" name="apellido" type="text" required value="{{ old('apellido') }}"
                           class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:ring-pachon-green focus:border-pachon-green">
                    @error('apellido')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700">Correo electrónico</label>
                    <input id="email" name="email" type="email" required value="{{ old('email') }}"
                           class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:ring-pachon-green focus:border-pachon-green">
                    @error('email')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="telefono" class="block text-sm font-medium text-gray-700">
                        Teléfono WhatsApp <span class="text-xs text-gray-500">(solo números, 7-15 dígitos)</span>
                    </label>
                    <input id="telefono" name="telefono" type="tel" required
                           inputmode="numeric" pattern="[0-9]{7,15}"
                           value="{{ old('telefono') }}"
                           placeholder="3001234567"
                           class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:ring-pachon-green focus:border-pachon-green">
                    @error('telefono')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700">Contraseña</label>
                    <input id="password" name="password" type="password" required
                           class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:ring-pachon-green focus:border-pachon-green">
                    @error('password')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="password_confirmation" class="block text-sm font-medium text-gray-700">Confirmar contraseña</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" required
                           class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:ring-pachon-green focus:border-pachon-green">
                </div>

                <button type="submit" class="w-full bg-pachon-green hover:bg-pachon-green-dark text-white font-semibold py-2 px-4 rounded-md transition">
                    Crear cuenta
                </button>
            </form>

            <p class="mt-4 text-center text-sm text-gray-600">
                ¿Ya tienes cuenta?
                <a href="{{ route('login') }}" class="text-pachon-green hover:underline font-medium">Iniciar sesión</a>
            </p>
        </div>
    </div>
</div>
@endsection
