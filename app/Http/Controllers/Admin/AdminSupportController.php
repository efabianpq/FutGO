<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Support\SupportArticle;
use App\Models\Support\SupportFeatureRequest;
use App\Models\Support\SupportIncidentPattern;
use App\Models\Support\SupportServiceStatus;
use App\Models\Support\SupportTicket;
use App\Models\User;
use App\Services\Support\KnowledgeBaseService;
use App\Services\Support\SupportAIGateway;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AdminSupportController extends Controller
{
    /** Ordenamiento de prioridad portable MySQL/SQLite. */
    private const PRIORITY_ORDER = "CASE priority WHEN 'critica' THEN 0 WHEN 'alta' THEN 1 WHEN 'media' THEN 2 ELSE 3 END";

    public function __construct(
        private KnowledgeBaseService $knowledgeBase,
        private SupportAIGateway $gateway,
    ) {}

    public function dashboard()
    {
        $stats = [
            'total'                 => SupportTicket::count(),
            'abiertos'              => SupportTicket::where('status', 'abierto')->count(),
            'en_revision'           => SupportTicket::where('status', 'en_revision')->count(),
            'resueltos_hoy'         => SupportTicket::where('status', 'resuelto')->whereDate('resolved_at', today())->count(),
            'sin_asignar'           => SupportTicket::whereNull('assigned_to')->open()->count(),
            'criticos'              => SupportTicket::where('priority', 'critica')->open()->count(),
            'satisfaccion_positiva' => SupportTicket::where('satisfaction_response', 'positiva')->count(),
            'satisfaccion_negativa' => SupportTicket::where('satisfaction_response', 'negativa')->count(),
        ];

        $ticketsRecientes = SupportTicket::with('user')
            ->open()
            ->orderByRaw(self::PRIORITY_ORDER)
            ->take(10)->get();

        $patronesActivos = SupportIncidentPattern::where('resolved', false)
            ->whereNotNull('team_alerted_at')
            ->latest('first_detected_at')
            ->take(5)->get();

        $statusComponents = SupportServiceStatus::all();

        return view('admin.support.dashboard', compact('stats', 'ticketsRecientes', 'patronesActivos', 'statusComponents'));
    }

    public function tickets(Request $request)
    {
        $tickets = SupportTicket::with(['user', 'assignedTo'])
            ->when($request->category, fn ($q) => $q->where('category', $request->category))
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->priority, fn ($q) => $q->where('priority', $request->priority))
            ->when($request->assigned, fn ($q) => $q->where('assigned_to', $request->assigned))
            ->orderByRaw(self::PRIORITY_ORDER)
            ->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString();

        return view('admin.support.tickets.index', compact('tickets'));
    }

    public function show(SupportTicket $ticket)
    {
        $ticket->load(['user', 'assignedTo', 'conversation']);
        $admins = User::where('role', 'admin')->get(['id', 'name']);

        return view('admin.support.tickets.show', compact('ticket', 'admins'));
    }

    public function updateStatus(Request $request, SupportTicket $ticket)
    {
        $request->validate(['status' => ['required', 'in:abierto,en_diagnostico,esperando_usuario,en_revision,resuelto,cerrado,reabierto']]);
        $ticket->update(['status' => $request->status]);

        return back()->with('success', 'Estado actualizado.');
    }

    public function assign(Request $request, SupportTicket $ticket)
    {
        $request->validate(['assigned_to' => ['required', 'exists:users,id']]);
        $ticket->update(['assigned_to' => $request->assigned_to, 'status' => 'en_revision']);

        return back()->with('success', 'Ticket asignado.');
    }

    public function resolve(Request $request, SupportTicket $ticket)
    {
        $request->validate(['resolution_notes' => ['required', 'string', 'max:2000']]);
        $ticket->update([
            'status'           => 'resuelto',
            'resolution_notes' => $request->resolution_notes,
            'resolved_at'      => now(),
        ]);

        return back()->with('success', 'Ticket resuelto. Se enviará email de satisfacción en la próxima hora.');
    }

    public function generateArticle(Request $request, SupportTicket $ticket)
    {
        abort_unless($ticket->isResolved(), 422, 'Solo se pueden generar artículos de tickets resueltos.');

        $conversationText = collect($ticket->conversation?->messages ?? [])
            ->map(fn ($m) => "{$m['role']}: {$m['content']}")
            ->implode("\n");

        $article = $this->knowledgeBase->generateArticleFromTicket($ticket, $conversationText);

        return back()->with('success', "Artículo generado: '{$article->title}'. Revisalo y publicalo desde el Centro de Conocimiento.");
    }

    public function knowledge()
    {
        $articles = SupportArticle::latest()->paginate(25);

        return view('admin.support.knowledge', compact('articles'));
    }

    public function storeArticle(Request $request)
    {
        $data = $request->validate([
            'title'    => ['required', 'string', 'max:200'],
            'content'  => ['required', 'string'],
            'category' => ['required', 'in:torneos,social,cuenta,tecnico,politicas'],
        ]);

        $data['slug']         = Str::slug($data['title']) . '-' . time();
        $data['source']       = 'manual';
        $data['is_published'] = false;

        SupportArticle::create($data);

        return back()->with('success', 'Artículo creado. Publicalo cuando esté listo.');
    }

    public function publishArticle(SupportArticle $article)
    {
        $article->update(['is_published' => ! $article->is_published]);
        $msg = $article->is_published ? 'Artículo publicado.' : 'Artículo despublicado.';

        return back()->with('success', $msg);
    }

    public function deleteArticle(SupportArticle $article)
    {
        $article->delete();

        return back()->with('success', 'Artículo eliminado.');
    }

    public function statusPanel()
    {
        $components = SupportServiceStatus::all();

        return view('admin.support.status', compact('components'));
    }

    public function updateComponent(Request $request, string $component)
    {
        $request->validate([
            'status'  => ['required', 'in:operativo,degradado,caido,mantenimiento'],
            'message' => ['nullable', 'string', 'max:300'],
        ]);

        SupportServiceStatus::where('component', $component)->update([
            'status'        => $request->status,
            'message'       => $request->message,
            'auto_detected' => false,
            'updated_at'    => now(),
        ]);

        return back()->with('success', 'Estado actualizado.');
    }

    public function featureRequests()
    {
        $features = SupportFeatureRequest::withCount('votes')->latest()->paginate(25);

        return view('admin.support.feature-requests', compact('features'));
    }

    public function updateFeatureStatus(Request $request, SupportFeatureRequest $fr)
    {
        $request->validate(['status' => ['required', 'in:recibido,evaluando,planeado,en_desarrollo,lanzado,descartado']]);
        $fr->update(['status' => $request->status]);

        return back()->with('success', 'Estado de funcionalidad actualizado.');
    }
}
