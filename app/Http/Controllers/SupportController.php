<?php

namespace App\Http\Controllers;

use App\Models\Support\SupportArticle;
use App\Models\Support\SupportConversation;
use App\Models\Support\SupportFeatureRequest;
use App\Models\Support\SupportFeatureVote;
use App\Models\Support\SupportServiceStatus;
use App\Models\Support\SupportTicket;
use App\Services\Support\PatternDetectorService;
use App\Services\Support\SupportAIGateway;
use App\Services\Support\SupportContextBuilder;
use App\Services\Support\TicketClassifierService;
use Illuminate\Http\Request;

class SupportController extends Controller
{
    public function __construct(
        private SupportAIGateway $gateway,
        private SupportContextBuilder $contextBuilder,
        private TicketClassifierService $classifier,
        private PatternDetectorService $patternDetector,
    ) {}

    // Hub principal con los 7 módulos
    public function index(Request $request)
    {
        $user = $request->user();

        $openTickets   = SupportTicket::where('user_id', $user->id)->open()->count();
        $serviceIssues = SupportServiceStatus::whereIn('status', ['degradado', 'caido'])->get();

        return view('support.index', compact('openTickets', 'serviceIssues'));
    }

    // Vista del chat
    public function chat(Request $request)
    {
        $conversation = SupportConversation::where('user_id', $request->user()->id)
            ->whereNull('ticket_id')
            ->latest()
            ->first();

        return view('support.chat', compact('conversation'));
    }

    // Procesar mensaje del bot
    public function sendMessage(Request $request)
    {
        $request->validate([
            'message'         => ['required', 'string', 'max:1000'],
            'conversation_id' => ['nullable', 'integer', 'exists:support_conversations,id'],
        ]);

        $user    = $request->user();
        $message = $request->input('message');

        $conversation = $request->conversation_id
            ? SupportConversation::where('id', $request->conversation_id)
                ->where('user_id', $user->id)
                ->firstOrFail()
            : SupportConversation::create([
                'user_id'  => $user->id,
                'messages' => [],
            ]);

        $history = $conversation->messages ?? [];

        try {
            $result = $this->gateway->chat($user, $message, $history);

            $conversation->addMessage('user', $message);
            $conversation->addMessage('assistant', $result['response']);

            $ticket = null;
            if ($result['should_escalate']) {
                $classification = $this->classifier->classify($message, $history);
                $context        = $this->contextBuilder->buildForUser($user);

                $ticket = SupportTicket::create([
                    'user_id'               => $user->id,
                    'category'              => $classification['category'],
                    'status'                => 'abierto',
                    'priority'              => $classification['priority'],
                    'classifier_confidence' => $classification['confidence'],
                    'subject'               => $classification['subject'],
                    'context_snapshot'      => $context,
                    'error_trace'           => $request->only(['url', 'device', 'resolution']),
                    'audit_timeline'        => $history,
                ]);

                $conversation->update([
                    'ticket_id'         => $ticket->id,
                    'escalated'         => true,
                    'escalation_reason' => $result['escalation_reason'],
                ]);

                $this->patternDetector->analyze($ticket);
            }

            return response()->json([
                'response'        => $result['response'],
                'conversation_id' => $conversation->id,
                'escalated'       => $result['should_escalate'],
                'ticket_id'       => $ticket?->id,
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'response'        => $e->getMessage(),
                'conversation_id' => $conversation->id,
                'escalated'       => false,
                'ticket_id'       => null,
            ]);
        }
    }

    // Escalado manual por el usuario
    public function escalate(Request $request)
    {
        $request->validate([
            'conversation_id' => ['required', 'integer', 'exists:support_conversations,id'],
            'reason'          => ['nullable', 'string', 'max:500'],
        ]);

        $user         = $request->user();
        $conversation = SupportConversation::where('id', $request->conversation_id)
            ->where('user_id', $user->id)->firstOrFail();

        if ($conversation->ticket_id) {
            return response()->json(['ticket_id' => $conversation->ticket_id, 'already_escalated' => true]);
        }

        $lastMessage = collect($conversation->messages)->last();
        $subject     = $request->reason ?? ($lastMessage['content'] ?? 'Consulta de soporte');

        $classification = $this->classifier->classify($subject);
        $context        = $this->contextBuilder->buildForUser($user);

        $ticket = SupportTicket::create([
            'user_id'               => $user->id,
            'category'              => $classification['category'],
            'status'                => 'abierto',
            'priority'              => $classification['priority'],
            'classifier_confidence' => $classification['confidence'],
            'subject'               => mb_substr($subject, 0, 200),
            'context_snapshot'      => $context,
            'audit_timeline'        => $conversation->messages,
        ]);

        $conversation->update([
            'ticket_id'         => $ticket->id,
            'escalated'         => true,
            'escalation_reason' => $request->reason,
        ]);

        $this->patternDetector->analyze($ticket);

        return response()->json(['ticket_id' => $ticket->id, 'already_escalated' => false]);
    }

    // Centro de ayuda — artículos
    public function knowledge()
    {
        $articles = SupportArticle::published()
            ->orderBy('category')
            ->orderByDesc('helpful_count')
            ->get()
            ->groupBy('category');

        return view('support.knowledge.index', compact('articles'));
    }

    public function article(SupportArticle $article)
    {
        abort_unless($article->is_published, 404);

        return view('support.knowledge.show', compact('article'));
    }

    public function markHelpful(Request $request, SupportArticle $article)
    {
        $request->validate(['helpful' => ['required', 'boolean']]);
        abort_unless($article->is_published, 404);

        if ($request->boolean('helpful')) {
            $article->increment('helpful_count');
        } else {
            $article->increment('not_helpful_count');
        }

        return response()->json(['ok' => true]);
    }

    // Estado del servicio (sin auth). Ordenamiento portable MySQL/SQLite.
    public function status()
    {
        $components = SupportServiceStatus::orderByRaw(
            "CASE status WHEN 'caido' THEN 0 WHEN 'degradado' THEN 1 WHEN 'mantenimiento' THEN 2 ELSE 3 END"
        )->get();

        return view('support.status', compact('components'));
    }

    // Mis tickets
    public function myTickets(Request $request)
    {
        $tickets = SupportTicket::where('user_id', $request->user()->id)
            ->latest()
            ->paginate(15);

        return view('support.my-tickets.index', compact('tickets'));
    }

    public function showTicket(Request $request, SupportTicket $ticket)
    {
        abort_unless($ticket->user_id === $request->user()->id, 403);
        $ticket->load('conversation');

        return view('support.my-tickets.show', compact('ticket'));
    }

    // Satisfacción post-resolución (llega desde email)
    public function satisfaction(Request $request, SupportTicket $ticket)
    {
        abort_unless(
            $ticket->user_id === $request->user()?->id || $request->has('token') || $request->has('response'),
            403
        );

        $response = $request->input('response');
        abort_unless(in_array($response, ['positiva', 'negativa']), 422);

        if (is_null($ticket->satisfaction_response)) {
            $ticket->update(['satisfaction_response' => $response]);

            if ($response === 'negativa') {
                $ticket->update(['status' => 'reabierto']);
            }
        }

        return view('support.satisfaction', compact('ticket', 'response'));
    }

    // Feature requests
    public function featureRequests()
    {
        $features = SupportFeatureRequest::visible()
            ->byVotes()
            ->paginate(20);

        $userVotes = auth()->check()
            ? SupportFeatureVote::where('user_id', auth()->id())
                ->pluck('feature_request_id')
                ->toArray()
            : [];

        return view('support.feature-requests.index', compact('features', 'userVotes'));
    }

    public function vote(Request $request, SupportFeatureRequest $fr)
    {
        $user = $request->user();

        $existing = SupportFeatureVote::where('feature_request_id', $fr->id)
            ->where('user_id', $user->id)->first();

        if ($existing) {
            $existing->delete();
            $fr->decrement('votes_count');
            $voted = false;
        } else {
            SupportFeatureVote::create(['feature_request_id' => $fr->id, 'user_id' => $user->id]);
            $fr->increment('votes_count');
            $voted = true;
        }

        return response()->json(['voted' => $voted, 'votes' => $fr->fresh()->votes_count]);
    }
}
