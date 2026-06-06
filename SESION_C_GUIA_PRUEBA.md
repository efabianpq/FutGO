# Sesión C — Guía de prueba en el navegador

Dinámica del partido: **convocatoria previa**, **MVP por torneo** y **bajas/cambios de plantilla**.

App local: **http://futgo.test:8080** (alternativa: `php artisan serve --port=8001`).

> PATH de Laragon antes de `php artisan`:
> ```powershell
> $env:Path = "C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64;C:\laragon\bin\composer;C:\laragon\bin\mysql\mysql-8.0.30-winx64\bin;$env:Path"
> ```

---

## 0. Datos previos

La demo ya trae todo lo necesario (`php artisan db:seed --class=DemoTournamentSeeder --force`):
- **Copa FutGO Demo 2026** con **MVP habilitado**, 8 equipos, fixture y partidos (6 finalizados, resto programados).
- Admin torneo: `admin.torneo@demo.futgo.com` / `Demo2026!`
- Capitán Leones: `ldn.capitan@demo.futgo.com` / `Demo2026!`
- Jugador Leones: `ldn.j1@demo.futgo.com` / `Demo2026!`

Para probar el **cambio de equipo** (solo permitido con torneo en `open`) creá un torneo aparte:

```php
// php artisan tinker
use App\Models\User; use App\Models\Torneos\Tournament; use App\Models\Torneos\Club; use App\Models\Torneos\Team; use App\Models\Torneos\TeamPlayer;
$admin = User::where('email','admin.torneo@demo.futgo.com')->first();
$t = Tournament::create(['name'=>'Liga Open C','slug'=>'liga-open-c','sport'=>'futbol','status'=>'open','format'=>'round_robin','groups_count'=>1,'teams_per_group'=>2,'classifies_per_group'=>1,'max_teams'=>2,'points_win'=>3,'points_draw'=>1,'points_loss'=>0,'match_duration'=>90,'created_by_user_id'=>$admin->id]);
$t->tournamentAdmins()->create(['user_id'=>$admin->id]);
foreach(['Rojo','Azul'] as $n){ $cap=User::factory()->create(['is_active'=>true,'modules'=>'torneos']); $tm=Team::create(['tournament_id'=>$t->id,'captain_user_id'=>$cap->id,'name'=>"Equipo $n",'status'=>'approved']); TeamPlayer::create(['team_id'=>$tm->id,'user_id'=>$cap->id,'is_captain'=>true,'status'=>'active']); TeamPlayer::create(['team_id'=>$tm->id,'user_id'=>User::factory()->create(['is_active'=>true,'modules'=>'torneos'])->id,'status'=>'active']); }
echo $t->slug;
```

---

## Escenario 1 — Convocatoria previa + confirmar asistencia

1. Login como **capitán** (`ldn.capitan@demo.futgo.com`).
2. **Mis Torneos** → hub de Copa FutGO Demo 2026 → **Mi equipo** (`/torneos/copa-futgo-demo-2026/mi-equipo`).
3. En **Próximos partidos** (panel derecho) → bloque **Convocatoria (capitán)** → clic en **Convocar** de un partido programado.
4. Marcá los jugadores convocados y **Guardar convocatoria**. Cada uno queda “Convocado · sin responder”.
5. Login como **jugador** (`ldn.j1@demo.futgo.com`) → **Mi Carrera** → **Próximos partidos**: aparece “¡Estás convocado!” con **Confirmar** / **Declinar**.
6. Confirmá → queda “Asistencia confirmada”. ✅ Volviendo como capitán a la convocatoria, ese jugador figura “Confirmó asistencia”.

---

## Escenario 2 — MVP en torneo habilitado, visible en el perfil

1. Login como **admin torneo**.
2. Gestión Torneos → Copa FutGO Demo 2026 → **Partidos** → un partido **programado** → **Ingresar resultado**.
3. Cargá la planilla (jugadores que jugaron, goles). Aparece la sección **⭐ Figura del partido (MVP)** → elegí un jugador.
4. **Guardar planilla**. ✅ El MVP suma: andá a `/torneos/copa-futgo-demo-2026/estadisticas/jugador/{id}` o a **Mi Carrera** del jugador y verás el acumulado con MVP.

---

## Escenario 3 — Torneo sin MVP no muestra la opción

1. Creá/editá un torneo con la casilla **“Registrar MVP (figura del partido)”** desmarcada (Gestión Torneos → crear/editar).
2. Entrá a la planilla de un partido de ese torneo. ✅ **No** aparece la sección “Figura del partido”. Aunque se forzara el envío del campo, el backend lo ignora.

---

## Escenario 4 — Baja de un jugador (conserva stats)

1. Login como **admin torneo** → Copa Demo → **Equipos** → un equipo → ver jugadores.
2. En un jugador no capitán (con el torneo en curso) → **Baja** (confirmar).
3. ✅ Queda en estado **Inactivo**. Sus goles/tarjetas de partidos ya jugados **se conservan** (vé sus estadísticas).
4. ✅ En la **convocatoria** de próximos partidos, ese jugador ya **no aparece** entre los disponibles.

**Cambio de equipo (regla por estado):**
- En el torneo **Liga Open C** (status `open`): Equipos → equipo → jugador no capitán → **Cambiar** → elegí el otro equipo → **Mover**. ✅ Se transfiere y queda en `roster_movements`.
- En **Copa Demo** (en curso): la opción **Cambiar** no se ofrece; si se fuerza, responde “El cambio de equipo solo se permite con el torneo en inscripción.”

---

## Queries SQL de verificación

```sql
-- Convocatoria de un partido
SELECT cu.match_id, tp.full_name, u.name, cu.status, cu.responded_at
FROM match_call_ups cu
JOIN team_players tp ON tp.id = cu.team_player_id
LEFT JOIN users u ON u.id = tp.user_id;

-- MVPs por jugador (torneo) y acumulado histórico
SELECT ps.team_player_id, ps.mvps FROM player_stats ps WHERE ps.mvps > 0;
SELECT user_id, mvps FROM player_career_stats WHERE mvps > 0;
SELECT id, mvp_team_player_id FROM tournament_matches WHERE mvp_team_player_id IS NOT NULL;

-- Historial de bajas y cambios
SELECT type, player_name, from_team_id, to_team_id, notes, created_at
FROM roster_movements ORDER BY id DESC;

-- ¿El torneo usa MVP?
SELECT name, mvp_enabled FROM tournaments;
```

---

## Errores conocidos / limitaciones

- La **convocatoria previa** y la **alineación del resultado** son flujos separados a propósito (planeación vs acta oficial). La convocatoria no pre-rellena la planilla automáticamente.
- **Cambio de equipo** solo en torneo `open`. Con el torneo en curso, la vía realista es **baja** y, si las reglas lo permiten, **alta aprobada** por el admin en otro equipo.
- Una **baja** marca al jugador `inactive` (no se borra): conserva estadísticas y queda fuera de convocatorias/planillas futuras.
- El reclamo de perfil de jugadores `por_verificar` sigue sin UI (pendiente).
