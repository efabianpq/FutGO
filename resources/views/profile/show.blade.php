@extends('layouts.app')

@section('title', 'Mi Perfil')

@section('content')
<div class="max-w-2xl mx-auto px-4 py-10">
    <h1 class="text-2xl font-bold text-pachon-green mb-4">👤 Mi Perfil</h1>
    <div class="bg-white rounded-lg shadow-md p-6 space-y-4">
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
        <div class="text-sm text-gray-500 pt-4 border-t">
            Edición de perfil disponible próximamente.
        </div>
    </div>
</div>
@endsection
