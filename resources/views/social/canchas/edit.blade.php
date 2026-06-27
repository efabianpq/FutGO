@extends('layouts.app')
@section('title', 'Editar — ' . $venue->name)

@section('content')
<div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

    <div class="mb-6">
        <p class="eyebrow">Cancha</p>
        <h1 class="font-display font-bold text-display-s text-pitch uppercase mt-1">Editar: {{ $venue->name }}</h1>
    </div>

    @include('social.canchas._form', [
        'venue'  => $venue,
        'action' => route('social.canchas.update', $venue->slug),
        'method' => 'PATCH',
    ])

</div>
@endsection
