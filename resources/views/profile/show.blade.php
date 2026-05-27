@extends('layouts.app')

@section('title', 'Mi Perfil')

@section('content')
<div class="max-w-2xl mx-auto px-4 py-10">
    <h1 class="text-2xl font-bold text-pachon-green mb-4">👤 Mi Perfil</h1>

    <div class="bg-white rounded-lg shadow-md p-6 space-y-5">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 pb-4 border-b">
            <div>
                <p class="text-xs uppercase text-gray-500">Nombre</p>
                <p class="font-semibold">{{ auth()->user()->name }}</p>
            </div>
            <div>
                <p class="text-xs uppercase text-gray-500">Email</p>
                <p class="font-semibold">{{ auth()->user()->email }}</p>
            </div>
            <div>
                <p class="text-xs uppercase text-gray-500">Código usado</p>
                <p class="font-mono">{{ auth()->user()->invitation_code ?? '—' }}</p>
            </div>
            <div>
                <p class="text-xs uppercase text-gray-500">Rol</p>
                <p class="capitalize">{{ auth()->user()->role }}</p>
            </div>
        </div>

        <form method="POST" action="{{ route('profile.update') }}" class="space-y-4">
            @csrf
            @method('PATCH')

            <div>
                <label for="phone_whatsapp" class="block text-sm font-medium text-gray-700">
                    Teléfono WhatsApp <span class="text-xs text-gray-500">(solo números, 7-15 dígitos)</span>
                </label>
                <input id="phone_whatsapp" name="phone_whatsapp" type="tel" required
                       inputmode="numeric" pattern="[0-9]{7,15}"
                       value="{{ old('phone_whatsapp', auth()->user()->phone_whatsapp) }}"
                       class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:ring-pachon-green focus:border-pachon-green">
                @error('phone_whatsapp')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>

            <label class="inline-flex items-center gap-2 text-sm">
                <input type="checkbox" name="notifications_enabled" value="1"
                       @checked(auth()->user()->notifications_enabled)
                       class="rounded border-gray-300 text-pachon-green focus:ring-pachon-green">
                <span class="text-gray-700">Quiero recibir notificaciones por WhatsApp</span>
            </label>

            <div class="flex justify-end pt-2">
                <button type="submit" class="bg-pachon-green hover:bg-pachon-green-dark text-white px-4 py-2 rounded-md text-sm font-semibold">
                    Guardar cambios
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
