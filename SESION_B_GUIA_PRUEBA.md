# Sesión B — Guía de prueba en el navegador

Perfil **permanente** del jugador y del club + **foto de perfil** + acumulado histórico.

App local: **http://futgo.test:8080**.

> PATH de Laragon antes de cualquier `php artisan`:
> ```powershell
> $env:Path = "C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64;C:\laragon\bin\composer;C:\laragon\bin\mysql\mysql-8.0.30-winx64\bin;$env:Path"
> ```
> Asegurate del symlink de storage (ya creado en esta sesión): `php artisan storage:link`.

---

## 0. Datos previos

La demo (`php artisan db:seed --class=DemoTournamentSeeder`) ya deja:
- Clubes permanentes (uno por equipo), `club_id` en cada inscripción.
- `player_career_stats` consolidado para los 144 jugadores.

**Usuarios** (contraseña `Demo2026!`): `ldn.capitan@demo.futgo.com`, `tig.capitan@demo.futgo.com`, `ldn.j1@demo.futgo.com`, etc.
Admin de torneo: `admin.torneo@demo.futgo.com`.

Para los escenarios 2 y 3 (trayectoria CROSS-torneo) se arma un segundo torneo donde el mismo jugador/club vuelve a participar. Pegá esto en `php artisan tinker`:

```php
use App\Models\User;
use App\Models\Torneos\{Tournament, Team, TeamPlayer, PlayerStat, Club};
use App\Services\Torneos\PlayerCareerStatsService;

$cap   = User::where('email', 'ldn.capitan@demo.futgo.com')->firstOrFail();
$admin = User::where('email', 'admin.torneo@demo.futgo.com')->firstOrFail();
$club  = Club::where('slug', 'leones-del-norte')->firstOrFail();   // club permanente de Leones

// Segundo torneo donde Leones del Norte vuelve a competir.
$t2 = Tournament::create([
    'name' => 'Copa Invierno 2026', 'slug' => 'copa-invierno-2026', 'sport' => 'futbol',
    'status' => 'in_progress', 'format' => 'groups_and_knockout',
    'groups_count' => 2, 'teams_per_group' => 4, 'classifies_per_group' => 2,
    'created_by_user_id' => $admin->id,
]);
$t2->tournamentAdmins()->create(['user_id' => $admin->id]);

// Reinscripción del MISMO club (misma identidad permanente).
$team2 = Team::create([
    'tournament_id' => $t2->id, 'club_id' => $club->id, 'captain_user_id' => $cap->id,
    'name' => 'Leones del Norte', 'status' => 'approved',
]);
$tp = TeamPlayer::create(['team_id' => $team2->id, 'user_id' => $cap->id, 'is_captain' => true, 'status' => 'active']);

// Stats del capitán en el 2º torneo.
PlayerStat::create([
    'tournament_id' => $t2->id, 'team_player_id' => $tp->id,
    'goals' => 3, 'assists' => 1, 'matches_played' => 4, 'minutes_played' => 360, 'mvps' => 1,
    'wins' => 3, 'draws' => 0, 'losses' => 1, 'clean_sheets' => 2,
]);

// Consolidar el histórico del capitán.
app(PlayerCareerStatsService::class)->refreshForUser($cap);
echo 'Club ID: ' . $club->id . PHP_EOL;
```

---

## Escenario 1 — Subir foto de perfil

1. Login como `ldn.capitan@demo.futgo.com` / `Demo2026!`.
2. Ir a **Mi perfil** (`http://futgo.test:8080/perfil`).
3. En **Foto de perfil**, elegir una imagen JPG/PNG/WEBP (< 2 MB) y **Subir foto**.
4. ✅ Esperado: la foto aparece de inmediato en el encabezado del perfil. También se ve en el avatar de las vistas de jugador (p. ej. su perfil de estadísticas y en "Mi Carrera").
   - Probar un archivo inválido (PDF o imagen > 2 MB) → mensaje de error en español, no se guarda.

---

## Escenario 2 — Acumulado del jugador en dos torneos

1. Como `ldn.capitan@demo.futgo.com`, ir a **Mi Carrera** (menú) → `http://futgo.test:8080/torneos/mi-carrera`.
2. ✅ Esperado: el bloque **Acumulado histórico** suma las stats de la Copa Demo **+** Copa Invierno (p. ej. goles del torneo 1 + 3 goles del torneo 2). "Mis torneos" lista **ambos** torneos; "Mi historial" muestra una fila por torneo con su desglose.

---

## Escenario 3 — Perfil de club con historial de participaciones

1. Visitar el perfil del club (usá el `Club ID` que imprimió tinker):
   `http://futgo.test:8080/torneos/clubes/{ID}`
2. ✅ Esperado: escudo + **Historial de participaciones** con **Copa FutGO Demo 2026** y **Copa Invierno 2026**, estadísticas acumuladas (PJ/PG/PE/PP/GF/GC sumando ambos torneos), goleadores históricos y jugadores históricos.
3. Como creador del club (el capitán), aparece el formulario **Subir escudo**: subí una imagen y verificá que reemplaza el escudo.

---

## Escenario 4 — Finalizar un torneo y conservar el histórico

1. Login como `admin.torneo@demo.futgo.com`.
2. Abrir la gestión de **Copa Invierno 2026** y avanzar su estado hasta **finished** (botón de cambio de estado en el dashboard del torneo), o por tinker:
   ```php
   $t2 = App\Models\Torneos\Tournament::where('slug','copa-invierno-2026')->first();
   // (debe estar en in_progress) avanzar a finished dispara la consolidación:
   $t2->update(['status' => 'finished']);
   app(App\Services\Torneos\PlayerCareerStatsService::class)->refreshForTournament($t2);
   ```
3. ✅ Esperado: las `player_stats` del torneo **NO** se borran y el acumulado del jugador en **Mi Carrera** se mantiene. Nada de historia se pierde al finalizar.

---

## Queries SQL de verificación

```sql
-- Clubes y cuántas inscripciones (torneos) tiene cada uno
SELECT c.id, c.name, COUNT(t.id) AS inscripciones
FROM clubs c LEFT JOIN teams t ON t.club_id = c.id
GROUP BY c.id ORDER BY inscripciones DESC;

-- Acumulado histórico del capitán de Leones
SELECT u.name, pcs.*
FROM player_career_stats pcs JOIN users u ON u.id = pcs.user_id
WHERE u.email = 'ldn.capitan@demo.futgo.com';

-- Comprobar que el acumulado = suma de player_stats del usuario
SELECT SUM(ps.goals) AS goles_sumados
FROM player_stats ps
JOIN team_players tp ON tp.id = ps.team_player_id
JOIN users u ON u.id = tp.user_id
WHERE u.email = 'ldn.capitan@demo.futgo.com';

-- Foto de perfil guardada
SELECT name, avatar_url FROM users WHERE avatar_url IS NOT NULL;

-- MVP por partido (si se cargó figura del partido)
SELECT id, match_number, mvp_team_player_id FROM tournament_matches WHERE mvp_team_player_id IS NOT NULL;
```

---

## Limpieza (opcional)

```php
use App\Models\Torneos\Tournament;
Tournament::where('slug','copa-invierno-2026')->get()->each->delete();
// El club Leones se conserva (sigue ligado a la Copa Demo).
```

---

## Errores conocidos / limitaciones

- **Captura de MVP en la planilla**: el modelo soporta `tournament_matches.mvp_team_player_id` y se agrega al histórico (`player_stats.mvps` → `player_career_stats.mvps`). El selector de MVP en el formulario de planilla queda como mejora de UI (hoy se puede setear vía datos/tinker); el pipeline ya funciona end-to-end.
- **Stats acumuladas del club** se calculan en lectura sobre los partidos finalizados de todas sus inscripciones (no hay tabla agregada de club). Es eficiente a escala de torneos amateur; si crece, conviene un `club_career_stats` análogo al del jugador.
- **Reclamo de perfil** del jugador `por_verificar` (vincular su `user_id`) sigue pendiente de UI (venía de la Sesión A).
- **Fusión de clubes**: el backfill creó un club por (capitán + nombre). Dos capitanes distintos con el mismo nombre de equipo son clubes distintos (correcto). No hay merge manual de clubes todavía.
- La foto/escudo se guardan en el disco `public` (`storage/app/public`), servidas vía el symlink `public/storage`. En producción con almacenamiento efímero conviene S3.
