<?php

namespace Database\Seeders\Demo;

use App\Models\Social\Conversation;
use App\Models\Social\FeedEvent;
use App\Models\Social\FriendlyMatch;
use App\Models\Social\Opportunity;
use App\Models\Social\Venue;
use App\Models\Torneos\Club;
use App\Models\Torneos\Tournament;
use App\Models\User;
use App\Services\Social\ConversationService;
use App\Services\Social\FeedService;
use App\Services\Social\FollowService;
use App\Services\Social\FriendlyMatchService;
use App\Services\Social\OpportunityService;
use App\Services\Social\ReliabilityService;
use Carbon\Carbon;
use Database\Seeders\Demo\DemoData;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * BLOQUE 3 — FutGO Social.
 *
 * Usa los SERVICIOS REALES (OpportunityService, FriendlyMatchService,
 * ConversationService, FollowService, FeedService, ReliabilityService) para que
 * las oportunidades, amistosos, conversaciones, seguimientos, feed y confiabilidad
 * nazcan exactamente como en producción. Cada bloque está protegido por
 * Schema::hasTable() por si el módulo Social no estuviera migrado.
 */
class DemoSocialSeeder extends Seeder
{
    private OpportunityService $opportunities;
    private FriendlyMatchService $friendlies;
    private ConversationService $conversations;
    private FollowService $follows;
    private FeedService $feed;
    private ReliabilityService $reliability;

    /** Venues creados, por nombre corto. @var array<string,Venue> */
    private array $venues = [];

    public function run(): void
    {
        if (! Schema::hasTable('opportunities')) {
            $this->command?->warn('   ⚠️  Módulo Social no migrado — se omite DemoSocialSeeder.');
            return;
        }

        $this->opportunities = app(OpportunityService::class);
        $this->friendlies    = app(FriendlyMatchService::class);
        $this->conversations = app(ConversationService::class);
        $this->follows       = app(FollowService::class);
        $this->feed          = app(FeedService::class);
        $this->reliability   = app(ReliabilityService::class);

        if (Schema::hasTable('venues')) {
            $this->command?->info('   📍 Canchas (venues) compartidas...');
            $this->seedVenues();
        }

        $this->command?->info('   📣 Oportunidades (rival, jugador, refuerzo, equipo)...');
        $this->seedOpportunities();

        $this->command?->info('   🤝 Amistosos (jugado + confirmado)...');
        $this->seedFriendlies();

        if (Schema::hasTable('conversations')) {
            $this->command?->info('   💬 Conversaciones y mensajería...');
            $this->seedConversations();
        }

        if (Schema::hasTable('follows')) {
            $this->command?->info('   ➕ Seguimientos...');
            $this->seedFollows();
        }

        if (Schema::hasTable('reliability_events')) {
            $this->command?->info('   ⭐ Eventos de confiabilidad + scores...');
            $this->seedReliability();
        }

        if (Schema::hasTable('feed_events')) {
            $this->command?->info('   📰 Eventos destacados del feed...');
            $this->seedHeadlineFeed();
        }
    }

    // ── Venues ────────────────────────────────────────────────────────────────

    private function seedVenues(): void
    {
        $registrant = DemoData::user(DemoData::ORGANIZADOR_EMAIL)->id;

        $defs = [
            ['campin-norte', 'Cancha El Campín Norte', 'Bucaramanga', 'Cra 27 # 45-10', 'cesped_sintetico', 200],
            ['marte', 'Cancha Marte — La Concordia', 'Bucaramanga', 'Cl 9 # 20-15', 'cesped_natural', 500],
            ['la-floresta', 'Complejo La Floresta', 'Bucaramanga', 'Anillo Vial', 'cesped_sintetico', 150],
            ['la-flora', 'Cancha Sintética La Flora', 'Medellín', 'Cl 44 # 80-20', 'cesped_sintetico', 120],
            ['panamericano', 'Polideportivo Panamericano', 'Cali', 'Cl 5 # 50-30', 'cesped_natural', 800],
        ];

        foreach ($defs as [$key, $name, $city, $address, $surface, $cap]) {
            $this->venues[$key] = Venue::create([
                'name'                  => $name,
                'slug'                  => Str::slug($name),
                'city'                  => $city,
                'address'               => $address,
                'surface_type'          => $surface,
                'approx_capacity'       => $cap,
                'registered_by_user_id' => $registrant,
                'is_active'             => true,
            ]);
        }
    }

    private function venueId(string $key): ?int
    {
        return $this->venues[$key]->id ?? null;
    }

    // ── Oportunidades ──────────────────────────────────────────────────────────

    private function seedOpportunities(): void
    {
        // 1) BUSCAR_RIVAL — Atlético Guane (abierta): 1 respuesta pendiente + 1 rechazada.
        $guaneCaptain = DemoData::captainOf('atletico-guane');
        $rivalGuane = $this->opportunities->publish($guaneCaptain, [
            'type'           => Opportunity::TYPE_BUSCAR_RIVAL,
            'club_id'        => DemoData::club('atletico-guane')->id,
            'city'           => 'Bucaramanga',
            'required_level' => 'intermedio',
            'venue_id'       => $this->venueId('campin-norte'),
            'window_start'   => Carbon::now()->next(Carbon::SATURDAY)->setTime(10, 0),
            'window_end'     => Carbon::now()->next(Carbon::SATURDAY)->setTime(12, 0),
            'payload'        => ['cancha_propuesta' => 'Cancha El Campín Norte', 'hora_preferida' => '10:00 AM'],
        ]);
        $this->opportunities->respond($rivalGuane, DemoData::captainOf('los-condores'), [
            'club_id' => DemoData::club('los-condores')->id,
            'message' => 'Nos interesa, somos 12 seguros. ¿Confirmamos para el sábado?',
        ]);
        $rejected = $this->opportunities->respond($rivalGuane, DemoData::captainOf('deportivo-cafe'), [
            'club_id' => DemoData::club('deportivo-cafe')->id,
            'message' => 'Podemos ir, aunque somos un poco más recreativos.',
        ]);
        $this->opportunities->reject($rejected); // nivel diferente

        // 2) BUSCAR_RIVAL — Los Guaduales (Cali): aceptada → genera FriendlyMatch confirmado.
        $guadualesCaptain = DemoData::captainOf('los-guaduales-fc');
        $rivalGuaduales = $this->opportunities->publish($guadualesCaptain, [
            'type'           => Opportunity::TYPE_BUSCAR_RIVAL,
            'club_id'        => DemoData::club('los-guaduales-fc')->id,
            'city'           => 'Cali',
            'required_level' => 'recreativo',
            'venue_id'       => $this->venueId('panamericano'),
            'window_start'   => Carbon::now()->addDays(10)->setTime(9, 0),
            'window_end'     => Carbon::now()->addDays(10)->setTime(11, 0),
            'payload'        => ['cancha_propuesta' => 'Polideportivo Panamericano'],
        ]);
        $resp = $this->opportunities->respond($rivalGuaduales, DemoData::captainOf('palmira-united'), [
            'club_id' => DemoData::club('palmira-united')->id,
            'message' => '¡De once! Llevamos camiseta blanca.',
        ]);
        $this->opportunities->accept($resp);

        // 3) BUSCAR_JUGADOR — Halcones busca mediocampista (en negociación por una contrapropuesta).
        $halconesCaptain = DemoData::captainOf('halcones-fc');
        $jugadorHalcones = $this->opportunities->publish($halconesCaptain, [
            'type'           => Opportunity::TYPE_BUSCAR_JUGADOR,
            'club_id'        => DemoData::club('halcones-fc')->id,
            'city'           => 'Bucaramanga',
            'required_level' => 'intermedio',
            'window_start'   => Carbon::now()->addDays(7),
            'payload'        => ['posicion' => 'mediocampista central', 'cupos' => 1, 'requisito' => 'mínimo nivel intermedio'],
        ]);
        $this->opportunities->respond($jugadorHalcones, DemoData::user(DemoData::LIBRE_EMAIL), [
            'message' => 'Juego de volante central hace años, me interesa.',
        ]);
        $counter = $this->opportunities->respond($jugadorHalcones, DemoData::user('fredy.agudelo@futgo.co'), [
            'message' => 'Puedo jugar, aunque también me acomodo de lateral. ¿Lo charlamos?',
        ]);
        $this->opportunities->counter($counter, 'Puedo jugar de central o de lateral derecho, como necesiten.');

        // 4) BUSCAR_REFUERZO — Halcones necesita delantero para los cuartos de la Copa Élite.
        $refuerzo = $this->opportunities->publish($halconesCaptain, [
            'type'           => Opportunity::TYPE_BUSCAR_REFUERZO,
            'club_id'        => DemoData::club('halcones-fc')->id,
            'city'           => 'Bucaramanga',
            'required_level' => 'competitivo',
            'window_start'   => Carbon::now()->addDays(3),
            'payload'        => ['partido' => 'cuartos de final', 'posicion' => 'delantero', 'torneo' => 'Copa Élite Santander'],
        ]);
        $this->opportunities->respond($refuerzo, DemoData::user('marlon.bacca@futgo.co'), [
            'message' => 'Disponible para los cuartos, juego de 9.',
        ]);

        // 5) BUSCAR_EQUIPO — jugador libre disponible (sin respuestas todavía).
        $this->opportunities->publish(DemoData::user(DemoData::LIBRE_EMAIL), [
            'type'           => Opportunity::TYPE_BUSCAR_EQUIPO,
            'city'           => 'Bucaramanga',
            'required_level' => 'intermedio',
            'window_start'   => Carbon::now()->addDays(14),
            'payload'        => ['posicion' => 'volante de marca', 'disponibilidad' => 'fines de semana', 'nivel' => 'intermedio'],
        ]);

        // 6) BUSCAR_EQUIPO — otro jugador libre, oportunidad ya vencida.
        $expired = $this->opportunities->publish(DemoData::user('marlon.bacca@futgo.co'), [
            'type'           => Opportunity::TYPE_BUSCAR_EQUIPO,
            'city'           => 'Barranquilla',
            'required_level' => 'competitivo',
            'window_start'   => Carbon::now()->subDays(20),
            'window_end'     => Carbon::now()->subDays(13),
            'payload'        => ['posicion' => 'delantero', 'disponibilidad' => 'noches'],
        ]);
        $this->opportunities->expireDue(); // marca como vencida lo que pasó su vigencia

        // 7) BUSCAR_JUGADOR — Independiente Sur: aceptada (suma a un jugador a la plantilla).
        $indepCaptain = DemoData::captainOf('independiente-sur');
        $jugadorIndep = $this->opportunities->publish($indepCaptain, [
            'type'           => Opportunity::TYPE_BUSCAR_JUGADOR,
            'club_id'        => DemoData::club('independiente-sur')->id,
            'city'           => 'Bogotá',
            'required_level' => 'intermedio',
            'window_start'   => Carbon::now()->addDays(5),
            'payload'        => ['posicion' => 'lateral', 'cupos' => 1],
        ]);
        $respIndep = $this->opportunities->respond($jugadorIndep, DemoData::user('fredy.agudelo@futgo.co'), [
            'message' => 'Me interesa, soy lateral derecho.',
        ]);
        $this->opportunities->accept($respIndep);
    }

    // ── Amistosos ──────────────────────────────────────────────────────────────

    private function seedFriendlies(): void
    {
        // FM jugado: Halcones FC vs Tigres del Norte 2-1 (doble confirmación).
        $halcones = DemoData::club('halcones-fc');
        $tigres   = DemoData::club('tigres-del-norte');

        $fm = FriendlyMatch::create([
            'home_club_id' => $halcones->id,
            'away_club_id' => $tigres->id,
            'scheduled_at' => Carbon::now()->subWeeks(3)->setTime(16, 0),
            'location'     => 'Cancha Marte — La Concordia',
            'venue_id'     => $this->venueId('marte'),
            'status'       => FriendlyMatch::STATUS_CONFIRMADO,
        ]);
        // Ambos capitanes reportan el mismo marcador → queda jugado/acordado.
        $this->friendlies->reportResult($fm, $halcones, 2, 1);
        $this->friendlies->reportResult($fm->refresh(), $tigres, 2, 1);

        // FM confirmado (Los Guaduales vs Palmira) ya nació de la oportunidad
        // aceptada en seedOpportunities(); aquí solo lo dejamos documentado.
    }

    // ── Conversaciones ──────────────────────────────────────────────────────────

    private function seedConversations(): void
    {
        // 1) Coordinación del amistoso confirmado Guaduales ↔ Palmira.
        $guadualesFriendly = FriendlyMatch::where('home_club_id', DemoData::club('los-guaduales-fc')->id)
            ->where('away_club_id', DemoData::club('palmira-united')->id)
            ->latest('id')->first();

        if ($guadualesFriendly) {
            $conv = $this->conversations->ensureForFriendly($guadualesFriendly);
            $g = DemoData::captainOf('los-guaduales-fc');
            $p = DemoData::captainOf('palmira-united');
            $gc = DemoData::club('los-guaduales-fc')->id;
            $pc = DemoData::club('palmira-united')->id;
            $this->conversations->postMessage($conv, $p, '¡Listo! ¿Confirmamos el horario de las 9?', $pc);
            $this->conversations->postMessage($conv, $g, 'Sí, a las 9 en el Panamericano. ¿Llevan ustedes el balón?', $gc);
            $this->conversations->postMessage($conv, $p, 'Nosotros llevamos balón y petos. Jugamos a 90, ¿les parece?', $pc);
            $this->conversations->postMessage($conv, $g, 'Perfecto, confirmado entonces. Nos vemos el sábado.', $gc);
        }

        // 2) Post-partido del amistoso jugado Halcones ↔ Tigres.
        $halconesFriendly = FriendlyMatch::where('home_club_id', DemoData::club('halcones-fc')->id)
            ->where('away_club_id', DemoData::club('tigres-del-norte')->id)
            ->latest('id')->first();

        if ($halconesFriendly) {
            $conv = $this->conversations->ensureForFriendly($halconesFriendly);
            $h  = DemoData::captainOf('halcones-fc');
            $t  = DemoData::captainOf('tigres-del-norte');
            $hc = DemoData::club('halcones-fc')->id;
            $tc = DemoData::club('tigres-del-norte')->id;
            $this->conversations->postMessage($conv, $t, 'Gran partido, muchachos. Espero la revancha pronto.', $tc);
            $this->conversations->postMessage($conv, $h, 'De nuestro lado también. Quedamos 2-1 pero fue parejo.', $hc);
            $this->conversations->postMessage($conv, $t, 'Cuando quieran los esperamos en Medellín.', $tc);
        }

        // 3) Reclutamiento: conversación de la oportunidad BUSCAR_JUGADOR aceptada (Indep. Sur).
        $opp = Opportunity::where('type', Opportunity::TYPE_BUSCAR_JUGADOR)
            ->where('club_id', DemoData::club('independiente-sur')->id)->first();

        if ($opp) {
            $conv = Conversation::where('subject_type', 'opportunity')->where('subject_id', $opp->id)->first();
            if ($conv) {
                $cap = DemoData::captainOf('independiente-sur');
                $fredy = DemoData::user('fredy.agudelo@futgo.co');
                $this->conversations->postMessage($conv, $cap, 'Vi tu respuesta. ¿Puedes venir a un entrenamiento esta semana?');
                $this->conversations->postMessage($conv, $fredy, 'Claro, ¿qué día y a qué hora?');
                $this->conversations->postMessage($conv, $cap, 'El jueves a las 7 pm en la sede. Te paso ubicación.');
            }
        }
    }

    // ── Seguimientos ──────────────────────────────────────────────────────────

    private function seedFollows(): void
    {
        $halcones = DemoData::club('halcones-fc');
        $condores = DemoData::club('los-condores');
        $copa     = Tournament::where('slug', 'copa-elite-santander-2026')->first();
        $relam    = Tournament::where('slug', 'torneo-relampago-nocturno')->first();
        $empre    = Tournament::where('slug', 'torneo-empresarial-cafe-2026')->first();

        // 5 jugadores siguen a Halcones FC (el campeón tiene hinchada).
        foreach ($this->somePlayers(['caribe-fc', 'tigres-del-norte', 'academia-oro'], 5) as $u) {
            $this->follows->toggle($u, $halcones);
        }

        // 3 capitanes siguen la Copa Élite para ver resultados.
        if ($copa) {
            foreach (['caribe-fc', 'tigres-del-norte', 'academia-oro'] as $slug) {
                $this->follows->toggle(DemoData::captainOf($slug), $copa);
            }
        }

        // El jugador libre sigue a sus posibles destinos.
        $libre = DemoData::user(DemoData::LIBRE_EMAIL);
        $this->follows->toggle($libre, $halcones);
        $this->follows->toggle($libre, $condores);

        // El admin sigue los torneos activos.
        $admin = DemoData::user(DemoData::ADMIN_EMAIL);
        foreach ([$copa, $relam, $empre] as $t) {
            if ($t) {
                $this->follows->toggle($admin, $t);
            }
        }

        // Dos jugadores que jugaron juntos se siguen mutuamente.
        $estrella = DemoData::user(DemoData::ESTRELLA_EMAIL);
        $tigresCap = DemoData::captainOf('tigres-del-norte');
        $this->follows->toggle($estrella, $tigresCap);
        $this->follows->toggle($tigresCap, $estrella);
    }

    // ── Confiabilidad ──────────────────────────────────────────────────────────

    private function seedReliability(): void
    {
        // 3 no-shows: jugadores convocados que no se presentaron (sujetos distintos
        // para no disparar la pausa automática por 2 no-shows en 30 días).
        $noShowUsers = $this->somePlayers(['deportivo-cafe', 'caribe-fc', 'palmira-united'], 3);
        foreach ($noShowUsers as $i => $u) {
            $u->reliabilityEvents()->create([
                'type'        => 'no_show',
                'occurred_at' => Carbon::now()->subDays(35 + $i * 10),
                'meta'        => ['contexto' => 'Liga Recreativa 2025'],
            ]);
        }

        // 2 cancelaciones tardías (clubs distintos).
        foreach (['deportivo-cafe', 'independiente-sur'] as $i => $slug) {
            DemoData::club($slug)->reliabilityEvents()->create([
                'type'        => 'cancelacion_tardia',
                'occurred_at' => Carbon::now()->subDays(12 + $i * 5),
                'meta'        => ['contexto' => 'amistoso cancelado < 24h'],
            ]);
        }

        // 4 calificaciones positivas (capitanes que calificaron bien tras un amistoso).
        DemoData::club('halcones-fc')->reliabilityEvents()->create(['type' => 'calificacion_positiva', 'occurred_at' => Carbon::now()->subWeeks(3)]);
        DemoData::club('tigres-del-norte')->reliabilityEvents()->create(['type' => 'calificacion_positiva', 'occurred_at' => Carbon::now()->subWeeks(3)]);
        DemoData::club('los-guaduales-fc')->reliabilityEvents()->create(['type' => 'calificacion_positiva', 'occurred_at' => Carbon::now()->subWeeks(1)]);
        DemoData::user(DemoData::ESTRELLA_EMAIL)->reliabilityEvents()->create(['type' => 'calificacion_positiva', 'occurred_at' => Carbon::now()->subWeeks(2)]);

        // 1 calificación negativa (una mala experiencia documentada, sin llegar a pausa).
        $this->somePlayers(['academia-oro'], 1)->first()
            ?->reliabilityEvents()->create(['type' => 'calificacion_negativa', 'occurred_at' => Carbon::now()->subDays(8)]);

        // Recalcula los scores cacheados a partir de los eventos.
        $this->reliability->rebuild();
    }

    // ── Feed destacado ──────────────────────────────────────────────────────────

    private function seedHeadlineFeed(): void
    {
        // La mayoría del feed la generan los servicios (oportunidades, amistosos,
        // logros). Agregamos el titular del campeón, que normalmente emite el
        // controlador de resultados de torneo (que el seeder no atraviesa).
        $t = Tournament::where('slug', 'liga-recreativa-bucaramanga-2025')->first();
        if ($t) {
            $this->feed->record(FeedEvent::TYPE_RESULTADO_TORNEO, DemoData::club('halcones-fc'), $t, [
                'city'    => 'Bucaramanga',
                'payload' => [
                    'tournament_id' => $t->id,
                    'tournament'    => $t->name,
                    'champion'      => 'Halcones FC',
                    'headline'      => 'Halcones FC se coronó campeón de la Liga Recreativa Bucaramanga 2025.',
                ],
                'occurred_at' => Carbon::now()->subMonths(7),
            ]);
        }
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Devuelve hasta $n usuarios registrados (con cuenta, no capitanes) de los
     * clubs dados, en orden estable.
     */
    private function somePlayers(array $slugs, int $n): \Illuminate\Support\Collection
    {
        $users = collect();
        foreach ($slugs as $slug) {
            $club = DemoData::club($slug);
            $members = $club->players()
                ->whereNotNull('user_id')
                ->where('is_captain', false)
                ->with('user')
                ->get()
                ->map(fn ($cp) => $cp->user)
                ->filter();
            $users = $users->merge($members);
        }
        return $users->unique('id')->take($n)->values();
    }
}
