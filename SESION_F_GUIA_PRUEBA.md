# Sesión F — Guía de prueba en el navegador

**Sistema de reputación FUTGO**: ranking global, logros (gamificación), fair play score, historial de temporadas y desempate por sorteo reproducible.

App local: **http://futgo.test:8080** (alternativa: `php artisan serve --port=8001`).

> PATH de Laragon antes de `php artisan`:
> ```powershell
> $env:Path = "C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64;C:\laragon\bin\composer;C:\laragon\bin\mysql\mysql-8.0.30-winx64\bin;$env:Path"
> ```

---

## 0. Datos previos

```powershell
php artisan migrate --seed          # corre AchievementSeeder (catálogo de logros) + demo
php artisan db:seed --class=DemoTournamentSeeder --force
php artisan torneos:rebuild-reputation   # construye fair play, logros y ranking cacheados
```

- **Copa FutGO Demo 2026** (ciudad/categoría definidas), con partidos jugados, goles, tarjetas y MVP.
- Jugador de prueba: `ldn.capitan@demo.futgo.com` / `Demo2026!`
- El comando `torneos:rebuild-reputation` reconstruye todo el cache de reputación (también se dispara solo al **finalizar** un torneo).

> Las **fórmulas**:
> - **Ranking** = goles·4 + asistencias·2 + MVP·6 + victorias·3 + vallas_invictas·2 + partidos·1 + fair_play·0.5
> - **Fair Play (jugador)** = max(0, 100 − 3·amarillas − 10·rojas − 5·inasistencias)
> - **Fair Play (equipo)** = promedio del fair play de sus jugadores registrados.

---

## Escenario 1 — Ranking global + filtros por ciudad/categoría

1. Login como cualquier usuario con módulo Torneos.
2. Menú **📊 Ranking** → `/torneos/ranking`.
3. Alterná **Jugadores / Equipos** (botones superiores).
4. En **Alcance** elegí **Por ciudad** y seleccioná una ciudad → la tabla se filtra a quienes acumularon en torneos de esa ciudad.
5. Igual con **Por categoría**.
6. El puntaje de cada fila sigue la fórmula del pie de página; los 3 primeros se resaltan.

✅ El ranking lee la tabla cacheada `futgo_rankings` (no calcula en el request).

---

## Escenario 2 — Provocar el logro "Veterano" (y verlo en el perfil)

Forzá acumulados de un jugador y reconstruí la reputación:

```php
// php artisan tinker
use App\Models\User; use App\Models\Torneos\PlayerCareerStat;
$u = User::where('email','ldn.capitan@demo.futgo.com')->first();
PlayerCareerStat::updateOrCreate(['user_id'=>$u->id], ['matches_played'=>50,'goals'=>12,'wins'=>10,'mvps'=>1]);
app(\App\Services\Torneos\AchievementService::class)->evaluateForUser($u);
echo $u->achievements()->pluck('code');   // incluye debut, veterano_10, veterano_50...
```

1. Login como ese jugador → **Mi Carrera** (`/torneos/mi-carrera`).
2. Sección **Logros**: los obtenidos aparecen en verde (Debut, Veterano, etc.); los bloqueados, atenuados.

✅ Reejecutar `evaluateForUser` NO duplica logros (idempotente).

---

## Escenario 3 — Tarjetas / inasistencias bajan el fair play

```php
// php artisan tinker
use App\Models\User; use App\Models\Torneos\TeamPlayer; use App\Models\Torneos\PlayerStat;
$u = User::where('email','ldn.capitan@demo.futgo.com')->first();
$tp = TeamPlayer::where('user_id',$u->id)->first();
PlayerStat::where('team_player_id',$tp->id)->update(['yellow_cards'=>2,'red_cards'=>1]);
$fp = app(\App\Services\Torneos\FairPlayService::class)->refreshForUser($u);
echo $fp->score;   // 100 − 3·2 − 10·1 = 84 (menos aún si hay convocatorias declinadas)
```

1. **Mi Carrera** → tarjeta **Fair Play**: muestra el puntaje y el desglose 🟨 🟥 🚫 (inasistencias).
2. Las inasistencias salen de la **convocatoria** de la Sesión C (declinadas + "convocado" a partido ya finalizado).

---

## Escenario 4 — Empate absoluto → sorteo reproducible

```php
// php artisan tinker — crear un grupo de 2 equipos que empatan 1-1 (empate absoluto)
use App\Models\User; use App\Models\Torneos\{Tournament,TournamentPhase,TournamentGroup,GroupTeam,Team,TournamentMatch};
$admin = User::first();
$t = Tournament::create(['name'=>'Sorteo Demo','slug'=>'sorteo-demo','sport'=>'futbol','status'=>'in_progress','format'=>'round_robin','groups_count'=>1,'teams_per_group'=>2,'classifies_per_group'=>1,'max_teams'=>2,'points_win'=>3,'points_draw'=>1,'points_loss'=>0,'created_by_user_id'=>$admin->id]);
$ph = TournamentPhase::create(['tournament_id'=>$t->id,'name'=>'Grupos','type'=>'groups','order'=>1,'is_active'=>true,'status'=>'active']);
$g = TournamentGroup::create(['phase_id'=>$ph->id,'name'=>'Grupo A','order'=>1]);
$a = Team::create(['tournament_id'=>$t->id,'captain_user_id'=>$admin->id,'name'=>'Equipo A','status'=>'approved']);
$b = Team::create(['tournament_id'=>$t->id,'captain_user_id'=>$admin->id,'name'=>'Equipo B','status'=>'approved']);
GroupTeam::create(['group_id'=>$g->id,'team_id'=>$a->id]); GroupTeam::create(['group_id'=>$g->id,'team_id'=>$b->id]);
TournamentMatch::create(['phase_id'=>$ph->id,'group_id'=>$g->id,'home_team_id'=>$a->id,'away_team_id'=>$b->id,'home_score'=>1,'away_score'=>1,'status'=>'finished','match_number'=>1]);
$calc = app(\App\Services\Torneos\StandingsCalculatorService::class);
$calc->recalculate($ph); $p1 = \App\Models\Torneos\Standing::where('group_id',$g->id)->pluck('position','team_id');
$calc->recalculate($ph); $p2 = \App\Models\Torneos\Standing::where('group_id',$g->id)->pluck('position','team_id');
echo $p1, $p2;   // posiciones IDÉNTICAS entre recálculos (reproducible)
echo \App\Models\Torneos\StandingDraw::where('group_id',$g->id)->count();   // 2 (auditado)
```

✅ El sorteo es **determinista** (seed estable `tournament:phase:group`), por eso dos recálculos dan el mismo orden, y queda **auditado** en `standing_draws`. Si los equipos difieren en cualquier criterio previo (DG, GF, head-to-head, fair play), NO se sortea (`standing_draws` queda vacío).

---

## Queries SQL de verificación

```sql
-- Ranking cacheado (jugadores, global)
SELECT position, display_name, score, matches_played, goals, mvps, fair_play
FROM futgo_rankings
WHERE subject_type='player' AND scope_type='global'
ORDER BY position LIMIT 20;

-- Logros otorgados
SELECT u.name, a.code, ua.awarded_at
FROM user_achievements ua
JOIN users u ON u.id = ua.user_id
JOIN achievements a ON a.id = ua.achievement_id
ORDER BY ua.awarded_at DESC;

-- Fair play
SELECT subject_type, subject_id, score, yellow_cards, red_cards, absences, matches
FROM fair_play_scores ORDER BY score ASC;

-- Sorteos auditados
SELECT phase_id, group_id, team_id, seed, draw_position FROM standing_draws ORDER BY group_id, draw_position;
```

---

## Errores conocidos / limitaciones

1. **Ranking cacheado**: se reconstruye al finalizar un torneo o con `torneos:rebuild-reputation` (cron). No refleja cambios al instante (por diseño, para no calcular por request).
2. **Fair play del equipo** = promedio del de sus jugadores registrados; los jugadores "por verificar" (sin cuenta) no aportan a la reputación hasta reclamar perfil.
3. **Fair play en standings** (criterio de desempate) usa la disciplina del torneo (amarillas + 3·rojas); el sorteo ('drawing') solo decide cuando todos los criterios previos empatan.
4. **Temporada** = año derivado de `starts_at` (o `created_at`) del torneo; no hay campo de temporada explícito (se consolida en lectura).
5. Los logros con métrica nueva requieren soportar esa métrica en el servicio; agregar logros con métricas ya soportadas (goals, matches, etc.) es solo insertar filas en `achievements`.
