@extends('layouts.app')

@section('title', 'Activar código de invitación')

@section('content')
<div class="min-h-[calc(100vh-8rem)] flex items-center justify-center px-4 py-10">
    <div class="w-full max-w-md">
        <div class="text-center mb-6">
            <div class="text-4xl mb-2">🎟️</div>
            <h1 class="text-2xl font-bold text-pachon-green">Código de invitación</h1>
            <p class="text-sm text-gray-500 mt-2">
                ¡Hola {{ auth()->user()->name }}! Ingresá el código de invitación que recibiste para activar tu cuenta.
            </p>
        </div>

        <div class="bg-white shadow-md rounded-lg p-6">
            <form method="POST" action="{{ route('activate.store') }}" class="space-y-4">
                @csrf

                <div>
                    <label for="code" class="block text-sm font-medium text-gray-700">Código</label>
                    <input id="code" name="code" type="text" required autofocus
                           placeholder="INV-001"
                           value="{{ old('code') }}"
                           class="mt-1 w-full uppercase tracking-wider text-center text-lg font-mono rounded-md border-gray-300 shadow-sm focus:ring-pachon-green focus:border-pachon-green">
                    @error('code')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>

                <button type="submit" class="w-full bg-pachon-green hover:bg-pachon-green-dark text-white font-semibold py-2 px-4 rounded-md transition">
                    Activar cuenta
                </button>
            </form>

            <form method="POST" action="{{ route('logout') }}" class="mt-4 text-center">
                @csrf
                <button type="submit" class="text-sm text-gray-500 hover:text-red-600 underline">
                    Cerrar sesión
                </button>
            </form>
        </div>
    </div>
</div>
@endsection
