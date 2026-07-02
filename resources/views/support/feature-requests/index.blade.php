@extends('layouts.app')
@section('title', 'Solicitar funcionalidad')

@php
    $frStatus = [
        'recibido' => ['Recibido', 'bg-surface-2 text-muted'],
        'evaluando' => ['Evaluando', 'bg-blue-400/15 text-blue-500'],
        'planeado' => ['Planeado', 'bg-purple-400/15 text-purple-500'],
        'en_desarrollo' => ['En desarrollo', 'bg-yellow-400/15 text-yellow-600'],
        'lanzado' => ['Lanzado', 'bg-green/15 text-green'],
        'descartado' => ['Descartado', 'bg-red-400/15 text-red-500'],
    ];
@endphp

@section('content')
<div class="max-w-3xl mx-auto px-4 py-8">
    <div class="flex items-center gap-3 mb-2">
        <h1 class="font-display text-2xl font-bold text-text inline-flex items-center gap-2"><x-icon name="star" class="w-6 h-6" /> Ideas de la comunidad</h1>
        <a href="{{ route('soporte.index') }}" class="ml-auto text-sm text-muted hover:text-text underline">Volver</a>
    </div>
    <p class="text-muted mb-6 text-sm">Vota las funcionalidades que más quieres ver en FutGO.</p>

    <div class="space-y-3">
        @forelse($features as $feature)
            <div class="flex items-start gap-4 p-4 rounded-xl border border-border bg-surface"
                 x-data="{
                    voted: {{ in_array($feature->id, $userVotes) ? 'true' : 'false' }},
                    votes: {{ $feature->votes_count }},
                    async toggle() {
                        const res = await fetch('{{ route('soporte.features.vote', $feature) }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name=\'csrf-token\']').content,
                            },
                        });
                        const data = await res.json();
                        this.voted = data.voted;
                        this.votes = data.votes;
                    }
                 }">
                <button @click="toggle()"
                        :class="voted ? 'border-green bg-green/10 text-green' : 'border-border text-muted hover:text-text'"
                        class="shrink-0 flex flex-col items-center justify-center w-14 h-14 rounded-xl border transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7"/></svg>
                    <span class="text-sm font-bold" x-text="votes"></span>
                </button>
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2">
                        <h3 class="font-semibold text-text">{{ $feature->title }}</h3>
                        @php $st = $frStatus[$feature->status] ?? [$feature->status, 'bg-surface-2 text-muted']; @endphp
                        <span class="text-[11px] px-2 py-0.5 rounded-full {{ $st[1] }}">{{ $st[0] }}</span>
                    </div>
                    <p class="text-sm text-muted mt-1">{{ $feature->description }}</p>
                </div>
            </div>
        @empty
            <div class="p-8 text-center text-muted rounded-xl border border-border">
                Todavía no hay ideas propuestas. Envía la tuya desde el
                <a href="{{ route('soporte.chat') }}?tipo=sugerencia" class="text-green underline">Asistente IA</a>.
            </div>
        @endforelse
    </div>

    <div class="mt-6">{{ $features->links() }}</div>
</div>
@endsection
