@extends('layouts.app')

@section('title', 'Nueva contraseña')

@section('content')
<div class="min-h-[calc(100vh-8rem)] flex items-center justify-center px-4 py-10">
    <div class="w-full max-w-md">
        <div class="text-center mb-6">
            <div class="text-4xl mb-2">🔒</div>
            <h1 class="text-2xl font-bold text-pachon-green">Nueva contraseña</h1>
        </div>

        <div class="bg-white shadow-md rounded-lg p-6">
            <form method="POST" action="{{ route('password.store') }}" class="space-y-4">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">

                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700">Correo electrónico</label>
                    <input id="email" name="email" type="email" required value="{{ old('email', $email) }}"
                           class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:ring-pachon-green focus:border-pachon-green">
                    @error('email')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700">Nueva contraseña</label>
                    <input id="password" name="password" type="password" required
                           class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:ring-pachon-green focus:border-pachon-green">
                    @error('password')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="password_confirmation" class="block text-sm font-medium text-gray-700">Confirmar nueva contraseña</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" required
                           class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:ring-pachon-green focus:border-pachon-green">
                </div>

                <button type="submit" class="w-full bg-pachon-green hover:bg-pachon-green-dark text-white font-semibold py-2 px-4 rounded-md transition">
                    Actualizar contraseña
                </button>
            </form>
        </div>
    </div>
</div>
@endsection
