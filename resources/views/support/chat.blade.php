@extends('layouts.app')
@section('title', 'Asistente FutGO')

@section('content')
<div class="max-w-2xl mx-auto px-4 py-6" x-data="supportChat()">

    <div class="flex items-center gap-3 mb-6">
        <div class="w-10 h-10 rounded-full bg-green flex items-center justify-center text-white font-bold text-lg">F</div>
        <div>
            <div class="font-semibold text-text">Asistente FutGO</div>
            <div class="text-xs text-green">En línea</div>
        </div>
        <a href="{{ route('soporte.index') }}" class="ml-auto text-sm text-muted hover:text-text underline">Volver</a>
    </div>

    {{-- Área de mensajes --}}
    <div class="space-y-4 mb-4 min-h-64 max-h-[60vh] overflow-y-auto" x-ref="messagesContainer">

        {{-- Mensaje de bienvenida --}}
        <div class="flex gap-3">
            <div class="w-8 h-8 rounded-full bg-green/20 flex items-center justify-center text-sm shrink-0 text-text">F</div>
            <div class="bg-surface-2 rounded-2xl rounded-tl-sm px-4 py-3 max-w-xs">
                <p class="text-sm text-text">Hola {{ auth()->user()->name }} 👋</p>
                <p class="text-sm mt-1 text-text">Soy el asistente de FutGO. Puedo ayudarte con:</p>
                <div class="flex flex-wrap gap-2 mt-3">
                    <template x-for="option in quickOptions" :key="option.text">
                        <button
                            @click="sendQuickMessage(option.text)"
                            class="text-xs px-3 py-1.5 rounded-full border border-green text-green hover:bg-green hover:text-white transition"
                            x-text="option.label">
                        </button>
                    </template>
                </div>
            </div>
        </div>

        {{-- Mensajes de la conversación --}}
        <template x-for="msg in messages" :key="msg.id">
            <div :class="msg.role === 'user' ? 'flex justify-end' : 'flex gap-3'">
                <template x-if="msg.role !== 'user'">
                    <div class="w-8 h-8 rounded-full bg-green/20 flex items-center justify-center text-sm shrink-0 text-text">F</div>
                </template>
                <div
                    :class="msg.role === 'user'
                        ? 'bg-green text-white rounded-2xl rounded-tr-sm px-4 py-3 max-w-xs'
                        : 'bg-surface-2 text-text rounded-2xl rounded-tl-sm px-4 py-3 max-w-xs'"
                    class="text-sm"
                    x-html="formatMessage(msg.content)">
                </div>
            </div>
        </template>

        {{-- Indicador de carga --}}
        <div x-show="isLoading" class="flex gap-3">
            <div class="w-8 h-8 rounded-full bg-green/20 flex items-center justify-center text-sm shrink-0 text-text">F</div>
            <div class="bg-surface-2 rounded-2xl rounded-tl-sm px-4 py-3">
                <div class="flex gap-1">
                    <span class="w-2 h-2 bg-muted rounded-full animate-bounce" style="animation-delay:0ms"></span>
                    <span class="w-2 h-2 bg-muted rounded-full animate-bounce" style="animation-delay:150ms"></span>
                    <span class="w-2 h-2 bg-muted rounded-full animate-bounce" style="animation-delay:300ms"></span>
                </div>
            </div>
        </div>

        {{-- Ticket creado --}}
        <div x-show="ticketCreated" x-cloak class="rounded-xl border border-green/30 bg-green/5 p-4 text-sm">
            <p class="font-semibold text-green">✅ Ticket creado</p>
            <p class="text-muted mt-1">El equipo de FutGO va a revisar tu caso y te notificará por email cuando tengamos respuesta.</p>
            <a :href="'/soporte/mis-casos/' + ticketId" class="inline-block mt-2 text-green underline text-xs">Ver mi ticket →</a>
        </div>

    </div>

    {{-- Botón escalar (aparece después del 2do intercambio) --}}
    <div x-show="messages.length >= 4 && !ticketCreated" x-cloak class="mb-3 text-center">
        <button @click="escalate()" class="text-xs text-muted hover:text-text underline">
            ¿No encontraste respuesta? Escalar a soporte humano
        </button>
    </div>

    {{-- Input --}}
    <form @submit.prevent="sendMessage()" class="flex gap-2">
        <input
            x-model="newMessage"
            type="text"
            placeholder="Escribe tu consulta..."
            maxlength="1000"
            :disabled="isLoading || ticketCreated"
            class="flex-1 rounded-xl border border-border bg-bg text-text px-4 py-3 text-sm focus:outline-none focus:border-green disabled:opacity-50"
        >
        <button
            type="submit"
            :disabled="isLoading || !newMessage.trim() || ticketCreated"
            class="bg-green text-white px-4 py-3 rounded-xl disabled:opacity-40 hover:opacity-90 transition">
            ➤
        </button>
    </form>

</div>

<script>
window.supportChat = function () {
    return {
        messages: [],
        newMessage: '',
        isLoading: false,
        conversationId: @json($conversation?->id),
        ticketCreated: false,
        ticketId: null,

        quickOptions: [
            { label: '⚽ Crear torneo', text: '¿Cómo creo un torneo?' },
            { label: '👥 Gestionar equipo', text: '¿Cómo gestiono mi equipo?' },
            { label: '📅 Ver fixture', text: '¿Por qué no aparece el fixture?' },
            { label: '📲 Credencial QR', text: '¿Cómo funciona la credencial QR?' },
            { label: '🐞 Reportar error', text: 'Tengo un problema técnico' },
        ],

        init() {
            const params = new URLSearchParams(window.location.search);
            const tipo = params.get('tipo');
            if (tipo === 'bug') this.newMessage = 'Quiero reportar un problema técnico';
            if (tipo === 'sugerencia') this.newMessage = 'Quiero enviar una sugerencia';
            this.$nextTick(() => this.scrollToBottom());
        },

        sendQuickMessage(text) {
            this.newMessage = text;
            this.sendMessage();
        },

        async sendMessage() {
            if (!this.newMessage.trim() || this.isLoading) return;

            const userMessage = this.newMessage.trim();
            this.newMessage = '';
            this.isLoading = true;

            this.messages.push({ role: 'user', content: userMessage, id: Date.now() });
            this.$nextTick(() => this.scrollToBottom());

            try {
                const res = await fetch('{{ route('soporte.chat.send') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({
                        message: userMessage,
                        conversation_id: this.conversationId,
                    }),
                });

                const data = await res.json();
                this.conversationId = data.conversation_id;
                this.messages.push({ role: 'assistant', content: data.response, id: Date.now() + 1 });

                if (data.escalated && data.ticket_id) {
                    this.ticketCreated = true;
                    this.ticketId = data.ticket_id;
                }
            } catch (e) {
                this.messages.push({
                    role: 'assistant',
                    content: 'Hubo un error al conectar con el asistente. Intenta de nuevo.',
                    id: Date.now() + 1,
                });
            } finally {
                this.isLoading = false;
                this.$nextTick(() => this.scrollToBottom());
            }
        },

        async escalate() {
            if (!this.conversationId) return;
            const res = await fetch('{{ route('soporte.chat.escalate') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify({ conversation_id: this.conversationId }),
            });
            const data = await res.json();
            if (data.ticket_id) {
                this.ticketCreated = true;
                this.ticketId = data.ticket_id;
            }
        },

        formatMessage(text) {
            return String(text)
                .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
                .replace(/\n/g, '<br>')
                .replace(/(https?:\/\/[^\s]+)/g, '<a href="$1" class="underline" target="_blank">$1</a>');
        },

        scrollToBottom() {
            const el = this.$refs.messagesContainer;
            if (el) el.scrollTop = el.scrollHeight;
        },
    };
};
</script>
@endsection
