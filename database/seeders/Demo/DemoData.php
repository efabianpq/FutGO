<?php

namespace Database\Seeders\Demo;

use App\Models\Torneos\Club;
use App\Models\User;

/**
 * Fuente única de verdad para el seeder de demostración de FutGO.
 *
 * Define los equipos permanentes (clubs), las cuentas de acceso documentadas y
 * los pools de nombres colombianos reales. Los demás seeders de demo NO se pasan
 * objetos entre sí: se ejecutan en orden y resuelven lo que necesitan por
 * `slug` (clubs) o `email` (usuarios) a través de los helpers de esta clase.
 *
 * Mundo: comunidad de fútbol amateur colombiano con presencia multi-ciudad —
 * Bucaramanga, Medellín, Bogotá (por localidad), Cali, Barranquilla y, con foco
 * especial, la Sabana de Bogotá (Chía, Cajicá, Zipaquirá, Tocancipá, Sopó, Funza)
 * a través de la liga escolar.
 */
final class DemoData
{
    /** Contraseña común a TODAS las cuentas demo (acceso fácil para la demo). */
    public const PASSWORD = 'password';

    // ── Cuentas documentadas (las que aparecen en el informe de la demo) ──────
    public const ADMIN_EMAIL       = 'admin@futgo.co';
    public const ARBITRO_EMAIL     = 'arbitro@futgo.co';
    public const ORGANIZADOR_EMAIL = 'organizador@futgo.co';     // creador de torneos + capitán Los Cóndores
    public const HALCONES_EMAIL    = 'capitan.halcones@futgo.co'; // capitán con historial fuerte (varios torneos + amistosos)
    public const ESTRELLA_EMAIL    = 'jugador.estrella@futgo.co'; // carrera más rica
    public const LIBRE_EMAIL       = 'libre@futgo.co';            // jugador libre con oportunidad activa
    public const SABANA_ORGANIZER_EMAIL = 'coordinadora.sabana@futgo.co'; // organizadora Liga Escolar Sabana (torneo prioritario para admins)

    /**
     * Equipos permanentes. El capitán de cada club se crea con el email indicado.
     * Todos los usuarios son `role='user'`; no hay rol de organizador diferenciado —
     * cualquier usuario puede crear y administrar torneos.
     *
     * @var array<int, array<string, mixed>>
     */
    public const CLUBS = [
        [
            'slug' => 'halcones-fc', 'name' => 'Halcones FC', 'city' => 'Bucaramanga',
            'level' => 'competitivo', 'color' => '#1D4ED8',
            'captain' => ['name' => 'Carlos Reyes', 'email' => self::HALCONES_EMAIL, 'role' => 'user'],
        ],
        [
            'slug' => 'los-condores', 'name' => 'Los Cóndores', 'city' => 'Bucaramanga',
            'level' => 'intermedio', 'color' => '#DC2626',
            'captain' => ['name' => 'Mauricio Ortiz', 'email' => self::ORGANIZADOR_EMAIL, 'role' => 'user'],
        ],
        [
            'slug' => 'deportivo-cafe', 'name' => 'Deportivo Café', 'city' => 'Bucaramanga',
            'level' => 'recreativo', 'color' => '#15803D',
            'captain' => ['name' => 'Hernán Cárdenas', 'email' => 'hernan.cardenas@futgo.co', 'role' => 'user'],
        ],
        [
            'slug' => 'atletico-guane', 'name' => 'Atlético Guane', 'city' => 'Bucaramanga',
            'level' => 'intermedio', 'color' => '#111827',
            'captain' => ['name' => 'Édinson Quintero', 'email' => 'edinson.quintero@futgo.co', 'role' => 'user'],
        ],
        [
            'slug' => 'tigres-del-norte', 'name' => 'Tigres del Norte', 'city' => 'Medellín',
            'level' => 'competitivo', 'color' => '#EA580C',
            'captain' => ['name' => 'Duván Restrepo', 'email' => 'duvan.restrepo@futgo.co', 'role' => 'user'],
        ],
        [
            'slug' => 'independiente-sur', 'name' => 'Independiente Sur', 'city' => 'Bogotá',
            'level' => 'intermedio', 'color' => '#B91C1C',
            'captain' => ['name' => 'Óscar Patiño', 'email' => 'oscar.patino@futgo.co', 'role' => 'user'],
        ],
        [
            'slug' => 'los-guaduales-fc', 'name' => 'Los Guaduales FC', 'city' => 'Cali',
            'level' => 'recreativo', 'color' => '#16A34A',
            'captain' => ['name' => 'Wílmer Mina', 'email' => 'wilmer.mina@futgo.co', 'role' => 'user'],
        ],
        [
            'slug' => 'palmira-united', 'name' => 'Palmira United', 'city' => 'Cali',
            'level' => 'intermedio', 'color' => '#2563EB',
            'captain' => ['name' => 'Yefferson Mosquera', 'email' => 'yefferson.mosquera@futgo.co', 'role' => 'user'],
        ],
        [
            'slug' => 'caribe-fc', 'name' => 'Caribe FC', 'city' => 'Barranquilla',
            'level' => 'competitivo', 'color' => '#F59E0B',
            'captain' => ['name' => 'Teófilo Barrios', 'email' => 'teofilo.barrios@futgo.co', 'role' => 'user'],
        ],
        [
            'slug' => 'academia-oro', 'name' => 'Academia Oro', 'city' => 'Medellín',
            'level' => 'elite_amateur', 'color' => '#CA8A04',
            'captain' => ['name' => 'Gustavo Henao', 'email' => 'gustavo.henao@futgo.co', 'role' => 'user'],
        ],

        // ── Medellín (torneo "Liga Medellín" — eliminatoria en curso) ───────────
        [
            'slug' => 'belen-fc', 'name' => 'Belén FC', 'city' => 'Medellín',
            'level' => 'intermedio', 'color' => '#0EA5E9',
            'captain' => ['name' => 'Camilo Zapata', 'email' => 'camilo.zapata@futgo.co', 'role' => 'user'],
        ],
        [
            'slug' => 'laureles-atletico', 'name' => 'Laureles Atlético', 'city' => 'Medellín',
            'level' => 'intermedio', 'color' => '#7C3AED',
            'captain' => ['name' => 'Sebastián Montoya', 'email' => 'sebastian.montoya@futgo.co', 'role' => 'user'],
        ],
        [
            'slug' => 'poblado-united', 'name' => 'Poblado United', 'city' => 'Medellín',
            'level' => 'competitivo', 'color' => '#059669',
            'captain' => ['name' => 'Nicolás Vélez', 'email' => 'nicolas.velez@futgo.co', 'role' => 'user'],
        ],
        [
            'slug' => 'itagui-fc', 'name' => 'Itagüí FC', 'city' => 'Medellín',
            'level' => 'intermedio', 'color' => '#DB2777',
            'captain' => ['name' => 'Andrés Zuluaga', 'email' => 'andres.zuluaga@futgo.co', 'role' => 'user'],
        ],
        [
            'slug' => 'envigado-popular', 'name' => 'Envigado Popular', 'city' => 'Medellín',
            'level' => 'recreativo', 'color' => '#EAB308',
            'captain' => ['name' => 'Jorge Ramírez', 'email' => 'jorge.ramirez.env@futgo.co', 'role' => 'user'],
        ],
        [
            'slug' => 'bello-fc', 'name' => 'Bello FC', 'city' => 'Medellín',
            'level' => 'recreativo', 'color' => '#6B7280',
            'captain' => ['name' => 'Cristian Higuita', 'email' => 'cristian.higuita@futgo.co', 'role' => 'user'],
        ],

        // ── Bogotá por localidad (torneo "Liga Barrial Bogotá" — finalizado) ────
        [
            'slug' => 'chapinero-fc', 'name' => 'Chapinero FC', 'city' => 'Bogotá',
            'level' => 'competitivo', 'color' => '#DC2626',
            'captain' => ['name' => 'Felipe Cruz', 'email' => 'felipe.cruz@futgo.co', 'role' => 'user'],
        ],
        [
            'slug' => 'suba-fc', 'name' => 'Suba FC', 'city' => 'Bogotá',
            'level' => 'intermedio', 'color' => '#2563EB',
            'captain' => ['name' => 'Diego Fonseca', 'email' => 'diego.fonseca@futgo.co', 'role' => 'user'],
        ],
        [
            'slug' => 'bosa-atletico', 'name' => 'Bosa Atlético', 'city' => 'Bogotá',
            'level' => 'recreativo', 'color' => '#16A34A',
            'captain' => ['name' => 'Harold Niño', 'email' => 'harold.nino@futgo.co', 'role' => 'user'],
        ],
        [
            'slug' => 'kennedy-united', 'name' => 'Kennedy United', 'city' => 'Bogotá',
            'level' => 'intermedio', 'color' => '#F59E0B',
            'captain' => ['name' => 'Yeison Cubillos', 'email' => 'yeison.cubillos@futgo.co', 'role' => 'user'],
        ],

        // ── Liga Escolar Sabana Sub-13 (torneo "vivo", visibility pública) ──────
        [
            'slug' => 'colegio-san-rafael-chia', 'name' => 'Colegio San Rafael Chía', 'city' => 'Chía',
            'level' => 'recreativo', 'color' => '#1E3A8A',
            'captain' => ['name' => 'Álvaro Beltrán', 'email' => 'alvaro.beltran@futgo.co', 'role' => 'user'],
        ],
        [
            'slug' => 'gimnasio-campestre-cajica', 'name' => 'Gimnasio Campestre Cajicá', 'city' => 'Cajicá',
            'level' => 'intermedio', 'color' => '#B91C1C',
            'captain' => ['name' => 'Leonardo Suárez', 'email' => 'leonardo.suarez.caj@futgo.co', 'role' => 'user'],
        ],
        [
            'slug' => 'liceo-sabana-zipaquira', 'name' => 'Liceo La Sabana Zipaquirá', 'city' => 'Zipaquirá',
            'level' => 'recreativo', 'color' => '#78350F',
            'captain' => ['name' => 'Pablo Cárdenas', 'email' => 'pablo.cardenas.zip@futgo.co', 'role' => 'user'],
        ],
        [
            'slug' => 'instituto-tocancipa', 'name' => 'Instituto Tocancipá', 'city' => 'Tocancipá',
            'level' => 'intermedio', 'color' => '#059669',
            'captain' => ['name' => 'Raúl Bermúdez', 'email' => 'raul.bermudez@futgo.co', 'role' => 'user'],
        ],
        [
            'slug' => 'escuela-futbol-sopo', 'name' => 'Escuela de Fútbol Sopó', 'city' => 'Sopó',
            'level' => 'recreativo', 'color' => '#7C2D12',
            'captain' => ['name' => 'Javier Ospina', 'email' => 'javier.ospina@futgo.co', 'role' => 'user'],
        ],
        [
            'slug' => 'real-funza-fc', 'name' => 'Real Funza FC', 'city' => 'Funza',
            'level' => 'intermedio', 'color' => '#4C1D95',
            'captain' => ['name' => 'Orlando Caicedo', 'email' => 'orlando.caicedo@futgo.co', 'role' => 'user'],
        ],
    ];

    /** Nombres de pila colombianos para generar plantillas creíbles. */
    public const FIRST_NAMES = [
        'Carlos', 'Andrés', 'Mauricio', 'Juan', 'Santiago', 'Sebastián', 'Felipe', 'David',
        'Daniel', 'Camilo', 'Nicolás', 'Julián', 'Diego', 'Mateo', 'Esteban', 'Jorge',
        'Luis', 'Miguel', 'Fernando', 'Ricardo', 'Óscar', 'Iván', 'Hernán', 'Cristian',
        'Édinson', 'Yefferson', 'Wílmer', 'Brayan', 'Duván', 'Fredy', 'Álvaro', 'Gustavo',
        'Javier', 'Leonardo', 'Marlon', 'Orlando', 'Pablo', 'Raúl', 'Harold', 'Yeison',
    ];

    /** Apellidos colombianos. */
    public const LAST_NAMES = [
        'Reyes', 'Suárez', 'Ortiz', 'Rodríguez', 'Gómez', 'Martínez', 'Quintero', 'Hernández',
        'Cárdenas', 'Mejía', 'Valencia', 'Rincón', 'Castaño', 'Moreno', 'Ramírez', 'Torres',
        'Vargas', 'Patiño', 'Mosquera', 'Arango', 'Zapata', 'Rojas', 'Mina', 'Lerma',
        'Borja', 'Barrios', 'Murillo', 'Sánchez', 'Gutiérrez', 'Pérez', 'Díaz', 'Restrepo',
        'Cardona', 'Agudelo', 'Henao', 'Montoya', 'Salazar', 'Bermúdez', 'Ospina', 'Caicedo',
    ];

    /** Posiciones cicladas para armar una plantilla de fútbol 11 + suplentes. */
    public const POSITIONS = [
        'Portero', 'Defensa central', 'Defensa central', 'Lateral derecho', 'Lateral izquierdo',
        'Mediocampista central', 'Mediocampista central', 'Volante de marca', 'Volante creativo',
        'Extremo', 'Delantero', 'Delantero', 'Extremo', 'Portero',
    ];

    // ── Lookups ───────────────────────────────────────────────────────────────

    public static function club(string $slug): Club
    {
        return Club::where('slug', $slug)->firstOrFail();
    }

    public static function user(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    /** Clubs en el orden de declaración (Halcones primero). */
    public static function clubsInOrder(): \Illuminate\Support\Collection
    {
        return collect(self::CLUBS)->map(fn ($spec) => self::club($spec['slug']));
    }

    public static function captainOf(string $slug): User
    {
        return self::club($slug)->captain;
    }

    /** Especificación cruda del club por slug. */
    public static function spec(string $slug): array
    {
        foreach (self::CLUBS as $spec) {
            if ($spec['slug'] === $slug) {
                return $spec;
            }
        }
        throw new \InvalidArgumentException("Club demo desconocido: {$slug}");
    }
}
