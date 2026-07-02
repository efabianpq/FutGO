@extends('layouts.app')
@section('title', 'Documentos legales · Admin')

@section('content')
@include('admin._nav')
<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-text">Documentos legales</h1>
        <a href="{{ route('admin.legal.create') }}" class="btn btn-primary btn-sm">Publicar versión</a>
    </div>

    @if(session('status'))
        <div class="mb-4 p-3 rounded-sm bg-primary/10 text-primary text-sm">{{ session('status') }}</div>
    @endif

    <div class="space-y-6">
        @foreach($types as $type => $label)
            <div class="bg-surface border border-border rounded-md p-5">
                <div class="flex items-center justify-between mb-3">
                    <h2 class="font-bold text-text">{{ $label }}</h2>
                    <a href="{{ route('admin.legal.create', ['type' => $type]) }}" class="text-[13px] text-primary font-semibold">Nueva versión &rarr;</a>
                </div>

                @forelse(($documents[$type] ?? collect()) as $doc)
                    <div class="flex items-center justify-between py-2 border-t border-border/60 text-sm">
                        <div class="flex items-center gap-3">
                            <span class="font-mono text-[12px] text-muted">v{{ $doc->version }}</span>
                            @if($doc->is_current)
                                <span class="px-2 py-0.5 rounded-full bg-primary/15 text-primary text-[11px] font-semibold">Vigente</span>
                            @else
                                <span class="px-2 py-0.5 rounded-full bg-surface-2 text-muted text-[11px]">Histórica</span>
                            @endif
                        </div>
                        <span class="text-[12px] text-muted">{{ $doc->published_at?->format('d/m/Y') ?? '—' }}</span>
                    </div>
                @empty
                    <p class="text-[13px] text-muted">Sin versiones publicadas.</p>
                @endforelse
            </div>
        @endforeach
    </div>
</div>
@endsection
