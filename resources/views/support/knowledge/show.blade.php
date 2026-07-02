@extends('layouts.app')
@section('title', $article->title)

@section('content')
<div class="max-w-2xl mx-auto px-4 py-8"
     x-data="{
        voted: false,
        async vote(helpful) {
            this.voted = true;
            try {
                await fetch('{{ route('soporte.knowledge.helpful', $article) }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=\'csrf-token\']').content,
                    },
                    body: JSON.stringify({ helpful }),
                });
            } catch (e) {}
        }
     }">
    <a href="{{ route('soporte.knowledge') }}" class="text-sm text-muted hover:text-text underline">← Centro de ayuda</a>

    <h1 class="font-display text-2xl font-bold text-text mt-4 mb-6">{{ $article->title }}</h1>

    <div class="max-w-none text-text leading-relaxed whitespace-pre-line">{{ $article->content }}</div>

    <div class="mt-10 pt-6 border-t border-border">
        <p class="text-sm text-muted mb-3">¿Te resultó útil este artículo?</p>
        <div class="flex gap-2" x-show="!voted">
            <button @click="vote(true)" class="btn btn-secondary btn-sm inline-flex items-center gap-1.5"><x-icon name="thumb-up" class="w-4 h-4" /> Sí</button>
            <button @click="vote(false)" class="btn btn-secondary btn-sm inline-flex items-center gap-1.5"><x-icon name="thumb-down" class="w-4 h-4" /> No</button>
        </div>
        <p x-show="voted" x-cloak class="text-sm text-green">¡Gracias por tu opinión!</p>
    </div>
</div>
@endsection
