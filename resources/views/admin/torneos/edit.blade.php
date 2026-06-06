@extends('layouts.app')
@section('title', 'Admin · Editar torneo')

@section('content')
@include('admin.torneos._nav')

<div class="max-w-3xl mx-auto px-4 py-8">
    <p class="eyebrow">Editando</p>
    <h1 class="font-display font-bold text-display-s sm:text-display-m text-pitch uppercase mt-1 mb-6">{{ $tournament->name }}</h1>

    <div class="bg-white border border-line rounded-md shadow-card-2 p-6 sm:p-8">
        <form method="POST" action="{{ route('admin.torneos.update', $tournament) }}" enctype="multipart/form-data">
            @csrf
            @method('PATCH')
            @include('admin.torneos._form')

            <div class="flex justify-end gap-3 pt-6 mt-6 border-t border-line-soft">
                <x-btn :href="route('admin.torneos.show', $tournament)" variant="ghost">Cancelar</x-btn>
                <x-btn type="submit" variant="primary">Guardar cambios</x-btn>
            </div>
        </form>
    </div>
</div>
@endsection
