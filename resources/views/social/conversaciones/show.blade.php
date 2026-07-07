@extends('layouts.app')
@section('title', 'Conversación')

@php
    use App\Models\Social\Opportunity;
    use App\Models\Social\FriendlyMatch;

    $subject = $conversation->subject;

    // Contexto: título + enlace a la entidad vinculada.
    $contextTitle = 'Conversación';
    $contextLink  = null;
    if ($subject instanceof Opportunity) {
        $contextTitle = $subject->typeLabel() . ' · ' . $subject->authorName();
        $contextLink  = route('social.oportunidades.show', $subject);
    } elseif ($subject instanceof FriendlyMatch) {
        $contextTitle = 'Amistoso · ' . ($subject->homeClub?->name ?? 'Local') . ' vs ' . ($subject->awayClub?->name ?? 'Visitante');
        $contextLink  = route('social.amistosos.index');
    }

    // Nombre de quien firma cada mensaje (club si actuó como club, o su nombre).
    $senderName = fn ($m) => $m->senderClub?->name ?? $m->senderUser?->name ?? 'Sistema';
@endphp

@section('content')
<div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-8"
     x-data="{ reportFor: null }">

    {{-- Cabecera + contexto --}}
    <div class="mb-4">
        <a href="{{ route('social.conversaciones.index') }}" class="font-mono text-[11px] text-ink-mute hover:text-pitch">&larr; Mensajes</a>
        <div class="flex items-center justify-between gap-3 mt-1">
            <div class="flex items-center gap-2 min-w-0">
                <h1 class="font-display font-bold text-pitch text-[20px] truncate">{{ $contextTitle }}</h1>
                <x-help-hint topic="social.conversaciones.show" />
            </div>
            <div class="flex items-center gap-2 shrink-0">
                @if ($contextLink)
                    <a href="{{ $contextLink }}" class="btn btn-secondary btn-sm">Ver detalle</a>
                @endif
                {{-- H20: Bloquear — solo en DMs directos (sin subject vinculado) --}}
                @if (is_null($conversation->subject_type))
                    <div x-data="{ confirmBlock: false }">
                        <template x-if="!confirmBlock">
                            <button type="button" @click="confirmBlock = true"
                                    class="font-mono text-[10px] uppercase tracking-wide-label text-ink-mute hover:text-alerta">
                                Bloquear
                            </button>
                        </template>
                        <template x-if="confirmBlock">
                            <form method="POST" action="{{ route('social.conversaciones.block', $conversation) }}"
                                  class="flex items-center gap-2">
                                @csrf
                                <span class="text-[12px] text-ink-soft">¿Bloquear este contacto?</span>
                                <button type="submit" class="font-mono text-[11px] uppercase text-alerta font-bold hover:underline">Sí</button>
                                <button type="button" @click="confirmBlock = false"
                                        class="font-mono text-[11px] uppercase text-ink-mute hover:underline">No</button>
                            </form>
                        </template>
                    </div>
                @endif
            </div>
        </div>
    </div>

    @if (session('status'))
        <div class="mb-4 bg-gol/20 border border-gol text-pitch-deep px-4 py-3 rounded-md font-display font-semibold">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-4 bg-alerta/15 border border-alerta text-alerta-deep px-4 py-3 rounded-md font-display font-semibold">{{ session('error') }}</div>
    @endif
    @error('body')
        <div class="mb-4 bg-alerta/15 border border-alerta text-alerta-deep px-4 py-3 rounded-md font-display font-semibold">{{ $message }}</div>
    @enderror

    {{-- Aviso de privacidad --}}
    <div class="mb-4 bg-bone border border-line rounded-md px-4 py-3 text-[12px] text-ink-soft">
        Comparte tu teléfono o WhatsApp solo si quieres. FutGO nunca lo muestra por ti: queda como un mensaje más.
    </div>

    {{-- Hilo de mensajes --}}
    <div class="bg-white border border-line rounded-md shadow-card p-4 flex flex-col gap-3 mb-4">
        @forelse ($messages as $m)
            @php $mine = $m->sender_user_id === $user->id; @endphp

            @if ($m->isSystem())
                {{-- Mensaje estructurado del sistema (resumen del acuerdo) --}}
                <div class="self-center max-w-[90%] text-center">
                    <p class="inline-block bg-bone text-ink-soft text-[12px] rounded-pill px-4 py-1.5">{{ $m->body }}</p>
                </div>
            @else
                <div class="flex flex-col {{ $mine ? 'items-end' : 'items-start' }}">
                    <div class="max-w-[85%] {{ $mine ? 'bg-green text-on-green' : 'bg-bone text-pitch' }} rounded-md px-3 py-2">
                        <p class="font-mono text-[10px] {{ $mine ? 'text-on-green/70' : 'text-ink-mute' }} mb-0.5">{{ $senderName($m) }}</p>
                        <p class="text-[14px] whitespace-pre-line break-words">{{ $m->body }}</p>
                    </div>
                    <div class="flex items-center gap-2 mt-0.5 px-1">
                        <span class="font-mono text-[10px] text-ink-mute">{{ $m->created_at?->format('d/m H:i') }}</span>
                        @unless ($mine)
                            <button type="button" @click="reportFor = (reportFor === {{ $m->id }} ? null : {{ $m->id }})"
                                    class="font-mono text-[10px] text-ink-mute hover:text-alerta">Reportar</button>
                        @endunless
                    </div>

                    {{-- Formulario de reporte inline (solo mensajes ajenos) --}}
                    @unless ($mine)
                        <div x-show="reportFor === {{ $m->id }}" x-cloak class="w-full max-w-[85%] mt-1">
                            <form method="POST" action="{{ route('social.conversaciones.messages.report', $m) }}"
                                  class="bg-white border border-line rounded-md p-3 flex flex-col gap-2">
                                @csrf
                                <select name="reason" required class="select text-[13px]">
                                    @foreach ($reportReasons as $reason)
                                        <option value="{{ $reason }}">{{ ucfirst(str_replace('_', ' ', $reason)) }}</option>
                                    @endforeach
                                </select>
                                <input type="text" name="details" maxlength="500" placeholder="Detalle (opcional)" class="input text-[13px]">
                                <div class="flex justify-end gap-2">
                                    <button type="button" @click="reportFor = null" class="btn btn-ghost btn-sm">Cancelar</button>
                                    <button type="submit" class="btn btn-secondary btn-sm">Enviar reporte</button>
                                </div>
                            </form>
                        </div>
                    @endunless
                </div>
            @endif
        @empty
            <p class="text-ink-mute text-[13px] text-center py-4">Todavía no hay mensajes. Escribe el primero.</p>
        @endforelse
        <span id="fin"></span>
    </div>

    {{-- Compositor de mensaje libre --}}
    <form method="POST" action="{{ route('social.conversaciones.store', $conversation) }}" class="flex flex-col gap-2">
        @csrf
        <textarea name="body" rows="3" maxlength="{{ $maxBody }}" required
                  placeholder="Escribe un mensaje…"
                  class="input w-full resize-y">{{ old('body') }}</textarea>
        <div class="flex items-center justify-end gap-2">
            <button type="submit" form="share-contact-form" class="btn btn-ghost btn-sm">Compartir mi contacto</button>
            <button type="submit" class="btn btn-primary btn-sm">Enviar</button>
        </div>
    </form>

    {{-- Compartir contacto: form separado (botón asociado con el atributo form=) --}}
    <form id="share-contact-form" method="POST" action="{{ route('social.conversaciones.share-contact', $conversation) }}"
          onsubmit="return confirm('¿Compartir tu teléfono/WhatsApp en esta conversación?');">
        @csrf
    </form>
</div>
@endsection
