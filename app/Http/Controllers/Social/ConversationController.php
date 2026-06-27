<?php

namespace App\Http\Controllers\Social;

use App\Http\Controllers\Controller;
use App\Models\Social\Conversation;
use App\Models\Social\ContentReport;
use App\Models\Social\Message;
use App\Rules\CleanText;
use App\Services\Social\ConversationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * FutGO Social — Fase 2 · Sesión S2-B · mensajería libre.
 *
 * Lista de conversaciones, hilo de chat (recarga simple, sin tiempo real), envío
 * de mensajes libres, compartir contacto de forma explícita y reporte de un
 * mensaje. El acceso se valida SIEMPRE contra la participación en el controlador
 * (`hasParticipant`), nunca solo en la vista.
 */
class ConversationController extends Controller
{
    /** Motivos de reporte ofrecidos en la UI (mismos que oportunidades). */
    private const REPORT_REASONS = ['spam', 'lenguaje_ofensivo', 'estafa', 'contenido_inapropiado', 'otro'];

    public function __construct(private ConversationService $service) {}

    // ─────────────────────────────────────────────────────────────────────
    // Lista de conversaciones
    // ─────────────────────────────────────────────────────────────────────

    public function index(Request $request): View
    {
        $user = $request->user();

        $conversations = $this->service->forUser($user)->map(function (Conversation $c) use ($user) {
            $c->setAttribute('has_unread', $this->service->hasUnread($c, $user));

            return $c;
        });

        return view('social.conversaciones.index', [
            'conversations' => $conversations,
            'user'          => $user,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Hilo de conversación
    // ─────────────────────────────────────────────────────────────────────

    public function show(Request $request, Conversation $conversation): View
    {
        $user = $request->user();
        $this->authorizeParticipant($conversation, $user);

        $conversation->load([
            'subject',
            'participants.user:id,name,futgo_id,avatar_url',
            'participants.club:id,name,slug,shield_url',
        ]);

        $messages = $conversation->messages()
            ->visible()
            ->with(['senderUser:id,name,futgo_id,avatar_url', 'senderClub:id,name,slug,shield_url'])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        // Abrir el hilo marca todo como leído.
        $this->service->markRead($conversation, $user);

        return view('social.conversaciones.show', [
            'conversation'  => $conversation,
            'messages'      => $messages,
            'user'          => $user,
            'reportReasons' => self::REPORT_REASONS,
            'maxBody'       => ConversationService::MAX_BODY,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Enviar mensaje libre
    // ─────────────────────────────────────────────────────────────────────

    public function store(Request $request, Conversation $conversation): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeParticipant($conversation, $user);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:' . ConversationService::MAX_BODY, new CleanText],
        ], [
            'body.required' => 'Escribí un mensaje.',
            'body.max'      => 'El mensaje es demasiado largo.',
        ]);

        $this->service->postMessage($conversation, $user, trim($data['body']), $this->clubIdFor($conversation, $user));

        return redirect()
            ->route('social.conversaciones.show', $conversation)
            ->withFragment('fin');
    }

    /**
     * Comparte el contacto del usuario como un mensaje libre más. Es una decisión
     * explícita: el sistema nunca expone el teléfono por su cuenta. Si no tiene
     * teléfono cargado, avisa sin publicar nada.
     */
    public function shareContact(Request $request, Conversation $conversation): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeParticipant($conversation, $user);

        $contact = trim((string) $user->phone_whatsapp);
        if ($contact === '') {
            return back()->with('error', 'No tenés un teléfono/WhatsApp cargado en tu perfil para compartir.');
        }

        $this->service->postMessage(
            $conversation,
            $user,
            "📱 Mi contacto: {$contact}",
            $this->clubIdFor($conversation, $user),
        );

        return redirect()
            ->route('social.conversaciones.show', $conversation)
            ->withFragment('fin');
    }

    // ─────────────────────────────────────────────────────────────────────
    // Reportar un mensaje
    // ─────────────────────────────────────────────────────────────────────

    public function reportMessage(Request $request, Message $message): RedirectResponse
    {
        $user = $request->user();

        $conversation = $message->conversation;
        abort_if($conversation === null, 404);
        $this->authorizeParticipant($conversation, $user);

        // No tiene sentido reportar el propio mensaje.
        abort_if($message->sender_user_id === $user->id, 403, 'No podés reportar tu propio mensaje.');

        $data = $request->validate([
            'reason'  => ['required', Rule::in(self::REPORT_REASONS)],
            'details' => ['nullable', 'string', 'max:500', new CleanText],
        ], [
            'reason.required' => 'Elegí un motivo.',
        ]);

        // Evita reportes duplicados pendientes del mismo usuario sobre el mensaje.
        $exists = ContentReport::where('reporter_user_id', $user->id)
            ->where('reportable_type', 'message')
            ->where('reportable_id', $message->id)
            ->where('status', ContentReport::STATUS_PENDIENTE)
            ->exists();

        if (! $exists) {
            ContentReport::create([
                'reporter_user_id' => $user->id,
                'reportable_type'  => 'message',
                'reportable_id'    => $message->id,
                'reason'           => $data['reason'],
                'details'          => $data['details'] ?? null,
            ]);
        }

        return back()->with('status', 'Gracias. Un administrador revisará el reporte.');
    }

    // ─────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────

    /** Aborta 403 si el usuario no participa en la conversación. */
    private function authorizeParticipant(Conversation $conversation, $user): void
    {
        $conversation->loadMissing('participants');
        abort_unless($conversation->hasParticipant($user), 403, 'No formás parte de esta conversación.');
    }

    /** Club que el usuario representa en esta conversación (para firmar el mensaje). */
    private function clubIdFor(Conversation $conversation, $user): ?int
    {
        $conversation->loadMissing('participants');

        return $conversation->participantFor($user)?->club_id;
    }
}
