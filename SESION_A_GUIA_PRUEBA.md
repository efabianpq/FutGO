# Sesión A — Guía de prueba en el navegador

Unificación de identidad **jugador/capitán** + **jugadores no registrados**.

App local: **http://futgo.test:8080** (alternativa: `php artisan serve --port=8001`).

> Recordá prepender el PATH de Laragon antes de cualquier comando `php artisan`:
> ```powershell
> $env:Path = "C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64;C:\laragon\bin\composer;C:\laragon\bin\mysql\mysql-8.0.30-winx64\bin;$env:Path"
> ```

---

## 0. Usuarios y torneos necesarios (tinker)

Abrí tinker: `php artisan tinker` y pegá este bloque. Crea **un usuario de prueba** (será capitán) y **dos torneos en estado `open`** para poder inscribir equipos. Reutiliza el admin de torneo del seeder demo.

```php
use App\Models\User;
use App\Models\Torneos\Tournament;

// Usuario de prueba que será capitán en un torneo y jugador en otro.
$cap = User::updateOrCreate(
    ['email' => 'capitan.demo@futgo.test'],
    ['name' => 'Capitán Demo', 'password' => bcrypt('Demo2026!'),
     'role' => 'user', 'modules' => 'torneos', 'is_active' => true, 'email_verified_at' => now()]
);

// Segundo usuario: capitán del "otro equipo" donde nuestro capitán será jugador.
$otro = User::updateOrCreate(
    ['email' => 'otro.capitan@futgo.test'],
    ['name' => 'Otro Capitán', 'password' => bcrypt('Demo2026!'),
     'role' => 'user', 'modules' => 'torneos', 'is_active' => true, 'email_verified_at' => now()]
);

// Admin de torneo (del seeder demo) para administrar los torneos de prueba.
$admin = User::where('email', 'admin.torneo@demo.futgo.com')->firstOrFail();

// Dos torneos ABIERTOS (status=open) para inscribir equipos.
foreach (['Liga Prueba A', 'Liga Prueba B'] as $name) {
    $t = Tournament::updateOrCreate(
        ['slug' => \Illuminate\Support\Str::slug($name)],
        ['name' => $name, 'sport' => 'futbol', 'status' => 'open',
         'format' => 'groups_and_knockout', 'groups_count' => 2, 'teams_per_group' => 4,
         'classifies_per_group' => 2, 'max_teams' => 8, 'visibility' => 'public',
         'created_by_user_id' => $admin->id]
    );
    $t->tournamentAdmins()->firstOrCreate(['user_id' => $admin->id]);
    echo $t->name . ' => ' . $t->slug . PHP_EOL;
}
```

**Credenciales de login:**
- Capitán de prueba: `capitan.demo@futgo.test` / `Demo2026!`
- Otro capitán: `otro.capitan@futgo.test` / `Demo2026!`

---

## Escenario 1 — Crear un equipo y volverse capitán

1. Iniciá sesión como **`capitan.demo@futgo.test`**.
2. Andá a **Mis Torneos** (menú superior) → entrá al hub de **Liga Prueba A**.
   - Ruta directa: `/torneos/{slug}` → `http://futgo.test:8080/torneos/liga-prueba-a`
3. Inscribí un equipo: `http://futgo.test:8080/torneos/liga-prueba-a/mi-equipo/inscribir`
   - Nombre: **Halcones Demo**. Guardá.
4. ✅ Esperado: quedás como **capitán** del equipo (badge "Capitán" en la plantilla) y como **jugador activo** automáticamente.

---

## Escenario 2 — El mismo usuario se une como jugador en otro torneo

1. Cerrá sesión e iniciá como **`otro.capitan@futgo.test`**.
2. Inscribí un equipo en **Liga Prueba B**: `http://futgo.test:8080/torneos/liga-prueba-b/mi-equipo/inscribir`
   - Nombre: **Pumas Demo**. Guardá.
3. En el Team Hub de Pumas Demo (`/torneos/liga-prueba-b/mi-equipo`), abrí **+ Agregar jugador** → modo **Con cuenta** → email **`capitan.demo@futgo.test`** → Agregar.
4. ✅ Esperado: `capitan.demo` queda como **jugador (no capitán)** de Pumas Demo en Liga Prueba B, mientras sigue siendo **capitán** de Halcones Demo en Liga Prueba A.

---

## Escenario 3 — Sección "Mis Equipos"

1. Volvé a iniciar sesión como **`capitan.demo@futgo.test`**.
2. Menú superior → **Mis Equipos** (`http://futgo.test:8080/torneos/mis-equipos`).
3. ✅ Esperado: aparece **solo Halcones Demo** (el equipo que capitanea), con su torneo (Liga Prueba A), estado del equipo, cantidad de jugadores y accesos a gestionar la plantilla.
   - **NO** aparece Pumas Demo (ahí es jugador, no capitán).

---

## Escenario 4 — Dar de alta un jugador SIN cuenta (por verificar)

1. Como **`capitan.demo@futgo.test`**, entrá a la plantilla de **Halcones Demo**:
   `http://futgo.test:8080/torneos/liga-prueba-a/mi-equipo`
2. **+ Agregar jugador** → pestaña **Sin cuenta (por verificar)**.
   - Nombre completo: **Pelé Sin Cuenta**
   - Documento: **DOC-2026**
   - (Dorsal/Posición opcionales) → Agregar.
3. ✅ Esperado: el jugador aparece en la plantilla con badge **"Por verificar"**. No se creó ningún usuario nuevo en `users`.

---

## Escenario 5 — Intentar duplicar un jugador (mensaje de error)

**Por documento (jugador sin cuenta):**
1. En la plantilla de Halcones Demo, **+ Agregar jugador** → **Sin cuenta** → Nombre **Otro Pelé**, Documento **DOC-2026** (el mismo de antes) → Agregar.
2. ✅ Esperado: error en español → *"Ya hay un jugador con ese documento inscrito en este torneo."*

**Por usuario registrado:**
1. **+ Agregar jugador** → **Con cuenta** → email **`capitan.demo@futgo.test`** (que ya es jugador/capitán del torneo) → Agregar.
2. ✅ Esperado: error en español → *"Ya sos capitán de un equipo en este torneo."* (o *"… ya pertenece a un equipo en este torneo."* si se intenta sobre otro usuario ya inscrito).

---

## Queries SQL de verificación

```sql
-- Equipos del usuario de prueba y quién es su capitán
SELECT t.id, t.name AS equipo, tr.name AS torneo, t.captain_user_id, t.status
FROM teams t
JOIN tournaments tr ON tr.id = t.tournament_id
WHERE tr.slug IN ('liga-prueba-a','liga-prueba-b');

-- Membresías: capitán marcado is_captain y jugadores no registrados (user_id NULL)
SELECT tp.id, tp.team_id, tp.user_id, tp.is_captain,
       tp.full_name, tp.document, tp.verification_status, tp.status
FROM team_players tp
JOIN teams t  ON t.id = tp.team_id
JOIN tournaments tr ON tr.id = t.tournament_id
WHERE tr.slug IN ('liga-prueba-a','liga-prueba-b')
ORDER BY tp.team_id, tp.is_captain DESC;

-- Un usuario capitán en un torneo y jugador (no capitán) en otro
SELECT u.email, tr.name AS torneo, t.name AS equipo, tp.is_captain
FROM team_players tp
JOIN users u   ON u.id = tp.user_id
JOIN teams t   ON t.id = tp.team_id
JOIN tournaments tr ON tr.id = t.tournament_id
WHERE u.email = 'capitan.demo@futgo.test';

-- Conteo de jugadores por verificar
SELECT verification_status, COUNT(*) FROM team_players GROUP BY verification_status;
```

Resultado esperado de la 3ª query: dos filas para `capitan.demo@futgo.test` — `is_captain=1` en Liga Prueba A (Halcones) y `is_captain=0` en Liga Prueba B (Pumas).

---

## Limpieza (opcional)

```php
// En tinker — borra los torneos de prueba y sus dependencias en cascada.
use App\Models\Torneos\Tournament;
Tournament::whereIn('slug', ['liga-prueba-a','liga-prueba-b'])->get()->each->delete();
```

---

## Errores conocidos / limitaciones

- **Reclamo de perfil**: un jugador `por_verificar` puede vincularse a una cuenta real más adelante (el modelo lo soporta: setear `user_id` + `verification_status='registrado'`), pero el **flujo de reclamo con UI no está construido todavía** (queda para la Sesión B).
- **Coexistencia de portales**: `/torneos/mis-equipos` (índice ligero pedido en esta sesión) y `/capitan` (Panel Capitán, portal rico preexistente) conviven. Ambos listan equipos capitaneados; no es un bug.
- **Anular eliminatoria avanzada**: anular un resultado de una ronda ya avanzada no revierte automáticamente las rondas siguientes (limitación preexistente del módulo, no afecta esta sesión).
- Para inscribir equipos el torneo debe estar en estado **`open`**. El torneo demo (`Copa FutGO Demo 2026`) está `in_progress`, por eso esta guía crea torneos `open` aparte.
