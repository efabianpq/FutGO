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
 * Mundo: comunidad de fútbol amateur colombiano (Bucaramanga como epicentro,
 * con presencia en Medellín, Bogotá, Cali y Barranquilla).
 */
final class DemoData
{
    /** Contraseña común a TODAS las cuentas demo (acceso fácil para la demo). */
    public const PASSWORD = 'password';

    // ── Cuentas documentadas (las que aparecen en el informe de la demo) ──────
    public const ADMIN_EMAIL       = 'admin@futgo.co';
    public const ARBITRO_EMAIL     = 'arbitro@futgo.co';
    public const ORGANIZADOR_EMAIL = 'organizador@futgo.co';     // torneo_admin + capitán Los Cóndores
    public const HALCONES_EMAIL    = 'capitan.halcones@futgo.co'; // capitán del campeón
    public const ESTRELLA_EMAIL    = 'jugador.estrella@futgo.co'; // carrera más rica
    public const LIBRE_EMAIL       = 'libre@futgo.co';            // jugador libre con oportunidad activa

    /**
     * Equipos permanentes. El capitán de cada club se crea con el email indicado;
     * `role` distingue a los 3 torneo_admins que también juegan.
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
            'captain' => ['name' => 'Mauricio Ortiz', 'email' => self::ORGANIZADOR_EMAIL, 'role' => 'torneo_admin'],
        ],
        [
            'slug' => 'deportivo-cafe', 'name' => 'Deportivo Café', 'city' => 'Bucaramanga',
            'level' => 'recreativo', 'color' => '#15803D',
            'captain' => ['name' => 'Hernán Cárdenas', 'email' => 'hernan.cardenas@futgo.co', 'role' => 'torneo_admin'],
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
            'captain' => ['name' => 'Gustavo Henao', 'email' => 'gustavo.henao@futgo.co', 'role' => 'torneo_admin'],
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
