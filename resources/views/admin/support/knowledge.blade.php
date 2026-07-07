@extends('layouts.app')
@section('title', 'Admin · Base de conocimiento')

@section('content')
@include('admin._nav')

<div class="max-w-5xl mx-auto px-4 py-8">
    <div class="flex items-center gap-3 mb-6">
        <a href="{{ route('admin.soporte.dashboard') }}" class="text-sm text-muted hover:text-text underline">← Soporte</a>
        <h1 class="font-display text-2xl font-bold text-text">Base de conocimiento</h1>
    </div>

    @if(session('success'))
        <div class="mb-6 p-3 rounded-lg bg-green/10 border border-green/30 text-green text-sm">{{ session('success') }}</div>
    @endif

    {{-- Nuevo artículo --}}
    <details class="mb-8 p-4 rounded-xl border border-border bg-surface">
        <summary class="font-semibold text-text cursor-pointer inline-flex items-center gap-1.5"><x-icon name="plus" class="w-4 h-4" /> Nuevo artículo</summary>
        <form method="POST" action="{{ route('admin.soporte.knowledge.store') }}" class="mt-4 space-y-3">
            @csrf
            <input type="text" name="title" required maxlength="200" placeholder="Título"
                   class="w-full rounded-lg border border-border bg-bg text-text text-sm px-3 py-2">
            <select name="category" required class="rounded-lg border border-border bg-bg text-text text-sm px-3 py-2">
                @foreach(['torneos' => 'Torneos', 'social' => 'Social', 'cuenta' => 'Cuenta', 'tecnico' => 'Técnico', 'politicas' => 'Políticas'] as $k => $v)
                    <option value="{{ $k }}">{{ $v }}</option>
                @endforeach
            </select>
            <textarea name="content" required rows="6" placeholder="Contenido del artículo..."
                      class="w-full rounded-lg border border-border bg-bg text-text text-sm px-3 py-2"></textarea>
            <textarea name="excerpt" maxlength="300" rows="2" placeholder="Resumen corto para el popup de ayuda (opcional)"
                      class="w-full rounded-lg border border-border bg-bg text-text text-sm px-3 py-2"></textarea>
            <input type="text" name="feature_keys" maxlength="500" placeholder="Claves de pantalla, separadas por coma (ej. torneos.crear)"
                   class="w-full rounded-lg border border-border bg-bg text-text text-sm px-3 py-2">
            <button class="btn btn-primary btn-sm">Crear artículo</button>
        </form>
    </details>

    <div class="space-y-2">
        @forelse($articles as $article)
            <details class="p-4 rounded-xl border border-border bg-surface">
                <summary class="flex items-center gap-3 cursor-pointer list-none">
                    <div class="min-w-0 flex-1">
                        <div class="font-medium text-text truncate">{{ $article->title }}</div>
                        <div class="text-xs text-muted mt-0.5">
                            {{ $article->category }} · {{ $article->source }}
                            · <x-icon name="thumb-up" class="w-3 h-3 inline" /> {{ $article->helpful_count }} / <x-icon name="thumb-down" class="w-3 h-3 inline" /> {{ $article->not_helpful_count }}
                            · {{ $article->is_published ? 'Publicado' : 'Borrador' }}
                        </div>
                        @if($article->topics->isNotEmpty())
                            <div class="flex flex-wrap gap-1 mt-2">
                                @foreach($article->topics as $topic)
                                    <span class="text-[11px] font-mono px-1.5 py-0.5 rounded bg-surface-2 text-muted border border-border">{{ $topic->feature_key }}</span>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </summary>

                <form method="POST" action="{{ route('admin.soporte.knowledge.update', $article) }}" class="mt-4 space-y-3">
                    @csrf @method('PATCH')
                    <input type="text" name="title" required maxlength="200" value="{{ $article->title }}"
                           class="w-full rounded-lg border border-border bg-bg text-text text-sm px-3 py-2">
                    <select name="category" required class="rounded-lg border border-border bg-bg text-text text-sm px-3 py-2">
                        @foreach(['torneos' => 'Torneos', 'social' => 'Social', 'cuenta' => 'Cuenta', 'tecnico' => 'Técnico', 'politicas' => 'Políticas'] as $k => $v)
                            <option value="{{ $k }}" @selected($article->category === $k)>{{ $v }}</option>
                        @endforeach
                    </select>
                    <textarea name="content" required rows="6"
                              class="w-full rounded-lg border border-border bg-bg text-text text-sm px-3 py-2">{{ $article->content }}</textarea>
                    <textarea name="excerpt" maxlength="300" rows="2" placeholder="Resumen corto para el popup de ayuda (opcional)"
                              class="w-full rounded-lg border border-border bg-bg text-text text-sm px-3 py-2">{{ $article->excerpt }}</textarea>
                    <input type="text" name="feature_keys" maxlength="500" placeholder="Claves de pantalla, separadas por coma (ej. torneos.crear)"
                           value="{{ $article->topics->pluck('feature_key')->implode(', ') }}"
                           class="w-full rounded-lg border border-border bg-bg text-text text-sm px-3 py-2">
                    <div class="flex items-center gap-2">
                        <button class="btn btn-primary btn-sm">Guardar cambios</button>
                    </div>
                </form>

                <div class="flex items-center gap-2 mt-3">
                    <form method="POST" action="{{ route('admin.soporte.knowledge.publish', $article) }}" class="shrink-0">
                        @csrf @method('PATCH')
                        <button class="btn btn-secondary btn-sm">{{ $article->is_published ? 'Despublicar' : 'Publicar' }}</button>
                    </form>
                    <form method="POST" action="{{ route('admin.soporte.knowledge.delete', $article) }}" class="shrink-0"
                          onsubmit="return confirm('¿Eliminar este artículo?')">
                        @csrf @method('DELETE')
                        <button class="btn btn-ghost btn-sm text-red-500">Eliminar</button>
                    </form>
                </div>
            </details>
        @empty
            <p class="text-sm text-muted p-4 rounded-xl border border-border">Todavía no hay artículos.</p>
        @endforelse
    </div>

    <div class="mt-6">{{ $articles->links() }}</div>
</div>
@endsection
