@extends('layouts.app')
@section('title', $opportunity->typeLabel())

@php
    $levelLabels = [
        'recreativo' => 'Recreativo', 'intermedio' => 'Intermedio',
        'competitivo' => 'Competitivo', 'elite_amateur' => 'Élite amateur',
    ];
    $statusBadge = [
        'abierta' => 'bg-gol/20 text-pitch-deep', 'en_negociacion' => 'bg-amber-100 text-amber-800',
        'cerrada' => 'bg-bone-soft text-ink-mute', 'vencida' => 'bg-bone-soft text-ink-mute',
        'cancelada' => 'bg-alerta/15 text-alerta-deep',
    ];
    $respStatusLabel = [
        'pendiente' => 'Pendiente', 'aceptada' => 'Aceptada',
        'rechazada' => 'Rechazada', 'contrapropuesta' => 'Contrapropuesta',
    ];
    $needsClubToRespond = in_array($opportunity->type, ['BUSCAR_RIVAL', 'BUSCAR_EQUIPO'], true);
@endphp

@section('content')
<div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8"
     x-data="{ showReport: false }">

    <a href="{{ route('social.oportunidades.index') }}" class="font-mono text-[12px] text-ink-mute hover:text-pitch">← Volver a oportunidades</a>

    @if (session('status'))
        <div class="mt-4 mb-4 bg-gol/20 border border-gol text-pitch-deep px-4 py-3 rounded-md font-display font-semibold">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="mt-4 mb-4 bg-alerta/15 border border-alerta text-alerta-deep px-4 py-3 rounded-md font-display font-semibold">{{ session('error') }}</div>
    @endif

    {{-- Cabecera --}}
    <div class="bg-white border border-line rounded-md shadow-card-2 p-5 sm:p-6 mt-4">
        <div class="flex items-start justify-between gap-3 mb-4">
            <div class="flex items-center gap-1.5">
                <span class="px-2.5 py-1 rounded-pill font-display font-bold text-[11px] uppercase tracking-wide-label bg-pitch text-bone">{{ $opportunity->typeLabel() }}</span>
                @if ($opportunity->isExpress())
                    <span class="px-2 py-1 rounded-pill font-display font-bold text-[10px] uppercase tracking-wide-label bg-alerta text-white">⚡ Urgente</span>
                @endif
            </div>
            <span class="px-2.5 py-1 rounded-pill font-display font-bold text-[10.5px] uppercase tracking-wide-label {{ $statusBadge[$opportunity->status] ?? '' }}">{{ $opportunity->statusLabel() }}</span>
        </div>

        <div class="flex items-center gap-3 mb-4">
            <x-avatar :name="$opportunity->authorName()" :src="$opportunity->club?->shield_url ?? $opportunity->user?->avatar_url" size="lg" />
            <div class="min-w-0">
                @if ($opportunity->club)
                    <a href="{{ route('torneos.clubes.show', $opportunity->club) }}" class="font-display font-bold text-pitch text-xl hover:underline">{{ $opportunity->club->name }}</a>
                    @if ($opportunity->user)
                        <p class="font-mono text-[11px] text-ink-mute">Cap.: <a href="{{ route('social.player.show', $opportunity->user->futgo_id) }}" class="hover:underline">{{ $opportunity->user->name }}</a></p>
                    @endif
                @elseif ($opportunity->user)
                    <a href="{{ route('social.player.show', $opportunity->user->futgo_id) }}" class="font-display font-bold text-pitch text-xl hover:underline">{{ $opportunity->user->name }}</a>
                @else
                    <p class="font-display font-bold text-pitch text-xl">{{ $opportunity->authorName() }}</p>
                @endif
                <p class="font-mono text-[12px] text-ink-mute">{{ $opportunity->city }}{{ $opportunity->zone ? ' · ' . $opportunity->zone : '' }}</p>
            </div>
        </div>

        <dl class="grid grid-cols-2 gap-3 text-[13px]">
            @if ($opportunity->required_level)
                <div><dt class="font-mono text-[10px] uppercase text-ink-mute">Nivel</dt><dd class="text-pitch font-semibold">{{ $levelLabels[$opportunity->required_level] ?? $opportunity->required_level }}</dd></div>
            @endif
            @if ($opportunity->window_start)
                <div><dt class="font-mono text-[10px] uppercase text-ink-mute">Fecha</dt><dd class="text-pitch font-semibold">{{ $opportunity->window_start->format('d/m/Y H:i') }}</dd></div>
            @endif
            @if ($opportunity->expires_at)
                <div><dt class="font-mono text-[10px] uppercase text-ink-mute">Válida hasta</dt><dd class="text-amber-700 font-semibold">{{ $opportunity->expires_at->format('d/m/Y H:i') }}</dd></div>
            @endif
            @foreach (['partido' => 'Partido', 'posiciones' => 'Posiciones', 'posicion' => 'Posición', 'cupos' => 'Cupos', 'cancha_propuesta' => 'Cancha', 'disponibilidad' => 'Disponibilidad'] as $key => $label)
                @if (! empty($opportunity->payload[$key]) || (isset($opportunity->payload[$key]) && $key === 'cupos'))
                    <div><dt class="font-mono text-[10px] uppercase text-ink-mute">{{ $label }}</dt><dd class="text-pitch font-semibold">{{ $opportunity->payload[$key] }}</dd></div>
                @endif
            @endforeach
        </dl>

        @if (! empty($opportunity->payload['directed_to_name']))
            <p class="text-[13px] text-ink-mute mt-3">
                🎯 Dirigida a: <a href="{{ route('social.player.show', $opportunity->payload['directed_to_user_id'] ?? '#') }}" class="text-pitch font-semibold hover:underline">{{ $opportunity->payload['directed_to_name'] }}</a>
            </p>
        @endif

        @if (! empty($opportunity->payload['descripcion']))
            <p class="text-[14px] text-ink-soft mt-4 whitespace-pre-line">{{ $opportunity->payload['descripcion'] }}</p>
        @endif

        {{-- Acciones del dueño / del visitante --}}
        <div class="flex flex-wrap gap-2 mt-5 pt-4 border-t border-line-soft">
            @if ($canCancel)
                <form method="POST" action="{{ route('social.oportunidades.cancel', $opportunity) }}"
                      onsubmit="return confirm('¿Cancelar esta oportunidad?')" class="flex items-end gap-2">
                    @csrf
                    <input type="text" name="reason" required maxlength="255" placeholder="Motivo de la cancelación"
                           class="h-[38px] px-3 bg-white border-[1.5px] border-line rounded-md text-[13px]">
                    <button type="submit" class="btn btn-danger btn-sm">Cancelar oportunidad</button>
                </form>
            @endif

            @auth
                @if (! $isOwner)
                    <button type="button" @click="showReport = !showReport" class="btn btn-secondary btn-sm">Reportar</button>
                @endif
            @endauth
        </div>

        {{-- Reportar --}}
        @auth
            @if (! $isOwner)
                <form method="POST" action="{{ route('social.oportunidades.report', $opportunity) }}" x-show="showReport" x-cloak class="mt-4 bg-bone-soft border border-line rounded-md p-4 flex flex-col gap-2">
                    @csrf
                    <p class="font-mono text-[10.5px] uppercase tracking-wide-label text-ink-mute">Reportar contenido</p>
                    <select name="reason" class="h-[38px] px-2 bg-white border-[1.5px] border-line rounded-md text-[13px]">
                        @foreach ($reportReasons as $r)
                            <option value="{{ $r }}">{{ ucfirst(str_replace('_', ' ', $r)) }}</option>
                        @endforeach
                    </select>
                    <textarea name="details" rows="2" maxlength="500" placeholder="Detalles (opcional)"
                              class="px-3 py-2 bg-white border-[1.5px] border-line rounded-md text-[13px]"></textarea>
                    <button type="submit" class="btn btn-secondary btn-sm self-end">Enviar reporte</button>
                </form>
            @endif
        @endauth
    </div>

    {{-- S3-A · Clubs compatibles sugeridos (solo el dueño de un BUSCAR_RIVAL abierto) --}}
    @if ($isOwner && $suggestedRivals->isNotEmpty())
        <div class="bg-white border border-line rounded-md shadow-card p-5 mt-6">
            <div class="flex items-center justify-between mb-1">
                <p class="font-display font-bold text-pitch text-[15px]">Rivales compatibles sugeridos</p>
                <span class="font-mono text-[10.5px] uppercase text-ink-mute">{{ $suggestedRivals->count() }} de {{ \App\Services\Social\SuggestionService::MAX_SUGGESTIONS }}</span>
            </div>
            <p class="text-ink-soft text-[12.5px] mb-4">Por reglas: misma ciudad, nivel cercano, activos hace poco y con buena confiabilidad. No esperes las respuestas — invitalos.</p>
            <div class="flex flex-col gap-3">
                @foreach ($suggestedRivals as $s)
                    <div class="flex items-center gap-3 border border-line-soft rounded-md p-3">
                        <x-avatar :name="$s->club->name" :src="$s->club->shield_url" size="md" />
                        <div class="min-w-0 flex-1">
                            <a href="{{ route('torneos.clubes.show', $s->club) }}" class="font-display font-bold text-pitch text-[14px] truncate hover:underline">{{ $s->club->name }}</a>
                            <div class="flex flex-wrap gap-1.5 mt-1">
                                @foreach ($s->reasons as $reason)
                                    <span class="font-mono text-[10px] uppercase tracking-wide-label bg-bone-soft text-ink-mute px-1.5 py-0.5 rounded">{{ $reason }}</span>
                                @endforeach
                            </div>
                        </div>
                        <div class="text-right shrink-0">
                            <p class="font-mono text-[10px] uppercase text-ink-mute">Confiab.</p>
                            <p class="font-display font-bold text-pitch text-[15px]">{{ $s->reliability }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Responder (visitante elegible) --}}
    @if ($canRespond)
        <div class="bg-white border border-line rounded-md shadow-card p-5 mt-6">
            <p class="font-display font-bold text-pitch text-[15px] mb-3">Responder</p>
            <form method="POST" action="{{ route('social.oportunidades.respond', $opportunity) }}" class="flex flex-col gap-3">
                @csrf
                @if ($needsClubToRespond)
                    <div class="flex flex-col gap-1">
                        <label class="font-mono text-[10.5px] uppercase text-ink-mute">Tu equipo</label>
                        <select name="club_id" required class="h-[40px] px-2 bg-white border-[1.5px] border-line rounded-md text-[14px]">
                            <option value="">Elegí tu equipo…</option>
                            @foreach ($myClubs as $club)
                                <option value="{{ $club->id }}">{{ $club->name }}</option>
                            @endforeach
                        </select>
                        @if ($myClubs->isEmpty())
                            <p class="font-mono text-[11px] text-alerta">Necesitás capitanear un equipo para responder.</p>
                        @endif
                    </div>
                @endif
                <textarea name="message" rows="3" maxlength="500" placeholder="Mensaje breve (coordinación dentro de la plataforma)"
                          class="px-3 py-2 bg-white border-[1.5px] border-line rounded-md text-[14px]"></textarea>
                <button type="submit" class="btn btn-primary btn-sm self-end" @if($needsClubToRespond && $myClubs->isEmpty()) disabled @endif>Enviar respuesta</button>
            </form>
        </div>
    @elseif (! auth()->check())
        <div class="bg-white border border-line rounded-md shadow-card p-5 mt-6 text-center">
            <p class="text-ink-soft text-[14px]">Para responder necesitás <a href="{{ route('login') }}" class="text-pitch underline font-semibold">iniciar sesión</a>.</p>
        </div>
    @endif

    {{-- Respuestas recibidas (solo el dueño) --}}
    @if ($isOwner)
        <div class="mt-6">
            <p class="font-mono text-[11px] tracking-wide-label uppercase text-ink-mute mb-3">Respuestas recibidas ({{ $opportunity->responses->count() }})</p>
            @if ($opportunity->responses->isEmpty())
                <div class="bg-white border border-line rounded-md shadow-card p-6 text-center text-ink-soft text-[14px]">Todavía no recibiste respuestas.</div>
            @else
                <div class="flex flex-col gap-3">
                    @foreach ($opportunity->responses as $resp)
                        <div class="bg-white border border-line rounded-md shadow-card p-4">
                            <div class="flex items-center justify-between gap-2 mb-2">
                                <div class="flex items-center gap-2 min-w-0">
                                    <x-avatar :name="$resp->club?->name ?? $resp->user?->name ?? '—'" :src="$resp->club?->shield_url ?? $resp->user?->avatar_url" size="sm" />
                                    <span class="font-display font-bold text-pitch text-[14px] truncate">{{ $resp->club?->name ?? $resp->user?->name ?? '—' }}</span>
                                </div>
                                <span class="font-mono text-[10.5px] uppercase text-ink-mute">{{ $respStatusLabel[$resp->status] ?? $resp->status }}</span>
                            </div>
                            @if ($resp->message)
                                <p class="text-[13px] text-ink-soft mb-3">{{ $resp->message }}</p>
                            @endif
                            @if (in_array($resp->status, ['pendiente', 'contrapropuesta'], true) && in_array($opportunity->status, ['abierta', 'en_negociacion']))
                                <div class="flex flex-wrap gap-2">
                                    <form method="POST" action="{{ route('social.oportunidades.responses.accept', $resp) }}">
                                        @csrf
                                        <button type="submit" class="btn btn-primary btn-sm">Aceptar</button>
                                    </form>
                                    <form method="POST" action="{{ route('social.oportunidades.responses.reject', $resp) }}">
                                        @csrf
                                        <button type="submit" class="btn btn-secondary btn-sm">Rechazar</button>
                                    </form>
                                    <form method="POST" action="{{ route('social.oportunidades.responses.counter', $resp) }}" class="flex items-end gap-2">
                                        @csrf
                                        <input type="text" name="message" maxlength="500" placeholder="Contrapropuesta…"
                                               class="h-[34px] px-2 bg-white border-[1.5px] border-line rounded-md text-[12px]">
                                        <button type="submit" class="btn btn-secondary btn-sm">Contraproponer</button>
                                    </form>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    @endif
</div>
@endsection
