@extends('layouts.app')

@section('title', 'Actualizamos nuestras políticas')

@section('content')
<div class="max-w-lg mx-auto px-4 py-12">
    <div class="text-center mb-8">
        <p class="eyebrow justify-center">Antes de continuar</p>
        <h1 class="font-display font-bold text-display-m text-pitch uppercase mt-3">Actualizamos nuestras políticas</h1>
        <p class="text-body-s text-ink-soft mt-2">Revisa los cambios y acéptalos para seguir usando FutGO.</p>
    </div>

    <div class="bg-white border border-line rounded-md shadow-card-2 p-6 sm:p-8 space-y-5">
        <div class="space-y-4">
            @foreach($documents as $doc)
                <div class="border border-line rounded-md p-4">
                    <div class="flex items-center justify-between">
                        <span class="font-display font-bold text-[15px] text-pitch">{{ $doc->typeLabel() }}</span>
                        <span class="font-mono text-[11px] text-ink-mute">v{{ $doc->version }}</span>
                    </div>
                    @if($doc->summary_of_changes)
                        <p class="text-[13px] text-ink-soft mt-2">{{ $doc->summary_of_changes }}</p>
                    @endif
                    <a href="{{ route('legal.show', $doc->type) }}" target="_blank" class="text-[13px] text-pitch font-semibold underline mt-2 inline-block">Leer el documento completo</a>
                </div>
            @endforeach
        </div>

        <form method="POST" action="{{ route('privacidad.aceptar.store') }}" class="space-y-4">
            @csrf
            <label class="flex items-start gap-2.5 cursor-pointer">
                <input type="checkbox" name="accept" value="1" class="mt-0.5 h-4 w-4 rounded border-line text-pitch focus:ring-pitch">
                <span class="text-[13px] text-ink-soft">He leído y acepto las políticas actualizadas.</span>
            </label>
            @error('accept')<p class="text-[12px] text-alerta">{{ $message }}</p>@enderror

            <x-btn type="submit" variant="primary" size="lg" class="w-full">Aceptar y continuar</x-btn>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="w-full text-center text-[13px] text-ink-mute hover:text-ink-soft">Cerrar sesión</button>
        </form>
    </div>
</div>
@endsection
