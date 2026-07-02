<?php

namespace Tests\Feature\Social;

use App\Models\Social\FriendlyMatch;
use App\Models\Social\Opportunity;
use App\Models\Social\ReliabilityScore;
use App\Models\Torneos\Club;
use App\Models\User;
use App\Services\Social\OpportunityService;
use App\Services\Social\SuggestionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * FutGO Social — Fase 3 · Sesión S3-A: recomendaciones por reglas (sin ML) +
 * modo rápido.
 *
 * Cubre: sugerencias de rivales compatibles (filtros duros + exclusión de
 * pausados), sugerencia de recategorización tras N victorias contra nivel
 * superior, modo rápido (express) que vence en el plazo correcto, e historial
 * de compatibilidad (head-to-head) entre clubs.
 */
class SuggestionAndExpressTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(array $extra = []): User
    {
        return User::create(array_merge([
            'name'      => 'U ' . uniqid(),
            'email'     => uniqid('user') . '@test.com',
            'password'  => bcrypt('password'),
            'role'      => 'user',
        ], $extra));
    }

    private function makeClub(User $captain, string $name, ?string $level = null): Club
    {
        return Club::create([
            'name'               => $name,
            'slug'               => uniqid('club-'),
            'status'             => 'validado',
            'created_by_user_id' => $captain->id,
            'captain_user_id'    => $captain->id,
            'play_level'         => $level,
        ]);
    }

    /** Marca al club como "activo recientemente" publicando una oportunidad reciente. */
    private function makeClubActive(Club $club, User $captain): void
    {
        Opportunity::create([
            'type'           => Opportunity::TYPE_BUSCAR_RIVAL,
            'user_id'        => $captain->id,
            'club_id'        => $club->id,
            'city'           => 'Asunción',
            'required_level' => $club->play_level,
            'status'         => Opportunity::STATUS_ABIERTA,
        ]);
    }

    private function makeJugadoFriendly(Club $home, Club $away, int $homeScore, int $awayScore): FriendlyMatch
    {
        return FriendlyMatch::create([
            'home_club_id'     => $home->id,
            'away_club_id'     => $away->id,
            'status'           => FriendlyMatch::STATUS_JUGADO,
            'result_agreement' => FriendlyMatch::AGREEMENT_ACORDADO,
            'final_home_score' => $homeScore,
            'final_away_score' => $awayScore,
            'scheduled_at'     => now()->subDays(7),
        ]);
    }

    private function service(): SuggestionService
    {
        return app(SuggestionService::class);
    }

    // ── 1. Sugerencias excluyen clubs con disponibilidad pausada ────────────

    public function test_sugerencias_excluyen_clubs_pausados(): void
    {
        $sourceCap = $this->makeUser(['city' => 'Asunción', 'play_level' => 'intermedio']);
        $source    = $this->makeClub($sourceCap, 'Source FC', 'intermedio');

        // Candidato BUENO: misma ciudad, mismo nivel, activo, no pausado.
        $goodCap = $this->makeUser(['city' => 'Asunción', 'play_level' => 'intermedio']);
        $good    = $this->makeClub($goodCap, 'Good FC', 'intermedio');
        $this->makeClubActive($good, $goodCap);

        // Candidato PAUSADO: igual de compatible, pero con disponibilidad pausada.
        $pausedCap = $this->makeUser(['city' => 'Asunción', 'play_level' => 'intermedio']);
        $paused    = $this->makeClub($pausedCap, 'Paused FC', 'intermedio');
        $this->makeClubActive($paused, $pausedCap);
        ReliabilityScore::create([
            'subject_type' => 'club', 'subject_id' => $paused->id,
            'score' => 95, 'is_paused' => true, 'paused_at' => now(),
        ]);

        $opportunity = Opportunity::create([
            'type'           => Opportunity::TYPE_BUSCAR_RIVAL,
            'user_id'        => $sourceCap->id,
            'club_id'        => $source->id,
            'city'           => 'Asunción',
            'required_level' => 'intermedio',
            'status'         => Opportunity::STATUS_ABIERTA,
        ]);

        $suggestions = $this->service()->compatibleRivalsFor($opportunity);
        $ids = $suggestions->pluck('club.id');

        $this->assertTrue($ids->contains($good->id), 'El club bueno debería estar sugerido.');
        $this->assertFalse($ids->contains($paused->id), 'El club pausado NO debe sugerirse.');
        $this->assertFalse($ids->contains($source->id), 'El propio club no se sugiere a sí mismo.');
    }

    // ── 2. Filtros duros: ciudad, nivel, actividad, confiabilidad ───────────

    public function test_sugerencias_aplican_filtros_de_ciudad_nivel_actividad_y_confiabilidad(): void
    {
        $sourceCap = $this->makeUser(['city' => 'Asunción', 'play_level' => 'intermedio']);
        $source    = $this->makeClub($sourceCap, 'Source FC', 'intermedio');

        // Otra ciudad → excluido.
        $otherCityCap = $this->makeUser(['city' => 'Encarnación', 'play_level' => 'intermedio']);
        $otherCity    = $this->makeClub($otherCityCap, 'OtherCity FC', 'intermedio');
        $this->makeClubActive($otherCity, $otherCityCap);

        // Nivel lejano (elite_amateur vs intermedio = 2 de distancia) → excluido.
        $farLevelCap = $this->makeUser(['city' => 'Asunción', 'play_level' => 'elite_amateur']);
        $farLevel    = $this->makeClub($farLevelCap, 'FarLevel FC', 'elite_amateur');
        $this->makeClubActive($farLevel, $farLevelCap);

        // Inactivo (sin actividad reciente) → excluido.
        $inactiveCap = $this->makeUser(['city' => 'Asunción', 'play_level' => 'intermedio']);
        $inactive    = $this->makeClub($inactiveCap, 'Inactive FC', 'intermedio');

        // Confiabilidad baja → excluido.
        $lowCap = $this->makeUser(['city' => 'Asunción', 'play_level' => 'intermedio']);
        $low    = $this->makeClub($lowCap, 'Low FC', 'intermedio');
        $this->makeClubActive($low, $lowCap);
        ReliabilityScore::create([
            'subject_type' => 'club', 'subject_id' => $low->id, 'score' => 40, 'is_paused' => false,
        ]);

        // Adyacente válido (competitivo) → incluido.
        $adjCap = $this->makeUser(['city' => 'Asunción', 'play_level' => 'competitivo']);
        $adj    = $this->makeClub($adjCap, 'Adj FC', 'competitivo');
        $this->makeClubActive($adj, $adjCap);

        $opportunity = Opportunity::create([
            'type'           => Opportunity::TYPE_BUSCAR_RIVAL,
            'user_id'        => $sourceCap->id,
            'club_id'        => $source->id,
            'city'           => 'Asunción',
            'required_level' => 'intermedio',
            'status'         => Opportunity::STATUS_ABIERTA,
        ]);

        $ids = $this->service()->compatibleRivalsFor($opportunity)->pluck('club.id');

        $this->assertTrue($ids->contains($adj->id));
        $this->assertFalse($ids->contains($otherCity->id), 'Otra ciudad excluida.');
        $this->assertFalse($ids->contains($farLevel->id), 'Nivel lejano excluido.');
        $this->assertFalse($ids->contains($inactive->id), 'Inactivo excluido.');
        $this->assertFalse($ids->contains($low->id), 'Confiabilidad baja excluida.');
    }

    public function test_sugerencias_limitadas_a_cinco(): void
    {
        $sourceCap = $this->makeUser(['city' => 'Asunción', 'play_level' => 'intermedio']);
        $source    = $this->makeClub($sourceCap, 'Source FC', 'intermedio');

        for ($i = 0; $i < 8; $i++) {
            $cap  = $this->makeUser(['city' => 'Asunción', 'play_level' => 'intermedio']);
            $club = $this->makeClub($cap, "Cand {$i} FC", 'intermedio');
            $this->makeClubActive($club, $cap);
        }

        $opportunity = Opportunity::create([
            'type'           => Opportunity::TYPE_BUSCAR_RIVAL,
            'user_id'        => $sourceCap->id,
            'club_id'        => $source->id,
            'city'           => 'Asunción',
            'required_level' => 'intermedio',
            'status'         => Opportunity::STATUS_ABIERTA,
        ]);

        $this->assertCount(
            SuggestionService::MAX_SUGGESTIONS,
            $this->service()->compatibleRivalsFor($opportunity)
        );
    }

    // ── 3. Recategorización tras N victorias contra nivel superior ──────────

    public function test_recategorizacion_aparece_despues_de_n_victorias_contra_nivel_superior(): void
    {
        $cap   = $this->makeUser(['play_level' => 'recreativo']);
        $club  = $this->makeClub($cap, 'Recreativo FC', 'recreativo');

        // Gana RECATEGORIZATION_WINS amistosos contra rivales de nivel superior.
        for ($i = 0; $i < SuggestionService::RECATEGORIZATION_WINS; $i++) {
            $oppCap = $this->makeUser(['play_level' => 'intermedio']);
            $opp    = $this->makeClub($oppCap, "Sup {$i} FC", 'intermedio');
            $this->makeJugadoFriendly($club, $opp, 3, 1); // gana como local
        }

        $suggestion = $this->service()->levelRecategorization($club->fresh());

        $this->assertNotNull($suggestion);
        $this->assertSame('recreativo', $suggestion['current_level']);
        $this->assertSame('intermedio', $suggestion['suggested_level']);
        $this->assertSame(SuggestionService::RECATEGORIZATION_WINS, $suggestion['wins']);
    }

    public function test_recategorizacion_no_aparece_con_pocas_victorias_ni_contra_mismo_nivel(): void
    {
        $cap  = $this->makeUser(['play_level' => 'recreativo']);
        $club = $this->makeClub($cap, 'Recreativo FC', 'recreativo');

        // Una sola victoria contra nivel superior (por debajo del umbral).
        $sup = $this->makeClub($this->makeUser(['play_level' => 'intermedio']), 'Sup FC', 'intermedio');
        $this->makeJugadoFriendly($club, $sup, 2, 0);

        // Victorias contra el MISMO nivel no cuentan.
        $same1 = $this->makeClub($this->makeUser(['play_level' => 'recreativo']), 'Same1 FC', 'recreativo');
        $same2 = $this->makeClub($this->makeUser(['play_level' => 'recreativo']), 'Same2 FC', 'recreativo');
        $this->makeJugadoFriendly($club, $same1, 5, 0);
        $this->makeJugadoFriendly($club, $same2, 5, 0);

        $this->assertNull($this->service()->levelRecategorization($club->fresh()));
    }

    public function test_recategorizacion_se_oculta_si_el_capitan_la_ignora(): void
    {
        $cap  = $this->makeUser(['play_level' => 'recreativo']);
        $club = $this->makeClub($cap, 'Recreativo FC', 'recreativo');

        for ($i = 0; $i < SuggestionService::RECATEGORIZATION_WINS; $i++) {
            $opp = $this->makeClub($this->makeUser(['play_level' => 'competitivo']), "Sup {$i} FC", 'competitivo');
            $this->makeJugadoFriendly($club, $opp, 4, 2);
        }

        $this->assertNotNull($this->service()->levelRecategorization($club->fresh()));

        // El capitán ignora la sugerencia (ruta).
        $this->actingAs($cap)
            ->post(route('torneos.clubes.level-suggestion.dismiss', $club))
            ->assertSessionHas('status');

        $this->assertNotNull($club->fresh()->level_suggestion_dismissed_at);
        $this->assertNull($this->service()->levelRecategorization($club->fresh()));
    }

    // ── 4. Modo rápido vence en el plazo correcto ───────────────────────────

    public function test_modo_rapido_vence_en_el_plazo_correcto(): void
    {
        Carbon::setTestNow(now());

        $cap  = $this->makeUser(['city' => 'Asunción', 'play_level' => 'intermedio']);
        $club = $this->makeClub($cap, 'Express FC', 'intermedio');

        $matchAt = now()->addHours(24);

        $opportunity = app(OpportunityService::class)->publish($cap, [
            'type'           => Opportunity::TYPE_BUSCAR_RIVAL,
            'city'           => 'Asunción',
            'required_level' => 'intermedio',
            'window_start'   => $matchAt,
            'club_id'        => $club->id,
            'is_express'     => true,
            'payload'        => [],
        ]);

        $this->assertTrue($opportunity->isExpress());
        // La vigencia termina en la fecha del partido.
        $this->assertSame($matchAt->timestamp, $opportunity->expires_at->timestamp);

        // Antes del plazo: sigue abierta.
        $this->assertSame(0, app(OpportunityService::class)->expireDue());
        $this->assertTrue($opportunity->fresh()->isAbierta());

        // Pasado el plazo: vence automáticamente.
        Carbon::setTestNow(now()->addHours(25));
        $this->assertSame(1, app(OpportunityService::class)->expireDue());
        $this->assertSame(Opportunity::STATUS_VENCIDA, $opportunity->fresh()->status);

        Carbon::setTestNow();
    }

    public function test_modo_rapido_via_controller_marca_express_y_muestra_badge(): void
    {
        $cap  = $this->makeUser(['city' => 'Asunción', 'play_level' => 'intermedio']);
        $club = $this->makeClub($cap, 'Express FC', 'intermedio');

        $this->actingAs($cap)->post(route('social.oportunidades.store'), [
            'type'           => Opportunity::TYPE_BUSCAR_RIVAL,
            'city'           => 'Asunción',
            'required_level' => 'intermedio',
            'club_id'        => $club->id,
            'window_start'   => now()->addDay()->format('Y-m-d\TH:i'),
            'is_express'     => '1',
        ])->assertRedirect();

        $opportunity = Opportunity::where('club_id', $club->id)->first();
        $this->assertNotNull($opportunity);
        $this->assertTrue($opportunity->isExpress());

        // El listado público muestra el badge de urgencia.
        $this->get(route('social.oportunidades.index', ['nivel' => 'todos']))
            ->assertOk()
            ->assertSee('Urgente');
    }

    // ── 5. Historial de compatibilidad (head-to-head) ───────────────────────

    public function test_head_to_head_cuenta_amistosos_entre_dos_clubs(): void
    {
        $a = $this->makeClub($this->makeUser(), 'A FC', 'intermedio');
        $b = $this->makeClub($this->makeUser(), 'B FC', 'intermedio');
        $c = $this->makeClub($this->makeUser(), 'C FC', 'intermedio');

        $this->makeJugadoFriendly($a, $b, 1, 0); // A gana
        $this->makeJugadoFriendly($b, $a, 2, 2); // empate (b local)
        $this->makeJugadoFriendly($a, $c, 0, 1); // contra otro rival

        $friendlyService = app(\App\Services\Social\FriendlyMatchService::class);

        $this->assertSame(2, $friendlyService->headToHeadCount($a->id, $b->id));

        $h2h = $friendlyService->clubHeadToHead($a);
        $vsB = $h2h->firstWhere('opponent.id', $b->id);

        $this->assertNotNull($vsB);
        $this->assertSame(2, $vsB->count);
        $this->assertSame(1, $vsB->won);
        $this->assertSame(1, $vsB->drawn);
    }
}
