@extends('layouts.app')
@section('title', 'Mensajes')

@php
    use App\Models\Social\Opportunity;
    use App\Models\Social\FriendlyMatch;

    // Título legible del contexto (oportunidad o amistoso) al que se vincula el hilo.
    $contextTitle = function ($c) {
        $s = $c->subject;
        if ($s instanceof Opportunity) {
            return $s->typeLabel() . ' · ' . $s->authorName();
        }
        if ($s instanceof FriendlyMatch) {
            return 'Amistoso · ' . ($s->homeClub?->name ?? 'Local') . ' vs ' . ($s->awayClub?->name ?? 'Visitante');
        }
        return 'Conversación';
    };

    // El "otro" participante (el primero que no sea el usuario actual).
    $counterpart = function ($c) use ($user) {
        $p = $c->participants->firstWhere(fn ($p) => $p->user_id !== $user->id);
        return $p?->club?->name ?? $p?->user?->name ?? 'Participante';
    };
@endphp

@section('content')
<div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

    <div class="mb-6">
        <p class="eyebrow">FutGO Social</p>
        <h1 class="font-display font-bold text-display-s text-pitch uppercase mt-1">Mensajes</h1>
        <p class="text-ink-soft text-[14px] mt-1">Tus conversaciones con rivales, refuerzos y equipos. Se abren automáticamente cuando aceptas una oportunidad o confirmas un amistoso.</p>
    </div>

    @if (session('status'))
        <div class="mb-4 bg-gol/20 border border-gol text-pitch-deep px-4 py-3 rounded-md font-display font-semibold">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-4 bg-alerta/15 border border-alerta text-alerta-deep px-4 py-3 rounded-md font-display font-semibold">{{ session('error') }}</div>
    @endif

    @if ($conversations->isEmpty())
        <div class="bg-white border border-line rounded-md shadow-card p-8 text-center">
            <p class="text-ink-soft">Todavía no tienes conversaciones.</p>
            <p class="text-ink-mute text-[13px] mt-2">Cuando aceptes una oportunidad o confirmes un amistoso, se abre acá un chat para coordinar los detalles.</p>
            <a href="{{ route('social.oportunidades.index') }}" class="btn btn-secondary btn-sm mt-4">Explorar oportunidades</a>
        </div>
    @else
        <div class="flex flex-col gap-2">
            @foreach ($conversations as $c)
                <a href="{{ route('social.conversaciones.show', $c) }}"
                   class="block bg-white border {{ $c->has_unread ? 'border-gol' : 'border-line' }} rounded-md shadow-card px-4 py-3 hover:shadow-card-2 transition-shadow">
                    <div class="flex items-center justify-between gap-3">
                        <div class="min-w-0">
                            <p class="font-display font-bold text-pitch text-[15px] truncate flex items-center gap-2">
                                {{ $counterpart($c) }}
                                @if ($c->has_unread)
                                    <span class="inline-block w-2 h-2 rounded-full bg-gol shrink-0" title="Mensajes sin leer"></span>
                                @endif
                            </p>
                            <p class="font-mono text-[11px] text-ink-mute truncate">{{ $contextTitle($c) }}</p>
                            @if ($c->latestMessage)
                                <p class="text-[13px] text-ink-soft truncate mt-0.5">{{ \Illuminate\Support\Str::limit($c->latestMessage->body, 70) }}</p>
                            @endif
                        </div>
                        <span class="font-mono text-[11px] text-ink-mute whitespace-nowrap shrink-0">
                            {{ $c->last_message_at?->diffForHumans(short: true) }}
                        </span>
                    </div>
                </a>
            @endforeach
        </div>
    @endif
</div>
@endsection
