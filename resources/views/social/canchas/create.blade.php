@extends('layouts.app')
@section('title', 'Registrar cancha')

@section('content')
<div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

    <div class="mb-6">
        <p class="eyebrow">FutGO Social</p>
        <h1 class="font-display font-bold text-display-s text-pitch uppercase mt-1">Registrar cancha</h1>
        <p class="text-ink-soft text-[14px] mt-1">Agregá una instalación al catálogo compartido de la plataforma.</p>
    </div>

    @include('social.canchas._form', ['venue' => null, 'action' => route('social.canchas.store'), 'method' => 'POST'])

</div>
@endsection
