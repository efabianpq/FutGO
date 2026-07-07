@extends('layouts.app')
@section('title', 'Admin · Dashboard')

@section('content')
@include('admin._nav')

<div class="max-w-7xl mx-auto px-4 py-8">
    <p class="eyebrow">Panel administrador</p>
    <div class="flex items-center gap-2 mt-2 mb-6">
        <h1 class="font-display font-bold text-display-m sm:text-display-l text-pitch uppercase leading-[0.96]">Dashboard</h1>
        <x-help-hint topic="admin.dashboard" />
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
        <x-stat-card label="Usuarios registrados" :value="$usersCount" />
        <x-stat-card label="Reportes de moderación pendientes" :value="$pendingReports" accent="gol" />
        <x-stat-card label="Reclamos de perfil escalados" :value="$escalatedClaims" accent="gol" />
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
        <a href="{{ route('admin.users.index') }}" class="bg-white border border-line rounded-md shadow-card p-5 hover:border-pitch transition-all duration-fast">
            <p class="font-display font-bold text-pitch">Usuarios</p>
            <p class="text-[13px] text-ink-soft mt-1">Buscar y revisar cuentas de la plataforma.</p>
        </a>
        <a href="{{ route('admin.amistosos.index') }}" class="bg-white border border-line rounded-md shadow-card p-5 hover:border-pitch transition-all duration-fast">
            <p class="font-display font-bold text-pitch">Amistosos</p>
            <p class="text-[13px] text-ink-soft mt-1">Disputas y cancelaciones a resolver.</p>
        </a>
        <a href="{{ route('admin.social.moderacion.index') }}" class="bg-white border border-line rounded-md shadow-card p-5 hover:border-pitch transition-all duration-fast">
            <p class="font-display font-bold text-pitch">Moderación</p>
            <p class="text-[13px] text-ink-soft mt-1">Reportes de contenido de la comunidad.</p>
        </a>
        <a href="{{ route('admin.torneos.reclamos.index') }}" class="bg-white border border-line rounded-md shadow-card p-5 hover:border-pitch transition-all duration-fast">
            <p class="font-display font-bold text-pitch">Reclamos de perfil</p>
            <p class="text-[13px] text-ink-soft mt-1">Reclamos escalados sin capitán activo.</p>
        </a>
        <a href="{{ route('torneos.ranking') }}" class="bg-white border border-line rounded-md shadow-card p-5 hover:border-pitch transition-all duration-fast">
            <p class="font-display font-bold text-pitch">Ranking de la plataforma</p>
            <p class="text-[13px] text-ink-soft mt-1">Recalcular el ranking FUTGO manualmente.</p>
        </a>
    </div>
</div>
@endsection
