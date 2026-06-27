<?php

namespace Database\Seeders\Demo;

use App\Models\Torneos\Club;
use App\Models\Torneos\ClubPlayer;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * BLOQUE 1 — El mundo base: personas y equipos permanentes.
 *
 * Crea las cuentas documentadas, ~10 capitanes, jugadores registrados, jugadores
 * libres y 3 jugadores `por_verificar` (sin cuenta) por club. Cada club queda con
 * una plantilla permanente creíble de 14 jugadores.
 */
class DemoUsersAndClubsSeeder extends Seeder
{
    /** Punteros del generador de nombres (deterministas, sin repetir). */
    private int $firstIdx = 0;
    private int $lastIdx = 0;

    /** Documentos ya asignados (para no repetir). */
    private int $docCounter = 0;

    /** Emails usados (dedupe). */
    private array $usedEmails = [];

    /** Contador global de usuarios (para variar notifications/documentos). */
    private int $userCounter = 0;

    /** Documento del jugador `por_verificar` reclamable (demo de reclamo de perfil). */
    private ?string $claimableDocument = null;
    private ?string $claimableName = null;

    public function run(): void
    {
        $this->command?->info('   👤 Cuentas de plataforma (admin, árbitro)...');
        $this->createPlatformAccounts();

        $this->command?->info('   ⚽ 10 equipos permanentes con plantilla completa...');
        foreach (DemoData::CLUBS as $spec) {
            $this->createClub($spec);
        }

        $this->command?->info('   🧍 Jugadores libres (buscando equipo)...');
        $this->createFreeAgents();
    }

    // ── Cuentas de plataforma ───────────────────────────────────────────────

    private function createPlatformAccounts(): void
    {
        $this->makeUser('Administrador FutGO', DemoData::ADMIN_EMAIL, [
            'role' => 'admin', 'modules' => 'full', 'document' => $this->nextDocument(),
        ]);

        $this->makeUser('Andrés Rojas', DemoData::ARBITRO_EMAIL, [
            'role' => 'user', 'modules' => 'torneos', 'city' => 'Bucaramanga',
            'document' => $this->nextDocument(),
        ]);
    }

    // ── Clubs + plantilla ───────────────────────────────────────────────────

    private function createClub(array $spec): void
    {
        $isHalcones = $spec['slug'] === 'halcones-fc';

        // 1. Capitán (cuenta documentada).
        $captain = $this->makeUser($spec['captain']['name'], $spec['captain']['email'], [
            'role'       => $spec['captain']['role'],
            'modules'    => 'torneos',
            'city'       => $spec['city'],
            'play_level' => $spec['level'],
            'document'   => $this->nextDocument(),
        ]);

        // 2. Club permanente (creado por su propio capitán → validado).
        $club = Club::create([
            'name'               => $spec['name'],
            'slug'               => $spec['slug'],
            'color'              => $spec['color'],
            'play_level'         => $spec['level'],
            'created_by_user_id' => $captain->id,
            'captain_user_id'    => $captain->id,
            'status'             => 'validado',
        ]);

        // 3. Capitán como primer miembro de la plantilla permanente.
        $jersey = 1;
        ClubPlayer::create($this->clubPlayerAttrs($club, $captain, true, '10', 'Mediocampista central'));

        // 4. Jugadores registrados de campo.
        //    Halcones (el showcase / campeón) lleva más cuentas registradas.
        $registeredCount = $isHalcones ? 8 : 5;

        for ($i = 0; $i < $registeredCount; $i++) {
            // El primer registrado de Halcones es el jugador estrella.
            if ($isHalcones && $i === 0) {
                $player = $this->makeUser('Andrés Suárez', DemoData::ESTRELLA_EMAIL, [
                    'role' => 'user', 'modules' => 'torneos',
                    'city' => $spec['city'], 'play_level' => $spec['level'],
                    'document' => $this->nextDocument(),
                ]);
            } else {
                $name = $this->nextName();
                $player = $this->makeUser($name, $this->emailFor($name), [
                    'role' => 'user', 'modules' => 'torneos',
                    'city' => $spec['city'], 'play_level' => $spec['level'],
                    // ~80% carga su documento (cédula); el resto lo deja en blanco.
                    'document' => ($i % 5 === 4) ? null : $this->nextDocument(),
                ]);
            }

            // El jugador estrella es delantero y usa el dorsal 9.
            $position = ($isHalcones && $i === 0) ? 'Delantero' : DemoData::POSITIONS[$i % count(DemoData::POSITIONS)];
            $number   = ($isHalcones && $i === 0) ? '9' : (string) (++$jersey);

            ClubPlayer::create($this->clubPlayerAttrs($club, $player, false, $number, $position));
        }

        // 5. Jugadores `por_verificar` (sin cuenta) — informales anotados por el capitán.
        $unregisteredCount = $isHalcones ? 5 : 8;
        $jersey = 20;

        for ($i = 0; $i < $unregisteredCount; $i++) {
            $name = $this->nextName();
            $document = $this->nextDocument();

            // En Deportivo Café, el primer informal queda RECLAMABLE: su documento
            // coincidirá con el de un jugador libre (demo de reclamo de perfil, §12).
            if ($spec['slug'] === 'deportivo-cafe' && $i === 0) {
                $name = 'Fredy Agudelo';
                $this->claimableDocument = $document;
                $this->claimableName = $name;
            }

            ClubPlayer::create([
                'club_id'             => $club->id,
                'user_id'             => null,
                'is_captain'          => false,
                'full_name'           => $name,
                'document'            => $document,
                'verification_status' => 'por_verificar',
                'jersey_number'       => (string) (++$jersey),
                'position'            => DemoData::POSITIONS[$i % count(DemoData::POSITIONS)],
                'status'              => 'active',
            ]);
        }
    }

    // ── Jugadores libres ──────────────────────────────────────────────────────

    private function createFreeAgents(): void
    {
        // 1. libre@futgo.co — publicará una BUSCAR_EQUIPO activa (en DemoSocial).
        $this->makeUser('Brayan Lerma', DemoData::LIBRE_EMAIL, [
            'role' => 'user', 'modules' => 'torneos',
            'city' => 'Bucaramanga', 'play_level' => 'intermedio',
            'document' => $this->nextDocument(),
        ]);

        // 2. Fredy Agudelo — su documento coincide con un `por_verificar` de
        //    Deportivo Café: al iniciar sesión verá el banner de reclamo de perfil.
        $this->makeUser($this->claimableName ?? 'Fredy Agudelo', 'fredy.agudelo@futgo.co', [
            'role' => 'user', 'modules' => 'torneos',
            'city' => 'Bucaramanga', 'play_level' => 'intermedio',
            'document' => $this->claimableDocument ?? $this->nextDocument(),
        ]);

        // 3. Marlon Bacca — jugador libre con una BUSCAR_EQUIPO ya vencida.
        $this->makeUser('Marlon Bacca', 'marlon.bacca@futgo.co', [
            'role' => 'user', 'modules' => 'torneos',
            'city' => 'Barranquilla', 'play_level' => 'competitivo',
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /** Crea (o actualiza) un usuario demo con valores por defecto sensatos. */
    private function makeUser(string $name, string $email, array $attrs = []): User
    {
        $this->userCounter++;
        $this->usedEmails[$email] = true; // reserva el email (evita colisión con nombres generados)

        // notifications: la mayoría activadas; ~1 de cada 11 desactivada.
        $notifications = ($this->userCounter % 11) !== 0;

        return User::updateOrCreate(
            ['email' => $email],
            array_merge([
                'name'                  => $name,
                'password'              => Hash::make(DemoData::PASSWORD),
                'role'                  => 'user',
                'modules'               => 'torneos',
                'is_active'             => true,
                'notifications_enabled' => $notifications,
                'email_verified_at'     => now(),
            ], $attrs)
        );
    }

    /** Atributos de un ClubPlayer registrado (con cuenta). */
    private function clubPlayerAttrs(Club $club, User $user, bool $isCaptain, string $jersey, string $position): array
    {
        return [
            'club_id'             => $club->id,
            'user_id'             => $user->id,
            'is_captain'          => $isCaptain,
            'full_name'           => null,
            'document'            => $user->document,
            'verification_status' => 'registrado',
            'jersey_number'       => $jersey,
            'position'            => $position,
            'status'              => 'active',
        ];
    }

    /** Siguiente nombre "Nombre Apellido" del pool, sin repetir combinación. */
    private function nextName(): string
    {
        $first = DemoData::FIRST_NAMES[$this->firstIdx % count(DemoData::FIRST_NAMES)];
        $last  = DemoData::LAST_NAMES[$this->lastIdx % count(DemoData::LAST_NAMES)];
        $this->firstIdx++;
        $this->lastIdx += 3; // desfasa para variar combinaciones

        return "{$first} {$last}";
    }

    /** Email único derivado del nombre. */
    private function emailFor(string $name): string
    {
        $base = \Illuminate\Support\Str::slug($name, '.');
        $email = "{$base}@futgo.co";
        $n = 1;
        while (isset($this->usedEmails[$email])) {
            $email = "{$base}{$n}@futgo.co";
            $n++;
        }
        $this->usedEmails[$email] = true;

        return $email;
    }

    /** Documento de cédula colombiano único (10 dígitos). */
    private function nextDocument(): string
    {
        $this->docCounter++;

        return (string) (1090000000 + $this->docCounter * 137);
    }
}
