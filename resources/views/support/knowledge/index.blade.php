@extends('layouts.app')
@section('title', 'Centro de ayuda')

@section('content')
<div class="max-w-3xl mx-auto px-4 py-8">
    <div class="flex items-center gap-3 mb-6">
        <h1 class="font-display text-2xl font-bold text-text inline-flex items-center gap-2"><x-icon name="book" class="w-6 h-6" /> Centro de ayuda</h1>
        <a href="{{ route('soporte.index') }}" class="ml-auto text-sm text-muted hover:text-text underline">Volver</a>
    </div>

    @php
        $catLabels = [
            'torneos'   => 'Torneos',
            'social'    => 'FutGO Social',
            'cuenta'    => 'Cuenta',
            'tecnico'   => 'Técnico',
            'politicas' => 'Políticas',
        ];
    @endphp

    @forelse($articles as $category => $group)
        <section class="mb-8">
            <h2 class="font-mono text-[11px] uppercase tracking-wide-label text-muted mb-3">{{ $catLabels[$category] ?? $category }}</h2>
            <div class="space-y-2">
                @foreach($group as $article)
                    <a href="{{ route('soporte.knowledge.article', $article) }}"
                       class="block p-4 rounded-xl border border-border bg-surface hover:bg-surface-2 transition">
                        <div class="font-semibold text-text">{{ $article->title }}</div>
                        @if($article->helpful_count > 0)
                            <div class="text-xs text-muted mt-1 inline-flex items-center gap-1"><x-icon name="thumb-up" class="w-3 h-3" /> {{ $article->helpful_count }} personas lo encontraron útil</div>
                        @endif
                    </a>
                @endforeach
            </div>
        </section>
    @empty
        <div class="p-8 text-center text-muted rounded-xl border border-border">
            Todavía no hay artículos publicados. Escríbele al
            <a href="{{ route('soporte.chat') }}" class="text-green underline">Asistente IA</a>.
        </div>
    @endforelse
</div>
@endsection
