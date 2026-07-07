@extends('layouts.app')
@section('title', 'Registrar cancha')

@section('content')
<div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

    <div class="mb-6">
        <p class="eyebrow">FutGO Social</p>
        <div class="flex items-center gap-2 mt-1">
            <h1 class="font-display font-bold text-display-s text-pitch uppercase">Registrar cancha</h1>
            <x-help-hint topic="social.canchas.create" />
        </div>
        <p class="text-ink-soft text-[14px] mt-1">Agregá una instalación al catálogo compartido de la plataforma.</p>
    </div>

    @include('social.canchas._form', ['venue' => null, 'action' => route('social.canchas.store'), 'method' => 'POST'])

</div>
@endsection
