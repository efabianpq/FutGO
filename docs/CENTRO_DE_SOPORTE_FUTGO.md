# Sesión: Centro de Soporte Inteligente — FutGO

## Contexto del proyecto

FutGO es una plataforma de fútbol amateur (torneos + red social deportiva) funcionalmente
completa al 100%. Este documento describe la implementación de un nuevo módulo: el
**Centro de Soporte Inteligente**, con bot de IA basado en Gemini 1.5 Flash, sistema de
tickets, base de conocimiento viva, roadmap conectado y monitoreo del estado del servicio.

**Stack:** PHP 8.3.30 · Laravel 11.46 · MySQL 8.0.30 · Blade + Alpine.js 3 · Tailwind 3 · Vite 5
**Tests actuales:** passing — no deben romperse bajo ninguna circunstancia.
**URL local:** `http://futgo.test:8080`

### Arquitectura existente relevante para este módulo

- `users.role` ENUM incluye `admin` y `torneo_admin`. Solo `admin` tiene acceso al panel
  de soporte interno completo. `torneo_admin` puede ver tickets de sus propios usuarios.
- Middleware existentes: `EnsureModule`, `EnsureTorneoAdmin`, `auth`, `ensure.active`.
- Scheduler: único cron cada minuto → `scheduler.sh` → `php artisan schedule:run`.
  Comandos activos: `torneos:match-reminders` (hourly), `social:expire-opportunities`
  (hourly), `social:detect-no-shows` (hourly), `social:rebuild-reliability` (diario 04:00),
  `backup:run` (03:00), `backup:clean` (03:30) 
- Cola: `QUEUE_CONNECTION=database`. Tabla `jobs` existe.
- Storage: `MEDIA_DISK` env (`public` dev / `r2` prod). No hardcodear disco.
- Morph map registrado en `AppServiceProvider`: `user`, `club`, `tournament`, `opportunity`,
  `friendly_match`, `message`, `achievement`, `feed_event`, `venue`.
- Sistema de diseño: CSS vars `--c-green`, `--c-navy`, `--c-green-strong`, etc. Mismos
  tokens en todas las vistas. Componentes reutilizables: `<x-avatar>`, `<x-logo>`,
  `<x-nav-dropdown>`. Nav en `components/nav.blade.php`.
- Convenciones: español, voseo. Commits: `tipo: descripción en español`.

---

## Concepto del módulo

El Centro de Soporte tiene **7 módulos** accesibles desde una pantalla hub:

```
Centro de Soporte
│
├── 💬 Asistente IA          — bot con contexto del usuario, diagnóstico silencioso
├── 📚 Centro de ayuda       — base de conocimiento con artículos y búsqueda
├── 🐞 Reportar problema     — captura automática de contexto + creación de ticket
├── 💡 Enviar sugerencia     — idea libre que puede convertirse en feature request
├── ⭐ Solicitar funcionalidad — feature requests con votos públicos
├── 📋 Mis casos             — historial de tickets del usuario con estado
└── ❤️ Estado del servicio   — estado de cada componente, accesible sin login
```

**Flujo principal:**
1. Usuario entra al hub → elige módulo o escribe al bot.
2. El bot hace un diagnóstico silencioso (verifica el estado del sistema para ese usuario).
3. Si resuelve → conversación guardada, ticket NO creado.
4. Si no resuelve o el usuario escala → ticket creado con todo el contexto.
5. Admin gestiona tickets desde `/admin/soporte`.
6. Al resolver → email de satisfacción al usuario (👍 / 👎).
7. Admin puede generar artículo de KB desde cualquier ticket resuelto.

---

## PARTE 1 — MIGRACIONES

Crear en el orden indicado. Todas en `database/migrations/`.

### Migración 1: `support_tickets`

```php
Schema::create('support_tickets', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->enum('category', [
        'bug', 'duda', 'disputa', 'sugerencia',
        'funcionalidad', 'reclamo', 'abuso',
        'cuenta', 'verificacion', 'otro'
    ])->default('otro');
    $table->enum('status', [
        'abierto', 'en_diagnostico', 'esperando_usuario',
        'en_revision', 'resuelto', 'cerrado', 'reabierto'
    ])->default('abierto');
    $table->enum('priority', ['critica', 'alta', 'media', 'baja'])->default('media');
    $table->decimal('classifier_confidence', 3, 2)->default(0.00);
    $table->string('subject', 200);
    $table->json('context_snapshot')->nullable();
    $table->json('error_trace')->nullable();
    $table->json('audit_timeline')->nullable();
    $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
    $table->text('resolution_notes')->nullable();
    $table->enum('satisfaction_response', ['positiva', 'negativa'])->nullable();
    $table->timestamp('resolved_at')->nullable();
    $table->timestamp('satisfaction_sent_at')->nullable();
    $table->timestamps();

    $table->index(['user_id', 'status']);
    $table->index(['status', 'priority']);
    $table->index('category');
});
```

### Migración 2: `support_conversations`

```php
Schema::create('support_conversations', function (Blueprint $table) {
    $table->id();
    $table->foreignId('ticket_id')->nullable()->constrained('support_tickets')->nullOnDelete();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->json('messages');           // array de {role, content, created_at}
    $table->json('diagnostic_result')->nullable();
    $table->boolean('escalated')->default(false);
    $table->text('escalation_reason')->nullable();
    $table->timestamps();

    $table->index('user_id');
    $table->index('ticket_id');
});
```

### Migración 3: `support_articles`

```php
Schema::create('support_articles', function (Blueprint $table) {
    $table->id();
    $table->string('title', 200);
    $table->string('slug', 200)->unique();
    $table->text('content');
    $table->enum('category', ['torneos', 'social', 'cuenta', 'tecnico', 'politicas']);
    $table->enum('source', ['manual', 'auto_generado'])->default('manual');
    $table->foreignId('source_ticket_id')->nullable()
          ->constrained('support_tickets')->nullOnDelete();
    $table->integer('helpful_count')->default(0);
    $table->integer('not_helpful_count')->default(0);
    $table->boolean('is_published')->default(false);
    $table->timestamps();

    $table->index(['category', 'is_published']);
    $table->fullText(['title', 'content']);
});
```

### Migración 4: `support_feature_requests`

```php
Schema::create('support_feature_requests', function (Blueprint $table) {
    $table->id();
    $table->foreignId('ticket_id')->nullable()
          ->constrained('support_tickets')->nullOnDelete();
    $table->string('title', 200);
    $table->text('description');
    $table->enum('status', [
        'recibido', 'evaluando', 'planeado',
        'en_desarrollo', 'lanzado', 'descartado'
    ])->default('recibido');
    $table->integer('votes_count')->default(0);
    $table->timestamps();

    $table->index('status');
});
```

### Migración 5: `support_feature_votes`

```php
Schema::create('support_feature_votes', function (Blueprint $table) {
    $table->id();
    $table->foreignId('feature_request_id')
          ->constrained('support_feature_requests')->cascadeOnDelete();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->timestamps();

    $table->unique(['feature_request_id', 'user_id']);
});
```

### Migración 6: `support_service_status`

```php
Schema::create('support_service_status', function (Blueprint $table) {
    $table->id();
    $table->string('component', 100)->unique();
    $table->enum('status', ['operativo', 'degradado', 'caido', 'mantenimiento'])
          ->default('operativo');
    $table->text('message')->nullable();
    $table->timestamp('last_checked_at')->nullable();
    $table->boolean('auto_detected')->default(true);
    $table->timestamps();
});
```

**Seeder inline en la migración** (en el método `up`, después de `Schema::create`):

```php
DB::table('support_service_status')->insert([
    ['component' => 'plataforma',      'status' => 'operativo', 'created_at' => now(), 'updated_at' => now()],
    ['component' => 'login',           'status' => 'operativo', 'created_at' => now(), 'updated_at' => now()],
    ['component' => 'correos',         'status' => 'operativo', 'created_at' => now(), 'updated_at' => now()],
    ['component' => 'notificaciones',  'status' => 'operativo', 'created_at' => now(), 'updated_at' => now()],
    ['component' => 'ranking',         'status' => 'operativo', 'created_at' => now(), 'updated_at' => now()],
    ['component' => 'scheduler',       'status' => 'operativo', 'created_at' => now(), 'updated_at' => now()],
]);
```

### Migración 7: `support_incident_patterns`

```php
Schema::create('support_incident_patterns', function (Blueprint $table) {
    $table->id();
    $table->string('pattern_key', 100)->index();
    $table->integer('tickets_count')->default(1);
    $table->timestamp('first_detected_at');
    $table->timestamp('team_alerted_at')->nullable();
    $table->boolean('resolved')->default(false);
    $table->timestamps();
});
```

---

## PARTE 2 — MODELOS ELOQUENT

Crear en `app/Models/Support/`. Todos con namespace `App\Models\Support`.

### `SupportTicket`

```php
protected $fillable = [
    'user_id', 'category', 'status', 'priority', 'classifier_confidence',
    'subject', 'context_snapshot', 'error_trace', 'audit_timeline',
    'assigned_to', 'resolution_notes', 'satisfaction_response',
    'resolved_at', 'satisfaction_sent_at',
];

protected $casts = [
    'context_snapshot' => 'array',
    'error_trace'      => 'array',
    'audit_timeline'   => 'array',
    'resolved_at'      => 'datetime',
    'satisfaction_sent_at' => 'datetime',
    'classifier_confidence' => 'float',
];

// Relaciones
public function user(): BelongsTo  // → User
public function assignedTo(): BelongsTo  // → User (foreign: assigned_to)
public function conversation(): HasOne  // → SupportConversation
public function featureRequest(): HasOne  // → SupportFeatureRequest

// Scopes
public function scopeOpen($q)      { return $q->whereNotIn('status', ['resuelto', 'cerrado']); }
public function scopePending($q)   { return $q->where('status', 'abierto'); }
public function scopeUrgent($q)    { return $q->whereIn('priority', ['critica', 'alta']); }

// Helpers
public function isResolved(): bool   { return in_array($this->status, ['resuelto', 'cerrado']); }
public function needsSatisfaction(): bool {
    return $this->status === 'resuelto'
        && is_null($this->satisfaction_sent_at)
        && is_null($this->satisfaction_response);
}
```

### `SupportConversation`

```php
protected $fillable = [
    'ticket_id', 'user_id', 'messages', 'diagnostic_result', 'escalated', 'escalation_reason'
];

protected $casts = [
    'messages'         => 'array',
    'diagnostic_result'=> 'array',
    'escalated'        => 'boolean',
];

public function ticket(): BelongsTo   // → SupportTicket
public function user(): BelongsTo     // → User

public function addMessage(string $role, string $content): void {
    $messages = $this->messages ?? [];
    $messages[] = ['role' => $role, 'content' => $content, 'created_at' => now()->toISOString()];
    $this->update(['messages' => $messages]);
}
```

### `SupportArticle`

```php
protected $fillable = [
    'title', 'slug', 'content', 'category', 'source',
    'source_ticket_id', 'helpful_count', 'not_helpful_count', 'is_published'
];

protected $casts = ['is_published' => 'boolean'];

public function scopePublished($q) { return $q->where('is_published', true); }
public function sourceTicket(): BelongsTo  // → SupportTicket (foreign: source_ticket_id)
```

### `SupportFeatureRequest`

```php
protected $fillable = ['ticket_id', 'title', 'description', 'status', 'votes_count'];

public function ticket(): BelongsTo     // → SupportTicket
public function votes(): HasMany        // → SupportFeatureVote
public function scopeVisible($q)  { return $q->whereNotIn('status', ['descartado']); }
public function scopeByVotes($q)  { return $q->orderByDesc('votes_count'); }

public function hasVotedBy(User $user): bool {
    return $this->votes()->where('user_id', $user->id)->exists();
}
```

### `SupportFeatureVote`

```php
protected $fillable = ['feature_request_id', 'user_id'];
public function featureRequest(): BelongsTo  // → SupportFeatureRequest
public function user(): BelongsTo            // → User
```

### `SupportServiceStatus`

```php
protected $fillable = ['component', 'status', 'message', 'last_checked_at', 'auto_detected'];
protected $casts    = ['last_checked_at' => 'datetime', 'auto_detected' => 'boolean'];

public function scopeByComponent($q, string $component) {
    return $q->where('component', $component);
}
public function isOperational(): bool { return $this->status === 'operativo'; }
public function hasProblem(): bool    { return in_array($this->status, ['caido', 'degradado']); }
```

### `SupportIncidentPattern`

```php
protected $fillable = [
    'pattern_key', 'tickets_count', 'first_detected_at', 'team_alerted_at', 'resolved'
];
protected $casts = [
    'first_detected_at' => 'datetime',
    'team_alerted_at'   => 'datetime',
    'resolved'          => 'boolean',
];
```

---

## PARTE 3 — CONFIGURACIÓN

### `config/support.php` (nuevo archivo)

```php
<?php

return [
    'google_ai_key'    => env('GOOGLE_AI_API_KEY'),
    'chat_model'       => env('SUPPORT_CHAT_MODEL', 'gemini-1.5-flash'),
    'max_tokens'       => env('SUPPORT_AI_MAX_TOKENS', 1000),
    'temperature'      => 0.3,
    'team_email'       => env('SUPPORT_TEAM_EMAIL'),
    'escalation_after' => env('SUPPORT_ESCALATION_THRESHOLD', 2),
    'pattern_window'   => env('SUPPORT_PATTERN_WINDOW_MINUTES', 30),
    'pattern_min'      => env('SUPPORT_PATTERN_MIN_TICKETS', 5),

    // Componentes monitoreados (en orden de visualización)
    'monitored_components' => [
        'plataforma', 'login', 'correos',
        'notificaciones', 'ranking', 'scheduler'
    ],

    // Etiquetas para la UI
    'component_labels' => [
        'plataforma'     => 'Plataforma',
        'login'          => 'Inicio de sesión',
        'correos'        => 'Correos',
        'notificaciones' => 'Notificaciones push',
        'ranking'        => 'Ranking',
        'scheduler'      => 'Automatizaciones',
    ],

    // Colores de estado para la UI (clases Tailwind)
    'status_colors' => [
        'operativo'    => 'text-green-600',
        'degradado'    => 'text-yellow-500',
        'caido'        => 'text-red-600',
        'mantenimiento'=> 'text-blue-500',
    ],
];
```

### Variables en `.env.example`

Agregar al final del `.env.example` existente (sin tocar las otras variables):

```env
# Centro de Soporte — Gemini AI
GOOGLE_AI_API_KEY=
SUPPORT_CHAT_MODEL=gemini-1.5-flash
SUPPORT_AI_MAX_TOKENS=1000
SUPPORT_TEAM_EMAIL=
SUPPORT_ESCALATION_THRESHOLD=2
SUPPORT_PATTERN_WINDOW_MINUTES=30
SUPPORT_PATTERN_MIN_TICKETS=5
```

---

## PARTE 4 — AI GATEWAY (servicios en `app/Services/Support/`)

### `SupportContextBuilder`

```php
/**
 * Construye el snapshot de contexto del usuario para el bot y los tickets.
 * NUNCA incluye email, teléfono, documento ni password.
 */
public function buildForUser(User $user): array
{
    // Rol efectivo
    $rol = match(true) {
        $user->role === 'admin'        => 'Administrador global',
        $user->role === 'torneo_admin' => 'Organizador de torneos',
        $user->captainClubs()->exists()=> 'Capitán de equipo',
        default                        => 'Jugador',
    };

    // Torneos activos (últimos 3)
    $torneos = $user->teamPlayers()
        ->with(['team.tournament'])
        ->get()
        ->map(fn($tp) => $tp->team?->tournament)
        ->filter()
        ->unique('id')
        ->take(3)
        ->map(fn($t) => [
            'nombre' => $t->name,
            'status' => $t->status,
            'formato'=> $t->format,
        ])->values()->toArray();

    // Clubs que capitanea
    $clubes = $user->captainClubs()
        ->with('activeTournaments')
        ->take(3)
        ->get()
        ->map(fn($c) => [
            'nombre' => $c->name,
            'nivel'  => $c->play_level,
            'ciudad' => $c->city,
        ])->toArray();

    // Oportunidades abiertas
    $oportunidades = \App\Models\Social\Opportunity::where('user_id', $user->id)
        ->where('status', 'abierta')
        ->take(3)
        ->pluck('type')
        ->toArray();

    // Amistosos pendientes de resultado
    $amistososPendientes = \App\Models\Social\FriendlyMatch::where('status', 'confirmado')
        ->where('scheduled_at', '<', now())
        ->whereHas('homeClub', fn($q) => $q->where('captain_user_id', $user->id))
        ->orWhereHas('awayClub', fn($q) => $q->where('captain_user_id', $user->id))
        ->count();

    return [
        'user_id'               => $user->id,
        'futgo_id'              => $user->futgo_id,
        'nombre'                => $user->name,
        'rol'                   => $rol,
        'ciudad'                => $user->city ?? 'no especificada',
        'nivel'                 => $user->play_level ?? 'no especificado',
        'torneos_activos'       => $torneos,
        'clubes_capitaneados'   => $clubes,
        'oportunidades_abiertas'=> $oportunidades,
        'amistosos_pendientes_resultado' => $amistososPendientes,
        'confiabilidad'         => optional($user->reliabilityScore)->score,
        'timestamp'             => now()->toISOString(),
    ];
}
```

### `SilentDiagnosticService`

Analiza el mensaje del usuario y verifica el estado del sistema para ese usuario específico
ANTES de responder. El usuario nunca ve los checks crudos, solo la conclusión.

```php
public function diagnose(User $user, string $userMessage): array
{
    $message = mb_strtolower($userMessage);
    $checks  = [];
    $issues  = [];

    // FIXTURE
    if ($this->mentions($message, ['fixture', 'calendario', 'partidos no aparecen', 'cruces'])) {
        $torneos = $user->teamPlayers()
            ->with(['team.tournament.phases'])
            ->get()
            ->map(fn($tp) => $tp->team?->tournament)
            ->filter()->unique('id');

        foreach ($torneos as $torneo) {
            if ($torneo->status === 'open') {
                $checks[] = "Torneo '{$torneo->name}' está en estado 'open' — fixture aún no generado.";
                $issues[] = "El fixture del torneo '{$torneo->name}' no existe porque el torneo todavía está en etapa de inscripción (open). El organizador debe generarlo desde el panel de administración.";
            } elseif ($torneo->status === 'draft') {
                $issues[] = "El torneo '{$torneo->name}' está en borrador — aún no está publicado ni tiene fixture.";
            } else {
                $checks[] = "Torneo '{$torneo->name}': {$torneo->status} ✓";
            }
        }
    }

    // RESULTADO
    if ($this->mentions($message, ['resultado', 'cargar resultado', 'guardar resultado', 'no me deja', 'ingresar resultado'])) {
        $torneos = $user->captainClubs()
            ->with(['teams.tournament'])
            ->get()
            ->flatMap(fn($c) => $c->teams)
            ->map(fn($t) => $t->tournament)
            ->filter()->unique('id');

        foreach ($torneos as $torneo) {
            if ($torneo->status !== 'in_progress') {
                $issues[] = "El torneo '{$torneo->name}' no está en juego (status: {$torneo->status}) — los resultados solo se pueden cargar cuando el torneo está activo.";
            } else {
                $checks[] = "Torneo '{$torneo->name}': en juego ✓";
            }
        }
    }

    // RANKING
    if ($this->mentions($message, ['ranking', 'posición', 'puntaje', 'no actualiza'])) {
        $ultimoRanking = \App\Models\Torneos\FutgoRanking::latest('updated_at')->first();
        if (!$ultimoRanking || $ultimoRanking->updated_at->lt(now()->subHours(25))) {
            $issues[] = "El ranking no se ha actualizado en las últimas 24 horas — se recalcula automáticamente al finalizar torneos.";
        } else {
            $checks[] = "Ranking actualizado: {$ultimoRanking->updated_at->diffForHumans()} ✓";
        }
    }

    // CREDENCIAL QR
    if ($this->mentions($message, ['credencial', 'qr', 'futgo id', 'fg-'])) {
        if (!$user->futgo_id) {
            $issues[] = "El usuario no tiene futgo_id asignado — esto no debería ocurrir. Escalar.";
        } else {
            $checks[] = "FutGO ID: {$user->futgo_id} ✓";
        }
    }

    // CONVOCATORIA
    if ($this->mentions($message, ['convocatoria', 'no aparezco', 'no me convocaron', 'convocar'])) {
        $esCaptian = $user->captainClubs()->exists();
        $checks[] = $esCapitan ? "Usuario es capitán ✓" : "Usuario es jugador (no capitán) — solo los capitanes arman convocatorias.";
    }

    // SCHEDULER / NOTIFICACIONES
    if ($this->mentions($message, ['recordatorio', 'notificación', 'no me llegó', 'email', 'correo'])) {
        $ultimoJob = \DB::table('jobs')->latest('created_at')->first();
        $ultimaNotif = \App\Models\Torneos\TournamentMatchNotification::latest()->first();
        if ($ultimaNotif && $ultimaNotif->created_at->gt(now()->subHours(2))) {
            $checks[] = "Notificaciones: activas ✓";
        } else {
            $checks[] = "No se encontraron notificaciones recientes — puede ser normal si no hay partidos próximos.";
        }
    }

    return [
        'checks'       => $checks,
        'issues_found' => !empty($issues),
        'issues'       => $issues,
        'diagnosis'    => empty($issues) ? null : implode(' ', $issues),
    ];
}

private function mentions(string $message, array $keywords): bool
{
    foreach ($keywords as $kw) {
        if (str_contains($message, $kw)) return true;
    }
    return false;
}
```

### `KnowledgeBaseService`

```php
public function search(string $query): \Illuminate\Support\Collection
{
    // Búsqueda por relevancia usando FULLTEXT si está disponible, o LIKE como fallback
    return \App\Models\Support\SupportArticle::published()
        ->where(function ($q) use ($query) {
            $q->where('title', 'like', "%{$query}%")
              ->orWhere('content', 'like', "%{$query}%");
        })
        ->orderByDesc('helpful_count')
        ->take(3)
        ->get(['id', 'title', 'slug', 'content', 'category']);
}

public function getByCategory(string $category): \Illuminate\Support\Collection
{
    return \App\Models\Support\SupportArticle::published()
        ->where('category', $category)
        ->orderByDesc('helpful_count')
        ->get();
}

public function generateArticleFromTicket(
    \App\Models\Support\SupportTicket $ticket,
    string $aiResponse
): \App\Models\Support\SupportArticle
{
    // Genera título y contenido usando la conversación resuelta
    $slug = \Str::slug($ticket->subject) . '-' . $ticket->id;

    return \App\Models\Support\SupportArticle::create([
        'title'          => $ticket->subject,
        'slug'           => $slug,
        'content'        => $aiResponse,
        'category'       => $this->categoryToArticleCategory($ticket->category),
        'source'         => 'auto_generado',
        'source_ticket_id' => $ticket->id,
        'is_published'   => false,  // requiere revisión manual del admin
    ]);
}

private function categoryToArticleCategory(string $cat): string
{
    return match($cat) {
        'bug', 'disputa' => 'tecnico',
        'cuenta', 'verificacion', 'abuso', 'reclamo' => 'cuenta',
        'duda' => 'torneos',
        'sugerencia', 'funcionalidad' => 'politicas',
        default => 'tecnico',
    };
}
```

### `SupportAIGateway` (orquestador central)

**Importante:** usa la API REST de Google AI directamente con `Http::` de Laravel.
No usar SDKs de terceros. El formato de Gemini difiere de Anthropic:
- `role: "assistant"` en Anthropic → `role: "model"` en Gemini
- `system` separado en Anthropic → `system_instruction` en Gemini
- Respuesta en `candidates[0].content.parts[0].text`

```php
<?php

namespace App\Services\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\User;

class SupportAIGateway
{
    public function __construct(
        private SupportContextBuilder $contextBuilder,
        private SilentDiagnosticService $diagnosticService,
        private KnowledgeBaseService $knowledgeBase,
    ) {}

    /**
     * Procesa un mensaje del usuario y retorna la respuesta del bot.
     *
     * @param User   $user    Usuario autenticado
     * @param string $message Mensaje del usuario
     * @param array  $history Historial previo [{role, content}]
     *
     * @return array {response, should_escalate, escalation_reason, diagnostic}
     */
    public function chat(User $user, string $message, array $history = []): array
    {
        // 1. Contexto del usuario
        $context = $this->contextBuilder->buildForUser($user);

        // 2. Diagnóstico silencioso
        $diagnostic = $this->diagnosticService->diagnose($user, $message);

        // 3. Artículos relevantes de la KB
        $articles = $this->knowledgeBase->search($message);
        $kbContext = $articles->map(fn($a) => "### {$a->title}\n{$a->content}")->implode("\n\n");

        // 4. Construir prompt del sistema
        $systemPrompt = $this->buildSystemPrompt($context, $diagnostic, $kbContext);

        // 5. Llamar a la API
        $aiResponse = $this->callGemini($systemPrompt, $history, $message);

        // 6. Detectar si debe escalar
        $shouldEscalate = $this->detectEscalation($aiResponse, $message, count($history));

        return [
            'response'         => $aiResponse,
            'should_escalate'  => $shouldEscalate,
            'escalation_reason'=> $shouldEscalate ? $this->extractEscalationReason($message) : null,
            'diagnostic'       => $diagnostic,
        ];
    }

    /**
     * Clasifica un mensaje en categoría + prioridad + subject.
     */
    public function classify(string $message, array $history = []): array
    {
        $prompt = <<<PROMPT
Clasificá el siguiente mensaje de soporte de una app de fútbol amateur.
Respondé ÚNICAMENTE con JSON válido, sin markdown, sin explicaciones.

Categorías posibles: bug, duda, disputa, sugerencia, funcionalidad, reclamo, abuso, cuenta, verificacion, otro
Prioridades posibles: critica, alta, media, baja

Criterios de prioridad:
- critica: la app no funciona para el usuario, pierde datos, no puede entrar
- alta: funcionalidad importante no funciona, afecta competencia activa
- media: algo no funciona bien pero hay workaround
- baja: duda, sugerencia, consulta general

Formato de respuesta:
{"category":"bug","priority":"alta","confidence":0.92,"subject":"No puedo cargar el resultado del partido"}

Mensaje a clasificar: {$message}
PROMPT;

        $response = $this->callGemini($prompt, [], '');

        // Limpiar posible markdown en la respuesta
        $clean = preg_replace('/```json|```/', '', $response);
        $clean = trim($clean);

        try {
            $data = json_decode($clean, true, 512, JSON_THROW_ON_ERROR);
            return [
                'category'   => $data['category'] ?? 'otro',
                'priority'   => $data['priority'] ?? 'media',
                'confidence' => (float)($data['confidence'] ?? 0.5),
                'subject'    => $data['subject'] ?? mb_substr($message, 0, 100),
            ];
        } catch (\Throwable $e) {
            return [
                'category'   => 'otro',
                'priority'   => 'media',
                'confidence' => 0.0,
                'subject'    => mb_substr($message, 0, 100),
            ];
        }
    }

    // ─── Privados ─────────────────────────────────────────────────────────────

    private function buildSystemPrompt(array $context, array $diagnostic, string $kbContext): string
    {
        $diagnosticInfo = $diagnostic['issues_found']
            ? "⚠️ DIAGNÓSTICO PREVIO:\n" . implode("\n", $diagnostic['issues'])
            : "✅ Sin problemas detectados automáticamente.";

        return <<<SYSTEM
Sos el asistente de soporte de FutGO, una plataforma de fútbol amateur en Colombia.
Respondé siempre en español, con voseo (vos, tenés, podés). Sé claro, amable y conciso.
Nunca inventes funcionalidades que no existen. Si no sabés algo, decilo honestamente.

━━━ CONTEXTO DEL USUARIO ━━━
Nombre: {$context['nombre']}
Rol: {$context['rol']}
Ciudad: {$context['ciudad']}
Nivel: {$context['nivel']}
FutGO ID: {$context['futgo_id']}
Torneos activos: {$this->formatArray($context['torneos_activos'])}
Clubes que capitanea: {$this->formatArray($context['clubes_capitaneados'])}
Oportunidades abiertas: {$this->formatArray($context['oportunidades_abiertas'])}

{$diagnosticInfo}

━━━ CONOCIMIENTO DE FUTGO ━━━

MÓDULO TORNEOS:
- Estados de torneo: draft (borrador) → open (inscripciones) → in_progress (en juego) → finished (finalizado) → cancelled
- El fixture SOLO se genera cuando el torneo está en 'open' y tiene los equipos suficientes aprobados
- Los resultados SOLO se cargan cuando el torneo está 'in_progress'
- El capitán es quien figura en clubs.captain_user_id — no hay rol global de capitán
- El FutGO ID (FG-XXXXXX) es único y permanente, nunca cambia
- El ranking se recalcula al finalizar torneos o por cron diario — no es tiempo real
- Fórmula de ranking: goles×4 + asistencias×2 + MVP×6 + victorias×3 + vallas×2 + partidos×1 + fair_play×0.5
- Fair play: 100 − 3×amarillas − 10×rojas − 5×inasistencias (mínimo 0)

MÓDULO SOCIAL (FutGO Social):
- Oportunidades: BUSCAR_RIVAL, BUSCAR_JUGADOR, BUSCAR_REFUERZO, BUSCAR_EQUIPO
- Al aceptar BUSCAR_RIVAL se crea un amistoso confirmado automáticamente
- Un amistoso requiere que AMBOS capitanes reporten el marcador para registrarlo
- Si los marcadores no coinciden → queda 'en_disputa', lo resuelve el organizador del torneo o un admin
- La confiabilidad baja con no-shows (−20) y cancelaciones tardías menos de 24hs (−10)
- Dos no-shows en 30 días → disponibilidad pausada, requiere reactivación manual
- Niveles: recreativo → intermedio → competitivo → elite_amateur

MENSAJERÍA:
- Los chats solo se crean cuando se acepta una oportunidad — no se puede iniciar chat sin acuerdo previo
- El número de WhatsApp solo se comparte si el usuario lo hace explícitamente

CREDENCIALES QR:
- El QR codifica solo el FutGO ID + firma HMAC, nunca datos personales
- Un árbitro o admin puede validar escaneando el QR o ingresando el FG-XXXXXX manualmente

DISPUTAS DE RESULTADO:
- Si los marcadores reportados no coinciden, el partido queda 'en_disputa'
- Solo el organizador del torneo o un admin global puede resolver la disputa
- Para solicitar resolución: el capitán debe ir a Mis Amistosos → partido en disputa → Escalar

CÓMO ESCALAR:
- Si el problema requiere acción de un administrador (modificar datos, resolver disputa, error técnico no documentado), indicalo claramente y decí al usuario que vas a crear un ticket.
- Frase para indicar escalado: "Voy a crear un ticket para que el equipo de FutGO lo revise y te notifique por email."

━━━ ARTÍCULOS RELEVANTES DE LA BASE DE CONOCIMIENTO ━━━
{$kbContext}

━━━ INSTRUCCIONES DE RESPUESTA ━━━
1. Si el diagnóstico automático identificó el problema, explicalo directamente sin decirle al usuario que "hiciste un diagnóstico".
2. Dá pasos concretos y numerados cuando la solución tiene múltiples pasos.
3. Si no podés resolver el problema, indicá que vas a crear un ticket. Usá exactamente la frase: "Voy a crear un ticket para que el equipo de FutGO lo revise."
4. Máximo 3 párrafos. Sé directo.
SYSTEM;
    }

    private function callGemini(string $systemPrompt, array $history, string $newMessage): string
    {
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/'
            . config('support.chat_model')
            . ':generateContent?key='
            . config('support.google_ai_key');

        // Construir el array de contents (historial + nuevo mensaje)
        $contents = [];
        foreach ($history as $msg) {
            $contents[] = [
                'role'  => $msg['role'] === 'assistant' ? 'model' : 'user',
                'parts' => [['text' => $msg['content']]],
            ];
        }
        if (!empty($newMessage)) {
            $contents[] = [
                'role'  => 'user',
                'parts' => [['text' => $newMessage]],
            ];
        }

        $payload = [
            'system_instruction' => [
                'parts' => [['text' => $systemPrompt]],
            ],
            'contents' => $contents,
            'generationConfig' => [
                'maxOutputTokens' => config('support.max_tokens', 1000),
                'temperature'     => config('support.temperature', 0.3),
            ],
        ];

        // Retry con backoff exponencial ante rate limiting (429)
        $attempts = 0;
        $maxAttempts = 3;

        while ($attempts < $maxAttempts) {
            $response = Http::timeout(30)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($url, $payload);

            if ($response->status() === 429) {
                $attempts++;
                if ($attempts < $maxAttempts) {
                    sleep(2 ** $attempts); // 2s, 4s, 8s
                    continue;
                }
                throw new \RuntimeException('Demasiadas solicitudes al asistente. Intentá en unos segundos.');
            }

            if ($response->failed()) {
                Log::error('Gemini API error', [
                    'status'   => $response->status(),
                    'response' => $response->json(),
                ]);
                throw new \RuntimeException('El asistente no está disponible en este momento.');
            }

            return $response->json('candidates.0.content.parts.0.text')
                ?? 'No pude generar una respuesta. Por favor intentá de nuevo.';
        }

        throw new \RuntimeException('El asistente no está disponible.');
    }

    private function detectEscalation(string $response, string $userMessage, int $historyLength): bool
    {
        $escalationPhrases = [
            'voy a crear un ticket',
            'no puedo resolver',
            'requiere acción de un administrador',
        ];

        foreach ($escalationPhrases as $phrase) {
            if (str_contains(mb_strtolower($response), $phrase)) return true;
        }

        // Si el usuario lleva 3+ intercambios y sigue sin resolverse
        if ($historyLength >= config('support.escalation_after', 2) * 2) {
            $userFrustration = ['no funciona', 'sigue sin', 'todavía no', 'no me ayudó', 'quiero hablar'];
            foreach ($userFrustration as $phrase) {
                if (str_contains(mb_strtolower($userMessage), $phrase)) return true;
            }
        }

        return false;
    }

    private function extractEscalationReason(string $message): string
    {
        return mb_substr($message, 0, 200);
    }

    private function formatArray(array $items): string
    {
        if (empty($items)) return 'ninguno';
        if (is_string($items[0])) return implode(', ', $items);
        return json_encode($items, JSON_UNESCAPED_UNICODE);
    }
}
```

### `TicketClassifierService`

```php
public function classify(string $message, array $history = []): array
{
    return app(SupportAIGateway::class)->classify($message, $history);
}
```

### `PatternDetectorService`

```php
public function analyze(\App\Models\Support\SupportTicket $ticket): void
{
    // Generar clave de patrón: categoría + primeras palabras del subject
    $words = collect(explode(' ', mb_strtolower($ticket->subject)))->take(3)->implode('_');
    $patternKey = $ticket->category . ':' . $words;

    $windowMinutes = config('support.pattern_window', 30);
    $minTickets    = config('support.pattern_min', 5);

    // Contar tickets con el mismo patrón en la ventana de tiempo
    $count = \App\Models\Support\SupportTicket::where('category', $ticket->category)
        ->where('created_at', '>=', now()->subMinutes($windowMinutes))
        ->count();

    // Actualizar o crear el patrón
    $pattern = \App\Models\Support\SupportIncidentPattern::firstOrCreate(
        ['pattern_key' => $patternKey],
        ['first_detected_at' => now(), 'tickets_count' => 0]
    );

    $pattern->increment('tickets_count');

    // Alertar al equipo si supera el umbral y no se alertó antes
    if ($count >= $minTickets && is_null($pattern->team_alerted_at) && !$pattern->resolved) {
        $this->alertTeam($pattern, $ticket, $count);
        $pattern->update(['team_alerted_at' => now()]);
    }
}

private function alertTeam(
    \App\Models\Support\SupportIncidentPattern $pattern,
    \App\Models\Support\SupportTicket $ticket,
    int $count
): void {
    $teamEmail = config('support.team_email');
    if (!$teamEmail) return;

    \Illuminate\Support\Facades\Mail::raw(
        "⚠️ PATRÓN DETECTADO EN FUTGO SOPORTE\n\n"
        . "Se recibieron {$count} tickets con categoría '{$ticket->category}' "
        . "en los últimos " . config('support.pattern_window') . " minutos.\n\n"
        . "Patrón: {$pattern->pattern_key}\n"
        . "Primer ticket: #{$ticket->id} — {$ticket->subject}\n\n"
        . "Revisá el panel: https://futgo.online/admin/soporte",
        fn($m) => $m->to($teamEmail)->subject("⚠️ FutGO Soporte — Patrón detectado: {$ticket->category}")
    );
}
```

### `StatusMonitorService`

```php
public function runAllChecks(): void
{
    $checks = [
        'plataforma'     => fn() => $this->checkPlataforma(),
        'login'          => fn() => $this->checkLogin(),
        'correos'        => fn() => $this->checkCorreos(),
        'notificaciones' => fn() => $this->checkNotificaciones(),
        'ranking'        => fn() => $this->checkRanking(),
        'scheduler'      => fn() => $this->checkScheduler(),
    ];

    foreach ($checks as $component => $check) {
        try {
            $result = $check();
            \App\Models\Support\SupportServiceStatus::where('component', $component)
                ->update([
                    'status'          => $result['status'],
                    'message'         => $result['message'],
                    'last_checked_at' => now(),
                    'auto_detected'   => true,
                ]);

            // Si caído, crear ticket interno automático
            if ($result['status'] === 'caido') {
                $this->createIncidentTicket($component, $result['message']);
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error("StatusMonitor error for {$component}", ['error' => $e->getMessage()]);
        }
    }
}

private function checkPlataforma(): array
{
    \DB::select('SELECT 1');
    return ['status' => 'operativo', 'message' => null];
}

private function checkLogin(): array
{
    $reciente = \DB::table('sessions')->where('last_activity', '>', now()->subMinutes(10)->timestamp)->exists();
    return $reciente
        ? ['status' => 'operativo', 'message' => null]
        : ['status' => 'degradado', 'message' => 'Sin actividad de sesión reciente.'];
}

private function checkCorreos(): array
{
    $reciente = \DB::table('jobs')
        ->where('created_at', '>', now()->subHours(2))
        ->exists();

    // También verificar que no haya jobs fallidos acumulados
    $failedCount = \DB::table('failed_jobs')->count();
    if ($failedCount > 10) {
        return ['status' => 'degradado', 'message' => "{$failedCount} jobs fallidos acumulados."];
    }

    return ['status' => 'operativo', 'message' => null];
}

private function checkNotificaciones(): array
{
    $reciente = \App\Models\Torneos\TournamentMatchNotification::where('created_at', '>', now()->subHours(25))->exists();
    // No es un error si no hay notificaciones (puede que no haya partidos próximos)
    return ['status' => 'operativo', 'message' => null];
}

private function checkRanking(): array
{
    $ultimo = \App\Models\Torneos\FutgoRanking::latest('updated_at')->first();
    if (!$ultimo) {
        return ['status' => 'degradado', 'message' => 'Sin datos de ranking.'];
    }
    if ($ultimo->updated_at->lt(now()->subHours(25))) {
        return ['status' => 'degradado', 'message' => 'Ranking no actualizado en 25 horas.'];
    }
    return ['status' => 'operativo', 'message' => null];
}

private function checkScheduler(): array
{
    // Verificar que el scheduler corra: buscar el job de recordatorios más reciente
    $log = storage_path('logs/torneos-reminders.log');
    if (file_exists($log) && filemtime($log) > now()->subMinutes(65)->timestamp) {
        return ['status' => 'operativo', 'message' => null];
    }
    return ['status' => 'degradado', 'message' => 'Scheduler sin actividad en más de 1 hora.'];
}

private function createIncidentTicket(string $component, ?string $message): void
{
    // Evitar duplicados: no crear si ya hay un ticket abierto para este componente
    $exists = \App\Models\Support\SupportTicket::where('category', 'bug')
        ->where('subject', 'like', "%{$component}%")
        ->where('status', '!=', 'cerrado')
        ->where('created_at', '>', now()->subHour())
        ->exists();

    if ($exists) return;

    $adminUser = \App\Models\User::where('role', 'admin')->first();
    if (!$adminUser) return;

    \App\Models\Support\SupportTicket::create([
        'user_id'               => $adminUser->id,
        'category'              => 'bug',
        'status'                => 'en_revision',
        'priority'              => 'critica',
        'classifier_confidence' => 1.0,
        'subject'               => "🚨 Monitor: componente '{$component}' caído",
        'context_snapshot'      => ['auto_generated' => true, 'component' => $component],
        'error_trace'           => ['message' => $message, 'detected_at' => now()->toISOString()],
        'assigned_to'           => $adminUser->id,
    ]);
}
```

---

## PARTE 5 — CONTROLADORES Y RUTAS

### Rutas en `routes/web.php`

Agregar después de las rutas existentes, sin modificar nada existente:

```php
// ═══ CENTRO DE SOPORTE — Usuario ═══
Route::middleware(['auth', 'ensure.active'])->prefix('soporte')->name('soporte.')->group(function () {
    Route::get('/',                              [SupportController::class, 'index'])->name('index');
    Route::get('/chat',                          [SupportController::class, 'chat'])->name('chat');
    Route::post('/chat',                         [SupportController::class, 'sendMessage'])->middleware('throttle:30,1')->name('chat.send');
    Route::post('/chat/escalar',                 [SupportController::class, 'escalate'])->name('chat.escalate');
    Route::get('/ayuda',                         [SupportController::class, 'knowledge'])->name('knowledge');
    Route::get('/ayuda/{article:slug}',          [SupportController::class, 'article'])->name('knowledge.article');
    Route::post('/ayuda/{article}/util',         [SupportController::class, 'markHelpful'])->name('knowledge.helpful');
    Route::get('/mis-casos',                     [SupportController::class, 'myTickets'])->name('my-tickets');
    Route::get('/mis-casos/{ticket}',            [SupportController::class, 'showTicket'])->name('my-tickets.show');
    Route::get('/funcionalidades',               [SupportController::class, 'featureRequests'])->name('features');
    Route::post('/funcionalidades/{fr}/votar',   [SupportController::class, 'vote'])->middleware('throttle:5,1')->name('features.vote');
});

// Estado del servicio — SIN auth (para usuarios que no pueden loguearse)
Route::get('/soporte/estado', [SupportController::class, 'status'])->name('soporte.status');

// Satisfacción post-ticket — llega desde un email, sin formulario
Route::get('/soporte/satisfaccion/{ticket}', [SupportController::class, 'satisfaction'])->name('soporte.satisfaction');

// ═══ CENTRO DE SOPORTE — Admin ═══
Route::middleware(['auth', 'ensure.active'])->prefix('admin/soporte')->name('admin.soporte.')->group(function () {
    // Solo admins globales acceden al panel completo
    Route::middleware(function ($request, $next) {
        abort_unless($request->user()?->role === 'admin', 403);
        return $next($request);
    })->group(function () {
        Route::get('/',                                          [AdminSupportController::class, 'dashboard'])->name('dashboard');
        Route::get('/tickets',                                   [AdminSupportController::class, 'tickets'])->name('tickets');
        Route::get('/tickets/{ticket}',                         [AdminSupportController::class, 'show'])->name('tickets.show');
        Route::patch('/tickets/{ticket}/estado',                 [AdminSupportController::class, 'updateStatus'])->name('tickets.status');
        Route::patch('/tickets/{ticket}/asignar',                [AdminSupportController::class, 'assign'])->name('tickets.assign');
        Route::post('/tickets/{ticket}/resolver',                [AdminSupportController::class, 'resolve'])->name('tickets.resolve');
        Route::post('/tickets/{ticket}/generar-articulo',        [AdminSupportController::class, 'generateArticle'])->name('tickets.generate-article');
        Route::get('/conocimiento',                              [AdminSupportController::class, 'knowledge'])->name('knowledge');
        Route::post('/conocimiento',                             [AdminSupportController::class, 'storeArticle'])->name('knowledge.store');
        Route::patch('/conocimiento/{article}/publicar',         [AdminSupportController::class, 'publishArticle'])->name('knowledge.publish');
        Route::delete('/conocimiento/{article}',                 [AdminSupportController::class, 'deleteArticle'])->name('knowledge.delete');
        Route::get('/estado',                                    [AdminSupportController::class, 'statusPanel'])->name('status');
        Route::patch('/estado/{component}',                      [AdminSupportController::class, 'updateComponent'])->name('status.update');
        Route::get('/funcionalidades',                           [AdminSupportController::class, 'featureRequests'])->name('features');
        Route::patch('/funcionalidades/{fr}/estado',             [AdminSupportController::class, 'updateFeatureStatus'])->name('features.status');
    });
});
```

### `SupportController`

```php
<?php

namespace App\Http\Controllers;

use App\Models\Support\{SupportTicket, SupportConversation, SupportArticle, SupportFeatureRequest, SupportFeatureVote, SupportServiceStatus};
use App\Services\Support\{SupportAIGateway, SupportContextBuilder, TicketClassifierService, PatternDetectorService};
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

        $openTickets = SupportTicket::where('user_id', $user->id)->open()->count();

        $serviceIssues = SupportServiceStatus::whereIn('status', ['degradado', 'caido'])->get();

        return view('support.index', compact('openTickets', 'serviceIssues'));
    }

    // Vista del chat
    public function chat(Request $request)
    {
        $conversation = SupportConversation::where('user_id', $request->user()->id)
            ->whereNull('ticket_id')  // conversación activa sin ticket
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

        // Recuperar o crear conversación
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
            // Llamar al gateway de IA
            $result = $this->gateway->chat($user, $message, $history);

            // Guardar en el historial
            $conversation->addMessage('user', $message);
            $conversation->addMessage('assistant', $result['response']);

            // Si debe escalar, crear el ticket
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

                // Vincular conversación al ticket
                $conversation->update([
                    'ticket_id'         => $ticket->id,
                    'escalated'         => true,
                    'escalation_reason' => $result['escalation_reason'],
                ]);

                // Detectar patrones de incidente
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

    // Estado del servicio (sin auth)
    public function status()
    {
        $components = SupportServiceStatus::orderByRaw(
            "FIELD(status, 'caido', 'degradado', 'mantenimiento', 'operativo')"
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
            $ticket->user_id === $request->user()?->id || $request->has('token'),
            403
        );

        $response = $request->input('response');
        abort_unless(in_array($response, ['positiva', 'negativa']), 422);

        if (is_null($ticket->satisfaction_response)) {
            $ticket->update(['satisfaction_response' => $response]);

            // Si negativa → reabrir
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
```

### `AdminSupportController`

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Support\{SupportTicket, SupportArticle, SupportFeatureRequest, SupportServiceStatus, SupportIncidentPattern};
use App\Services\Support\{KnowledgeBaseService, SupportAIGateway};
use Illuminate\Http\Request;

class AdminSupportController extends Controller
{
    public function __construct(
        private KnowledgeBaseService $knowledgeBase,
        private SupportAIGateway $gateway,
    ) {}

    public function dashboard()
    {
        $stats = [
            'total'          => SupportTicket::count(),
            'abiertos'       => SupportTicket::where('status', 'abierto')->count(),
            'en_revision'    => SupportTicket::where('status', 'en_revision')->count(),
            'resueltos_hoy'  => SupportTicket::where('status', 'resuelto')->whereDate('resolved_at', today())->count(),
            'sin_asignar'    => SupportTicket::whereNull('assigned_to')->open()->count(),
            'criticos'       => SupportTicket::where('priority', 'critica')->open()->count(),
            'satisfaccion_positiva' => SupportTicket::where('satisfaction_response', 'positiva')->count(),
            'satisfaccion_negativa' => SupportTicket::where('satisfaction_response', 'negativa')->count(),
        ];

        $ticketsRecientes = SupportTicket::with('user')
            ->open()
            ->orderByRaw("FIELD(priority,'critica','alta','media','baja')")
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
            ->when($request->category, fn($q) => $q->where('category', $request->category))
            ->when($request->status,   fn($q) => $q->where('status',   $request->status))
            ->when($request->priority, fn($q) => $q->where('priority', $request->priority))
            ->when($request->assigned, fn($q) => $q->where('assigned_to', $request->assigned))
            ->orderByRaw("FIELD(priority,'critica','alta','media','baja')")
            ->orderByDesc('created_at')
            ->paginate(25);

        return view('admin.support.tickets.index', compact('tickets'));
    }

    public function show(SupportTicket $ticket)
    {
        $ticket->load(['user', 'assignedTo', 'conversation']);

        $admins = \App\Models\User::where('role', 'admin')->get(['id', 'name']);

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
            ->map(fn($m) => "{$m['role']}: {$m['content']}")
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

        $data['slug']         = \Str::slug($data['title']) . '-' . time();
        $data['source']       = 'manual';
        $data['is_published'] = false;

        SupportArticle::create($data);
        return back()->with('success', 'Artículo creado. Publicalo cuando esté listo.');
    }

    public function publishArticle(SupportArticle $article)
    {
        $article->update(['is_published' => !$article->is_published]);
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
            'status'         => $request->status,
            'message'        => $request->message,
            'auto_detected'  => false,
            'updated_at'     => now(),
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
```

---

## PARTE 6 — VISTAS BLADE

**Seguir exactamente el sistema de diseño de FutGO:** mismos tokens CSS, mismos
componentes (`<x-avatar>`, etc.), mismo layout `layouts/app.blade.php`.
Todas las vistas van en `resources/views/support/` y `resources/views/admin/support/`.

### `support/index.blade.php` — Hub de los 7 módulos

```html
@extends('layouts.app')
@section('title', 'Centro de Soporte')

@section('content')
<div class="max-w-4xl mx-auto px-4 py-8">

    {{-- Banner de problemas activos --}}
    @if($serviceIssues->isNotEmpty())
    <div class="mb-6 p-4 rounded-xl border border-yellow-400 bg-yellow-50 dark:bg-yellow-900/20">
        <p class="font-semibold text-yellow-700 dark:text-yellow-300">
            ⚠️ Hay problemas activos en la plataforma.
            <a href="{{ route('soporte.status') }}" class="underline">Ver estado del servicio →</a>
        </p>
    </div>
    @endif

    <h1 class="text-2xl font-bold mb-2">Centro de Soporte</h1>
    <p class="text-gray-500 mb-8">¿En qué podemos ayudarte?</p>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">

        {{-- Asistente IA --}}
        <a href="{{ route('soporte.chat') }}" class="block p-5 rounded-2xl border border-green/30 bg-green/5 hover:bg-green/10 transition">
            <div class="text-3xl mb-2">💬</div>
            <div class="font-semibold">Asistente IA</div>
            <div class="text-sm text-gray-500 mt-1">Respuestas inmediatas sobre cómo usar FutGO</div>
        </a>

        {{-- Centro de ayuda --}}
        <a href="{{ route('soporte.knowledge') }}" class="block p-5 rounded-2xl border hover:bg-gray-50 dark:hover:bg-white/5 transition">
            <div class="text-3xl mb-2">📚</div>
            <div class="font-semibold">Centro de ayuda</div>
            <div class="text-sm text-gray-500 mt-1">Artículos y guías paso a paso</div>
        </a>

        {{-- Reportar problema --}}
        <a href="{{ route('soporte.chat') }}?tipo=bug" class="block p-5 rounded-2xl border hover:bg-gray-50 dark:hover:bg-white/5 transition">
            <div class="text-3xl mb-2">🐞</div>
            <div class="font-semibold">Reportar problema</div>
            <div class="text-sm text-gray-500 mt-1">Algo no funciona como debería</div>
        </a>

        {{-- Sugerencia --}}
        <a href="{{ route('soporte.chat') }}?tipo=sugerencia" class="block p-5 rounded-2xl border hover:bg-gray-50 dark:hover:bg-white/5 transition">
            <div class="text-3xl mb-2">💡</div>
            <div class="font-semibold">Enviar sugerencia</div>
            <div class="text-sm text-gray-500 mt-1">Compartí una idea para mejorar FutGO</div>
        </a>

        {{-- Feature requests --}}
        <a href="{{ route('soporte.features') }}" class="block p-5 rounded-2xl border hover:bg-gray-50 dark:hover:bg-white/5 transition">
            <div class="text-3xl mb-2">⭐</div>
            <div class="font-semibold">Solicitar funcionalidad</div>
            <div class="text-sm text-gray-500 mt-1">Votá las ideas de la comunidad</div>
        </a>

        {{-- Mis casos --}}
        <a href="{{ route('soporte.my-tickets') }}" class="block p-5 rounded-2xl border hover:bg-gray-50 dark:hover:bg-white/5 transition relative">
            @if($openTickets > 0)
                <span class="absolute top-3 right-3 bg-red-500 text-white text-xs rounded-full px-2 py-0.5">{{ $openTickets }}</span>
            @endif
            <div class="text-3xl mb-2">📋</div>
            <div class="font-semibold">Mis casos</div>
            <div class="text-sm text-gray-500 mt-1">Seguimiento de tus consultas</div>
        </a>

        {{-- Estado --}}
        <a href="{{ route('soporte.status') }}" class="block p-5 rounded-2xl border hover:bg-gray-50 dark:hover:bg-white/5 transition sm:col-span-2 lg:col-span-3">
            <div class="text-3xl mb-2">❤️</div>
            <div class="font-semibold">Estado del servicio</div>
            <div class="text-sm text-gray-500 mt-1">Ver si hay problemas activos en la plataforma</div>
        </a>

    </div>
</div>
@endsection
```

### `support/chat.blade.php` — Chat con el bot

```html
@extends('layouts.app')
@section('title', 'Asistente FutGO')

@section('content')
<div class="max-w-2xl mx-auto px-4 py-6" x-data="supportChat()">

    <div class="flex items-center gap-3 mb-6">
        <div class="w-10 h-10 rounded-full bg-green flex items-center justify-center text-on-green font-bold text-lg">F</div>
        <div>
            <div class="font-semibold">Asistente FutGO</div>
            <div class="text-xs text-green">En línea</div>
        </div>
    </div>

    {{-- Área de mensajes --}}
    <div class="space-y-4 mb-4 min-h-64 max-h-[60vh] overflow-y-auto" x-ref="messagesContainer">

        {{-- Mensaje de bienvenida --}}
        <div class="flex gap-3">
            <div class="w-8 h-8 rounded-full bg-green/20 flex items-center justify-center text-sm shrink-0">F</div>
            <div class="bg-gray-100 dark:bg-white/10 rounded-2xl rounded-tl-sm px-4 py-3 max-w-xs">
                <p class="text-sm">Hola {{ auth()->user()->name }} 👋</p>
                <p class="text-sm mt-1">Soy el asistente de FutGO. Puedo ayudarte con:</p>
                <div class="flex flex-wrap gap-2 mt-3">
                    <template x-for="option in quickOptions" :key="option.text">
                        <button
                            @click="sendQuickMessage(option.text)"
                            class="text-xs px-3 py-1.5 rounded-full border border-green text-green hover:bg-green hover:text-on-green transition"
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
                    <div class="w-8 h-8 rounded-full bg-green/20 flex items-center justify-center text-sm shrink-0">F</div>
                </template>
                <div
                    :class="msg.role === 'user'
                        ? 'bg-green text-on-green rounded-2xl rounded-tr-sm px-4 py-3 max-w-xs'
                        : 'bg-gray-100 dark:bg-white/10 rounded-2xl rounded-tl-sm px-4 py-3 max-w-xs'"
                    x-html="formatMessage(msg.content)">
                </div>
            </div>
        </template>

        {{-- Indicador de carga --}}
        <div x-show="isLoading" class="flex gap-3">
            <div class="w-8 h-8 rounded-full bg-green/20 flex items-center justify-center text-sm shrink-0">F</div>
            <div class="bg-gray-100 dark:bg-white/10 rounded-2xl rounded-tl-sm px-4 py-3">
                <div class="flex gap-1">
                    <span class="w-2 h-2 bg-gray-400 rounded-full animate-bounce" style="animation-delay:0ms"></span>
                    <span class="w-2 h-2 bg-gray-400 rounded-full animate-bounce" style="animation-delay:150ms"></span>
                    <span class="w-2 h-2 bg-gray-400 rounded-full animate-bounce" style="animation-delay:300ms"></span>
                </div>
            </div>
        </div>

        {{-- Ticket creado --}}
        <div x-show="ticketCreated" class="rounded-xl border border-green/30 bg-green/5 p-4 text-sm">
            <p class="font-semibold text-green">✅ Ticket creado</p>
            <p class="text-gray-600 dark:text-gray-300 mt-1">El equipo de FutGO va a revisar tu caso y te notificará por email cuando tengamos respuesta.</p>
            <a :href="'/soporte/mis-casos/' + ticketId" class="inline-block mt-2 text-green underline text-xs">Ver mi ticket →</a>
        </div>

    </div>

    {{-- Botón escalar (aparece después del 2do intercambio) --}}
    <div x-show="messages.length >= 4 && !ticketCreated" class="mb-3 text-center">
        <button @click="escalate()" class="text-xs text-gray-400 hover:text-gray-600 underline">
            ¿No encontraste respuesta? Escalar a soporte humano
        </button>
    </div>

    {{-- Input --}}
    <form @submit.prevent="sendMessage()" class="flex gap-2">
        <input
            x-model="newMessage"
            type="text"
            placeholder="Escribí tu consulta..."
            maxlength="1000"
            :disabled="isLoading || ticketCreated"
            class="flex-1 rounded-xl border px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-green disabled:opacity-50"
        >
        <button
            type="submit"
            :disabled="isLoading || !newMessage.trim() || ticketCreated"
            class="bg-green text-on-green px-4 py-3 rounded-xl disabled:opacity-40 hover:bg-green-strong transition">
            ➤
        </button>
    </form>

</div>

@push('scripts')
<script>
function supportChat() {
    return {
        messages: [],
        newMessage: '',
        isLoading: false,
        conversationId: {{ $conversation?->id ?? 'null' }},
        ticketCreated: false,
        ticketId: null,

        quickOptions: [
            { label: '⚽ Crear torneo', text: '¿Cómo creo un torneo?' },
            { label: '👥 Gestionar equipo', text: '¿Cómo gestiono mi equipo?' },
            { label: '📅 Ver fixture', text: '¿Por qué no aparece el fixture?' },
            { label: '📲 Credencial QR', text: '¿Cómo funciona la credencial QR?' },
            { label: '🐞 Reportar error', text: 'Tengo un problema técnico' },
        ],

        // Inicializar con el tipo de consulta si viene del hub
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
            this.isLoading  = true;

            this.messages.push({ role: 'user', content: userMessage, id: Date.now() });
            this.$nextTick(() => this.scrollToBottom());

            try {
                const res = await fetch('{{ route("soporte.chat.send") }}', {
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
                    this.ticketId      = data.ticket_id;
                }
            } catch (e) {
                this.messages.push({
                    role: 'assistant',
                    content: 'Hubo un error al conectar con el asistente. Intentá de nuevo.',
                    id: Date.now() + 1
                });
            } finally {
                this.isLoading = false;
                this.$nextTick(() => this.scrollToBottom());
            }
        },

        async escalate() {
            if (!this.conversationId) return;
            const res = await fetch('{{ route("soporte.chat.escalate") }}', {
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
                this.ticketId      = data.ticket_id;
            }
        },

        formatMessage(text) {
            // Convertir saltos de línea a <br> y URLs a links
            return text
                .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
                .replace(/\n/g, '<br>')
                .replace(/(https?:\/\/[^\s]+)/g, '<a href="$1" class="underline" target="_blank">$1</a>');
        },

        scrollToBottom() {
            const el = this.$refs.messagesContainer;
            if (el) el.scrollTop = el.scrollHeight;
        }
    }
}
</script>
@endpush
@endsection
```

### `support/status.blade.php` — Estado del servicio (sin auth)

```html
{{-- Layout liviano sin auth --}}
@extends('layouts.public')
@section('title', 'Estado del Servicio — FutGO')

@section('content')
<div class="max-w-2xl mx-auto px-4 py-10">
    <h1 class="text-2xl font-bold mb-2">❤️ Estado del Servicio</h1>
    <p class="text-gray-500 mb-8 text-sm">Actualizado cada 5 minutos automáticamente.</p>

    @php
        $allOk = $components->every(fn($c) => $c->status === 'operativo');
        $labels = config('support.component_labels');
    @endphp

    @if($allOk)
        <div class="mb-6 p-4 rounded-xl bg-green/10 border border-green/30 text-green font-semibold">
            ✅ Todos los sistemas operativos
        </div>
    @else
        <div class="mb-6 p-4 rounded-xl bg-yellow-50 border border-yellow-400 text-yellow-700 font-semibold">
            ⚠️ Hay componentes con problemas activos
        </div>
    @endif

    <div class="space-y-3">
        @foreach($components as $component)
        <div class="flex items-center justify-between p-4 rounded-xl border bg-white dark:bg-white/5">
            <div>
                <div class="font-medium">{{ $labels[$component->component] ?? $component->component }}</div>
                @if($component->message)
                    <div class="text-xs text-gray-500 mt-0.5">{{ $component->message }}</div>
                @endif
                @if($component->last_checked_at)
                    <div class="text-xs text-gray-400 mt-0.5">
                        Verificado {{ $component->last_checked_at->diffForHumans() }}
                    </div>
                @endif
            </div>
            <div class="flex items-center gap-2">
                @php
                    $dot = match($component->status) {
                        'operativo'     => 'bg-green',
                        'degradado'     => 'bg-yellow-400',
                        'caido'         => 'bg-red-500',
                        'mantenimiento' => 'bg-blue-400',
                    };
                    $label = match($component->status) {
                        'operativo'     => 'Operativo',
                        'degradado'     => 'Degradado',
                        'caido'         => 'Caído',
                        'mantenimiento' => 'Mantenimiento',
                    };
                @endphp
                <span class="w-3 h-3 rounded-full {{ $dot }}"></span>
                <span class="text-sm">{{ $label }}</span>
            </div>
        </div>
        @endforeach
    </div>

    <p class="text-center mt-8 text-sm text-gray-400">
        <a href="{{ route('soporte.index') }}" class="underline">Volver al Centro de Soporte</a>
    </p>
</div>

{{-- Auto-refresh cada 60 segundos --}}
<script>setTimeout(() => location.reload(), 60000);</script>
@endsection
```

Las demás vistas (`support/knowledge/`, `support/my-tickets/`, `support/feature-requests/`,
`support/satisfaction.blade.php`, `admin/support/`) deben seguir el mismo patrón de diseño.
Implementarlas con consistencia visual con el resto de FutGO.

---

## PARTE 7 — SCHEDULER Y COMANDOS

Agregar en `routes/console.php` **sin tocar los schedulers existentes**:

```php
// Centro de Soporte — monitor de estado (cada 5 minutos)
Schedule::call(function () {
    app(\App\Services\Support\StatusMonitorService::class)->runAllChecks();
})
->everyFiveMinutes()
->withoutOverlapping()
->appendOutputTo(storage_path('logs/support-monitor.log'));

// Centro de Soporte — emails de satisfacción (cada hora)
Schedule::command('support:send-satisfaction-emails')
    ->hourly()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/support-satisfaction.log'));
```

### Comando `support:send-satisfaction-emails`

Crear en `app/Console/Commands/SendSupportSatisfactionEmails.php`:

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Support\SupportTicket;
use Illuminate\Support\Facades\Mail;

class SendSupportSatisfactionEmails extends Command
{
    protected $signature   = 'support:send-satisfaction-emails';
    protected $description = 'Envía emails de satisfacción a tickets resueltos pendientes';

    public function handle(): void
    {
        $tickets = SupportTicket::where('status', 'resuelto')
            ->whereNull('satisfaction_sent_at')
            ->whereNull('satisfaction_response')
            ->where('resolved_at', '<=', now()->subHours(2))
            ->with('user')
            ->get();

        foreach ($tickets as $ticket) {
            if (!$ticket->user->email) continue;

            $positiveUrl = route('soporte.satisfaction', $ticket) . '?response=positiva';
            $negativeUrl = route('soporte.satisfaction', $ticket) . '?response=negativa';

            Mail::raw(
                "Hola {$ticket->user->name} 👋\n\n"
                . "Resolvimos tu consulta: \"{$ticket->subject}\"\n\n"
                . "¿Se resolvió tu problema?\n\n"
                . "👍 Sí, gracias: {$positiveUrl}\n\n"
                . "👎 No, todavía tengo el problema: {$negativeUrl}\n\n"
                . "— Equipo FutGO",
                fn($m) => $m
                    ->to($ticket->user->email)
                    ->subject("FutGO Soporte — ¿Se resolvió tu consulta?")
            );

            $ticket->update(['satisfaction_sent_at' => now()]);
            $this->info("Email enviado para ticket #{$ticket->id}");
        }

        $this->info("Procesados {$tickets->count()} tickets.");
    }
}
```

---

## PARTE 8 — NAVEGACIÓN

En `resources/views/components/nav.blade.php`, agregar el acceso al Centro de Soporte.
Buscar el dropdown del avatar (donde están "Mi Carrera", "Configurar perfil", "Salir")
y agregar antes de "Salir":

```html
<x-nav-dropdown-item href="{{ route('soporte.index') }}" icon="🆘">
    Centro de Soporte
</x-nav-dropdown-item>
```

O si el nav usa una estructura diferente de links, seguir el mismo patrón visual
que los otros ítems del menú del usuario.

---

## PARTE 9 — TESTS

Crear `tests/Feature/SupportTest.php`. Los tests existentes deben seguir pasando.

```php
<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use App\Models\User;
use App\Models\Support\{SupportTicket, SupportConversation, SupportArticle, SupportFeatureRequest, SupportServiceStatus};

class SupportTest extends TestCase
{
    use RefreshDatabase;

    // Mock de Gemini para todos los tests
    protected function setUp(): void
    {
        parent::setUp();
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => [
                        'parts' => [['text' => 'El fixture solo se genera cuando el torneo está en estado open.']]
                    ]
                ]]
            ], 200),
        ]);
    }

    // Test 1: Hub carga correctamente para usuario autenticado
    public function test_hub_carga_para_usuario_autenticado(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get(route('soporte.index'))->assertOk();
    }

    // Test 2: Chat recibe un mensaje y retorna respuesta de la IA
    public function test_chat_retorna_respuesta_de_ia(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)
            ->postJson(route('soporte.chat.send'), ['message' => '¿Cómo genero el fixture?']);

        $response->assertOk()
            ->assertJsonStructure(['response', 'conversation_id', 'escalated', 'ticket_id']);
        $this->assertNotNull($response->json('conversation_id'));
    }

    // Test 3: El escalado crea un SupportTicket con context_snapshot
    public function test_escalado_crea_ticket_con_contexto(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [['text' => 'Voy a crear un ticket para que el equipo de FutGO lo revise.']]]
                ]]
            ], 200),
        ]);

        $user = User::factory()->create();
        $response = $this->actingAs($user)
            ->postJson(route('soporte.chat.send'), ['message' => 'No puedo cargar el resultado y nada funciona']);

        $response->assertOk();

        if ($response->json('escalated')) {
            $this->assertNotNull($response->json('ticket_id'));
            $ticket = SupportTicket::find($response->json('ticket_id'));
            $this->assertNotNull($ticket->context_snapshot);
            $this->assertEquals($user->id, $ticket->user_id);
        }
    }

    // Test 4: La clasificación asigna categoría y prioridad
    public function test_clasificacion_asigna_categoria_y_prioridad(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [['text' => '{"category":"bug","priority":"alta","confidence":0.95,"subject":"No puedo cargar resultado"}']]]
                ]]
            ], 200),
        ]);

        $service = app(\App\Services\Support\TicketClassifierService::class);
        $result  = $service->classify('No puedo cargar el resultado del partido');

        $this->assertArrayHasKey('category', $result);
        $this->assertArrayHasKey('priority', $result);
        $this->assertArrayHasKey('confidence', $result);
        $this->assertContains($result['category'], ['bug','duda','disputa','sugerencia','funcionalidad','reclamo','abuso','cuenta','verificacion','otro']);
    }

    // Test 5: El estado del servicio es accesible sin autenticación
    public function test_estado_servicio_accesible_sin_auth(): void
    {
        $this->get(route('soporte.status'))->assertOk();
    }

    // Test 6: El voto en feature request es único por usuario
    public function test_voto_es_unico_por_usuario(): void
    {
        $user = User::factory()->create();
        $fr   = SupportFeatureRequest::create([
            'title'       => 'Editar resultados',
            'description' => 'Poder modificar un resultado cargado.',
            'status'      => 'recibido',
        ]);

        // Primer voto
        $this->actingAs($user)->postJson(route('soporte.features.vote', $fr))
            ->assertOk()->assertJson(['voted' => true, 'votes' => 1]);

        // Toggle — quita el voto
        $this->actingAs($user)->postJson(route('soporte.features.vote', $fr))
            ->assertOk()->assertJson(['voted' => false, 'votes' => 0]);
    }

    // Test 7: Admin puede cambiar el estado de un ticket
    public function test_admin_puede_cambiar_estado_de_ticket(): void
    {
        $admin  = User::factory()->create(['role' => 'admin']);
        $user   = User::factory()->create();
        $ticket = SupportTicket::create([
            'user_id'  => $user->id,
            'category' => 'bug',
            'status'   => 'abierto',
            'priority' => 'media',
            'classifier_confidence' => 0.9,
            'subject'  => 'Test ticket',
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.soporte.tickets.status', $ticket), ['status' => 'en_revision'])
            ->assertRedirect();

        $this->assertEquals('en_revision', $ticket->fresh()->status);
    }

    // Test 8: Admin puede resolver un ticket y eso actualiza resolved_at
    public function test_admin_puede_resolver_ticket(): void
    {
        $admin  = User::factory()->create(['role' => 'admin']);
        $user   = User::factory()->create();
        $ticket = SupportTicket::create([
            'user_id'  => $user->id,
            'category' => 'duda',
            'status'   => 'en_revision',
            'priority' => 'baja',
            'classifier_confidence' => 0.8,
            'subject'  => 'Consulta de prueba',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.soporte.tickets.resolve', $ticket), [
                'resolution_notes' => 'Se explicó el proceso al usuario.'
            ])
            ->assertRedirect();

        $ticket->refresh();
        $this->assertEquals('resuelto', $ticket->status);
        $this->assertNotNull($ticket->resolved_at);
    }

    // Test 9: Artículo no publicado no es accesible públicamente
    public function test_articulo_no_publicado_retorna_404(): void
    {
        $user    = User::factory()->create();
        $article = SupportArticle::create([
            'title'       => 'Artículo privado',
            'slug'        => 'articulo-privado',
            'content'     => 'Contenido.',
            'category'    => 'tecnico',
            'source'      => 'manual',
            'is_published'=> false,
        ]);

        $this->actingAs($user)
            ->get(route('soporte.knowledge.article', $article))
            ->assertNotFound();
    }

    // Test 10: Monitor de estado actualiza support_service_status
    public function test_monitor_actualiza_estado_de_componentes(): void
    {
        // Seed inicial necesario
        SupportServiceStatus::create(['component' => 'plataforma', 'status' => 'operativo']);

        $service = app(\App\Services\Support\StatusMonitorService::class);
        $service->runAllChecks();

        $status = SupportServiceStatus::where('component', 'plataforma')->first();
        $this->assertNotNull($status->last_checked_at);
    }
}
```

---

## PARTE 10 — VERIFICACIÓN FINAL

Al terminar de implementar todo, ejecutar en orden:

```bash
php artisan migrate
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan optimize:clear
php artisan test
```

**Los tests existentes deben seguir pasando.**
Los 10 tests nuevos de `SupportTest` deben pasar también.

Si algún test falla:
1. Reportar el mensaje de error exacto
2. Identificar si el fallo es en un test existente (problema de regresión) o en un test nuevo
3. No modificar los tests existentes para que pasen — corregir la implementación

---

## RESUMEN DE ARCHIVOS A CREAR/MODIFICAR

**Crear (nuevos):**
- `database/migrations/*_create_support_tickets_table.php`
- `database/migrations/*_create_support_conversations_table.php`
- `database/migrations/*_create_support_articles_table.php`
- `database/migrations/*_create_support_feature_requests_table.php`
- `database/migrations/*_create_support_feature_votes_table.php`
- `database/migrations/*_create_support_service_status_table.php`
- `database/migrations/*_create_support_incident_patterns_table.php`
- `app/Models/Support/SupportTicket.php`
- `app/Models/Support/SupportConversation.php`
- `app/Models/Support/SupportArticle.php`
- `app/Models/Support/SupportFeatureRequest.php`
- `app/Models/Support/SupportFeatureVote.php`
- `app/Models/Support/SupportServiceStatus.php`
- `app/Models/Support/SupportIncidentPattern.php`
- `app/Services/Support/SupportContextBuilder.php`
- `app/Services/Support/SilentDiagnosticService.php`
- `app/Services/Support/KnowledgeBaseService.php`
- `app/Services/Support/SupportAIGateway.php`
- `app/Services/Support/TicketClassifierService.php`
- `app/Services/Support/PatternDetectorService.php`
- `app/Services/Support/StatusMonitorService.php`
- `app/Http/Controllers/SupportController.php`
- `app/Http/Controllers/Admin/AdminSupportController.php`
- `app/Console/Commands/SendSupportSatisfactionEmails.php`
- `config/support.php`
- `resources/views/support/index.blade.php`
- `resources/views/support/chat.blade.php`
- `resources/views/support/status.blade.php`
- `resources/views/support/satisfaction.blade.php`
- `resources/views/support/knowledge/index.blade.php`
- `resources/views/support/knowledge/show.blade.php`
- `resources/views/support/my-tickets/index.blade.php`
- `resources/views/support/my-tickets/show.blade.php`
- `resources/views/support/feature-requests/index.blade.php`
- `resources/views/admin/support/dashboard.blade.php`
- `resources/views/admin/support/tickets/index.blade.php`
- `resources/views/admin/support/tickets/show.blade.php`
- `resources/views/admin/support/knowledge.blade.php`
- `resources/views/admin/support/status.blade.php`
- `resources/views/admin/support/feature-requests.blade.php`
- `tests/Feature/SupportTest.php`

**Modificar (existentes, mínimamente):**
- `routes/web.php` → agregar rutas de soporte al final
- `routes/console.php` → agregar 2 schedulers sin tocar los existentes
- `resources/views/components/nav.blade.php` → agregar link a Centro de Soporte
- `.env.example` → agregar variables de Gemini y soporte

**NO TOCAR:**
- Tests existentes
- Migraciones existentes
- Servicios de negocio existentes (Torneos, Social)
- `AppServiceProvider` (no agregar morph aliases para Support — no son polimórficos)