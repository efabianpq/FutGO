@extends('layouts.app')
@section('title', 'Editar — ' . $venue->name)

@section('content')
<div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

    <div class="mb-6">
        <p class="eyebrow">Cancha</p>
        <div class="flex items-center gap-2 mt-1">
            <h1 class="font-display font-bold text-display-s text-pitch uppercase">Editar: {{ $venue->name }}</h1>
            <x-help-hint topic="social.canchas.edit" />
        </div>
    </div>

    @include('social.canchas._form', [
        'venue'  => $venue,
        'action' => route('social.canchas.update', $venue->slug),
        'method' => 'PATCH',
    ])

</div>
@endsection
