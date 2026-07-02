<?php

namespace App\Services\Support;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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
     * @param  array  $history  Historial previo [{role, content}]
     * @return array{response:string, should_escalate:bool, escalation_reason:?string, diagnostic:array}
     */
    public function chat(User $user, string $message, array $history = []): array
    {
        $context    = $this->contextBuilder->buildForUser($user);
        $diagnostic = $this->diagnosticService->diagnose($user, $message);

        $articles  = $this->knowledgeBase->search($message);
        $kbContext = $articles->map(fn ($a) => "### {$a->title}\n{$a->content}")->implode("\n\n");

        $systemPrompt = $this->buildSystemPrompt($context, $diagnostic, $kbContext);
        $aiResponse   = $this->callGemini($systemPrompt, $history, $message);

        $shouldEscalate = $this->detectEscalation($aiResponse, $message, count($history));

        return [
            'response'          => $aiResponse,
            'should_escalate'   => $shouldEscalate,
            'escalation_reason' => $shouldEscalate ? $this->extractEscalationReason($message) : null,
            'diagnostic'        => $diagnostic,
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

        $clean = preg_replace('/```json|```/', '', $response);
        $clean = trim($clean);

        try {
            $data = json_decode($clean, true, 512, JSON_THROW_ON_ERROR);

            return [
                'category'   => $data['category'] ?? 'otro',
                'priority'   => $data['priority'] ?? 'media',
                'confidence' => (float) ($data['confidence'] ?? 0.5),
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
            : '✅ Sin problemas detectados automáticamente.';

        $torneos       = $this->formatArray($context['torneos_activos']);
        $clubes        = $this->formatArray($context['clubes_capitaneados']);
        $oportunidades = $this->formatArray($context['oportunidades_abiertas']);

        return <<<SYSTEM
Eres el asistente de soporte de FutGO, una plataforma de fútbol amateur en Colombia.
Responde siempre en español neutro, con tuteo (tú, tienes, puedes) — nunca uses voseo (vos) ni ustedeo. Sé claro, amable y conciso.
Nunca inventes funcionalidades que no existen. Si no sabes algo, dilo honestamente.

━━━ CONTEXTO DEL USUARIO ━━━
Nombre: {$context['nombre']}
Rol: {$context['rol']}
Ciudad: {$context['ciudad']}
Nivel: {$context['nivel']}
FutGO ID: {$context['futgo_id']}
Torneos activos: {$torneos}
Clubes que capitanea: {$clubes}
Oportunidades abiertas: {$oportunidades}

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
- Si el problema requiere acción de un administrador (modificar datos, resolver disputa, error técnico no documentado), indícalo claramente y dile al usuario que vas a crear un ticket.
- Frase para indicar escalado: "Voy a crear un ticket para que el equipo de FutGO lo revise y te notifique por email."

━━━ ARTÍCULOS RELEVANTES DE LA BASE DE CONOCIMIENTO ━━━
{$kbContext}

━━━ INSTRUCCIONES DE RESPUESTA ━━━
1. Si el diagnóstico automático identificó el problema, explícalo directamente sin decirle al usuario que "hiciste un diagnóstico".
2. Da pasos concretos y numerados cuando la solución tiene múltiples pasos.
3. Si no puedes resolver el problema, indica que vas a crear un ticket. Usa exactamente la frase: "Voy a crear un ticket para que el equipo de FutGO lo revise."
4. Máximo 3 párrafos. Sé directo.
SYSTEM;
    }

    private function callGemini(string $systemPrompt, array $history, string $newMessage): string
    {
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/'
            . config('support.chat_model')
            . ':generateContent?key='
            . config('support.google_ai_key');

        $contents = [];
        foreach ($history as $msg) {
            $contents[] = [
                'role'  => ($msg['role'] ?? 'user') === 'assistant' ? 'model' : 'user',
                'parts' => [['text' => $msg['content'] ?? '']],
            ];
        }
        if (! empty($newMessage)) {
            $contents[] = [
                'role'  => 'user',
                'parts' => [['text' => $newMessage]],
            ];
        }

        $payload = [
            'system_instruction' => [
                'parts' => [['text' => $systemPrompt]],
            ],
            'contents'         => $contents,
            'generationConfig' => [
                'maxOutputTokens' => config('support.max_tokens', 1000),
                'temperature'     => config('support.temperature', 0.3),
            ],
        ];

        $attempts    = 0;
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
            if (str_contains(mb_strtolower($response), $phrase)) {
                return true;
            }
        }

        // Si el usuario lleva varios intercambios y sigue sin resolverse
        if ($historyLength >= config('support.escalation_after', 2) * 2) {
            $userFrustration = ['no funciona', 'sigue sin', 'todavía no', 'no me ayudó', 'quiero hablar'];
            foreach ($userFrustration as $phrase) {
                if (str_contains(mb_strtolower($userMessage), $phrase)) {
                    return true;
                }
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
        if (empty($items)) {
            return 'ninguno';
        }
        if (is_string($items[0])) {
            return implode(', ', $items);
        }

        return json_encode($items, JSON_UNESCAPED_UNICODE);
    }
}
