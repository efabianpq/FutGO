@extends('layouts.landing')

@section('title', $document->title . ' · FutGO')

@section('content')
<style>
    .legal-prose h2 { font-size: 18px; font-weight: 700; margin-top: 1.75rem; margin-bottom: .5rem; }
    .legal-prose ul { list-style: disc; padding-left: 1.25rem; }
    .legal-prose ul li { margin-bottom: .25rem; }
    .legal-prose a { color: var(--color-primary, #00c853); font-weight: 600; }
    .legal-prose strong { font-weight: 700; }
</style>
<div class="max-w-[760px] mx-auto px-6 py-14">
    <a href="{{ route('home') }}" class="text-[13px] font-semibold text-muted hover:text-text">&larr; Volver al inicio</a>

    <h1 class="text-[28px] md:text-[34px] font-black text-text mt-4 mb-2">{{ $document->title }}</h1>
    <p class="text-[13px] text-muted mb-8">
        Versión {{ $document->version }}
        @if($document->published_at)
            · Vigente desde {{ $document->published_at->translatedFormat('d \d\e F \d\e Y') }}
        @endif
    </p>

    <div class="legal-prose text-[15px] leading-relaxed text-text space-y-4">
        {!! \Illuminate\Support\Str::markdown($document->content) !!}
    </div>

    {{-- Navegación entre los documentos legales --}}
    <nav class="mt-14 pt-6 border-t border-border flex flex-wrap gap-4">
        @foreach($allTypes as $type => $label)
            <a href="{{ route('legal.show', $type) }}"
               class="text-[13px] font-semibold {{ $type === $document->type ? 'text-primary' : 'text-muted hover:text-text' }} transition-all">
                {{ $label }}
            </a>
        @endforeach
    </nav>
</div>
@endsection
