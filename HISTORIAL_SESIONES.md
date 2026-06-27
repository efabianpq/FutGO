# HISTORIAL_SESIONES.md — FutGO v2
> Archivo de referencia histórica. Contiene el detalle de cada sesión de desarrollo.
> Para el contexto activo de Claude Code, usar `CLAUDE.md`.

---

## Etapa 1 — Estructura base del módulo Torneos
- Columna `users.modules` (DEFAULT 'polla')
- ENUM role ampliado con 'torneo_admin'
- Métodos `hasModuleAccess` / `hasTorneosAccess` / `hasPollaAccess` en User
- Middleware `EnsureModule` y `EnsureTorneoAdmin`
- Rutas base `/torneos` y `/admin/torneos`
- 7 tests de acceso por módulo

## Prompt 3 — 12 tablas creadas
`tournaments`, `tournament_admins`, `teams`, `team_players`, `tournament_phases`, `tournament_groups`, `group_teams`, `tournament_matches`, `match_events`, `standings`, `player_stats`, `tournament_invitations`. 10 tests en MigrationsTest.

## Prompt 4 — 12 modelos Eloquent (app/Models/Torneos/)
Tournament, TournamentAdmin, Team, TeamPlayer, TournamentPhase, TournamentGroup, GroupTeam, TournamentMatch, MatchEvent, Standing, PlayerStat, TournamentInvitation. 16 tests en ModelsTest.

## Prompt 5 — Panel de administración (CRUD torneos)
- `Admin/Torneos/TournamentController` (index, create, store, show, edit, update, updateStatus, destroy)
- 8 rutas bajo `/admin/torneos` con middleware `['auth','ensure.active','ensure.torneo_admin']`
- Scoping por rol; slug auto; secuencia de estado forzada draft→open→in_progress→finished
- Solo se elimina torneo en `status=draft`
- 14 tests en TournamentAdminTest

## Prompt 6 — Gestión de equipos y jugadores
- `Admin/Torneos/TeamAdminController` (index, show, approve, reject)
- `Torneos/TeamController` (inscribir, store, show, addPlayer, removePlayer)
- Al inscribir: capitán queda como jugador activo automáticamente, estado=pending
- Anti-duplicados por user_id dentro del torneo
- 13 tests en TeamsTest

## Prompt 7 — Generación de fixture
- `FixtureGeneratorService::generate()` + `advanceTeams()`
- `match_number` secuencial único a nivel torneo
- Cruce estándar A1vB2/B1vA2; rondas nombradas; tercer puesto opcional
- Comando `torneos:generate-fixture {tournament_id}`
- Fix: `TournamentGroup::teams()` belongsToMany especifica pivote `group_id`
- 13 tests en FixtureGeneratorTest

## Prompt 6B — Configuración avanzada del torneo
- Migración `000014`: +19 columnas en tournaments (visibility, category, city, venue, max_teams, points_win/draw/loss, tiebreaker_order JSON, knockout_tiebreak, min/max_players_per_team, match_duration, max_substitutions, registration_fee, prize_description, rules, logo_url, banner_url)
- Migración `000015`: +4 columnas en player_stats (wins, draws, losses, clean_sheets)
- `_form.blade.php` en 6 secciones con editor de tiebreaker ordenable con Alpine
- 5 tests nuevos en TournamentAdminTest
- ⚠ Siempre usar `points_win/draw/loss` (nunca hardcodear 3/1/0)

## Prompt 8 — Ingreso de resultados y eventos
- `StandingsCalculatorService::recalculate(TournamentPhase)` — puntos custom, tiebreaker_order, head_to_head
- `PlayerStatsCalculatorService::recalculate(Tournament, Team)`
- `MatchResultController` (index, show, store, markLive, destroy) con scoping
- `store`: DB::transaction → actualiza match, borra+crea eventos, recalcula standings y stats
- 13 tests en MatchResultTest
- ⚠ `matches_played` solo si el jugador tiene al menos un evento en el partido (cambió en Prompt 9)

## Prompt 9 — Lineup y estadísticas individuales
- Migración `000016`: tabla `match_lineups` (match_id, team_player_id, team_id, started, minute_in, minute_out)
- `match_lineups` pasa a ser la fuente de verdad para PJ/minutos/clean_sheets/V-E-D
- Fix: red_card → `team_player.status='inactive'` en el mismo transaction
- `StatsController`: goleadores y perfil individual por torneo
- 8 tests en PlayerStatsTest; 210 tests passing

## Prompt 10 — Tabla de posiciones y desempates
- `StandingsCalculatorService` reescrito: delete+insert (no updateOrCreate)
- Head-to-head: pts directos → DG directa → GF directa
- `StandingsController` (Admin/Torneos) con recálculo manual
- 14 tests en StandingsTest; 224 tests passing

## Prompt 11 — Cierre de fase de grupos y generación de eliminatoria
- Migración `000017`: columna `status` (pending/active/completed) en `tournament_phases`
- `PhaseClosureService::closeGroupPhase()`: valida, cierra, llama `advanceTeams()`, activa siguiente fase
- Bloqueo de fase cerrada en `MatchResultController`, `StandingsCalculatorService`, `StandingsController`
- `PhaseController` con rutas `GET/POST /admin/torneos/{tournament}/phases/{phase}/close`
- 12 tests en PhaseClosureTest; 236 tests passing

---

## Sesión A — Unificación identidad jugador/capitán + jugadores no registrados
**Decisiones de modelo:**
- Capitán se deriva POR EQUIPO (nunca rol global). Se conserva `teams.captain_user_id` + se agrega `team_players.is_captain`
- Jugadores no registrados: `user_id` NULL + `full_name` + `document` + `verification_status='por_verificar'`

**Migraciones:** `000019` — team_players + is_captain, full_name, document, verification_status; user_id pasa a NULLABLE

**Nuevas funciones:**
- `TeamPlayer`: helpers `isCaptain/isRegistered/isPorVerificar/displayName`
- `MyTeamsController` + vista `torneos/mis-equipos`
- `TeamController::addGuestPlayer` — alta de jugador sin cuenta
- Anti-dup por `document` para jugadores no registrados

9 tests en UnifiedCaptainPlayerTest; 335 tests passing.

⚠ Reclamo de perfil `por_verificar` preparado en modelo, sin UI.

---

## Sesión B — Perfil permanente (jugador + club) + foto + acumulado histórico
**Decisiones de modelo:**
- Equipo permanente: tabla `clubs` + `teams.club_id` (nullable FK). `teams` = participación en torneo.
- Acumulado jugador: tabla `player_career_stats` (1 fila por user_id), NO cache.

**Migraciones:** `000020` clubs + teams.club_id (backfill); `000021` users.avatar_url; `000022` mvp_team_player_id + player_stats.mvps; `000023` player_career_stats

**Nuevas funciones:**
- Foto de perfil: `ProfileController::updatePhoto` — disk public, borra anterior. Componente `<x-avatar>`
- `PlayerCareerStatsService::refreshForUser/Team/Tournament`
- MVP: `PlayerStatsCalculatorService` cuenta mvps; `MatchResultController::store` acepta `mvp_team_player_id`
- `PlayerCareerController` + vista `torneos/mi-carrera`
- `ClubController` show + updateShield
- Inscripción: find-or-create club por nombre de capitán

7 tests en CareerAndProfileTest; 342 tests passing.

---

## Refactor post-Sesión B — Equipo permanente transversal + unificación de menús
**Cambio de modelo:**
- EQUIPO = `clubs` (permanente) con `captain_user_id` y plantilla propia `club_players`
- `teams` = participación en torneo; `team_players` = snapshot copiado al enrolar

**Migraciones:** `000024` — clubs.captain_user_id + tabla `club_players` (backfill completo)

**Nuevas funciones:**
- `ClubMembershipService::enroll/syncMemberAdded/syncMemberRemoved/changeCaptain`
- Crear equipo standalone: `POST /torneos/equipos`
- Gestión permanente: `/torneos/clubes/{club}/gestionar`
- Inscripción = enrolar equipo permanente existente
- Aprobación de jugadores tardíos por admin del torneo

**Unificación de menús:**
- "Mi Actividad" + "Mi Carrera" → **Mi Carrera**
- "Mis Equipos" + "Panel Capitán" → **Mis Equipos**
- Rutas `/mi-actividad` y `/capitan` redirigen a los nuevos destinos

337 tests passing.

---

## Sesión C — Dinámica del partido (convocatoria previa, MVP, bajas/cambios)
**Migraciones:** `000025` match_call_ups; `000026` tournaments.mvp_enabled; `000027` roster_movements

**Convocatoria previa (≠ alineación):**
- `match_call_ups`: status convocado/confirmado/declinado + responded_at
- `CallUpController`: manage/store (capitán) + respond (jugador)
- Rutas `/torneos/{tournament}/partidos/{match}/convocatoria[/responder]`

**MVP por torneo:**
- `tournaments.mvp_enabled` → checkbox en `_form`
- Selector en planilla solo si habilitado; guarda `mvp_team_player_id`

**Bajas y cambios (TeamAdminController):**
- Baja: `status='inactive'` en torneo (preserva stats). Prohibida en `finished`, no al capitán.
- Cambio de equipo: solo con torneo en `open`; en `in_progress` = vía baja+alta aprobada.
- Historial en `roster_movements`

10 tests en MatchDynamicsTest; 347 tests passing.

---

## Sesión D — Credencial QR antifraude
**Decisiones:**
- Librería: `bacon/bacon-qr-code` v3 con backend SVG (sin GD ni imagick — portable a Hostinger)
- Identificador: `users.futgo_id` formato FG-XXXXXX (alfabeto sin ambiguos 0/O/1/I/L/U)
- Privacidad del QR: solo `?fg=FG-XXXXXX&sig=HMAC-SHA256-truncado` — sin nombre, email ni documento

**Migraciones:** `000028` users.futgo_id (unique) + users.document; `000029` credential_validations

**Nuevas funciones:**
- `CredentialService`: nextFutgoId, signatureFor, verify, qrUrlFor, qrSvgFor
- `CredentialController@show` + vista `torneos/credencial/show`
- `CredentialValidationController`: form + auto-validación por QR + ingreso manual
- Rutas `GET/POST /torneos/validar` registradas ANTES del comodín `/{tournament}`
- Firma inválida degrada con aviso (no bloquea — audita igual)

9 tests en CredentialQrTest; 356 tests passing.

---

## Sesión E — Portal público + contenido compartible + exportación PDF/CSV
**Decisiones:**
- Imágenes compartibles: SVG renderizado desde Blade (PHP puro, sin GD/imagick)
- Preview de link compartido: Open Graph tags (og:image = banner/logo del torneo)
- `TournamentReportService`: fuente única de datos de lectura con eager loading (sin N+1). Carga solo `id` y `name` del usuario (privacidad).

**Nuevas funciones:**
- Portal público: rutas `/t/{slug}` fuera del grupo auth. `abort_unless(isPublic, 404)`.
- `TournamentShareController`: tarjetas SVG goleadores/posiciones/mvp/resultado
- `TournamentExportController`: PDF (dompdf) + CSV con BOM UTF-8
- Componente `x-share.frame` (1080×1080 con branding FutGO)

11 tests en PublicPortalTest (incluye test N+1 con queries < 25); 367 tests passing.

⚠ Portal limita a 12 resultados / 12 próximos / top 10 goleadores (sin paginación).

---

## Sesión F — Sistema de reputación (ranking, logros, fair play, temporadas, sorteo)
**Decisiones:**
- Ranking CACHEADO: tabla `futgo_rankings`. Se reconstruye al finalizar torneo + cron.
- Fair play CACHEADO: tabla `fair_play_scores`. Inasistencias = MatchCallUp 'declinado' + 'convocado' a partido finalizado.
- Logros DATA-DRIVEN: catálogo `achievements` + asignaciones `user_achievements`. Sin cambios de esquema para nuevos logros si la métrica ya está soportada.

**Fórmulas:**
- Ranking: `goles·4 + asist·2 + MVP·6 + victorias·3 + vallas·2 + PJ·1 + fair_play·0.5`
- Fair play jugador: `max(0, 100 − 3·amarillas − 10·rojas − 5·inasistencias)`

**Migraciones:** `000030` achievements; `000031` user_achievements; `000032` fair_play_scores; `000033` futgo_rankings; `000034` standing_draws

**Desempate 'drawing' resuelto:**
- Sorteo determinista: `seed=crc32(tournament:phase:group) + md5(seed:team_id)` — reproducible entre recálculos
- Auditado en `standing_draws` (borra+inserta por grupo)
- Corre DESPUÉS de fair_play, ANTES del cual van todos los criterios configurados

**Nuevas funciones:**
- `ReputationService::consolidateTournament/rebuildAll`
- `AchievementSeeder`: debut, veterano_10/50, goleador_10/50/100, asistidor_10, figura_5, muro_10, ganador_25, juego_limpio
- `SeasonHistoryService::forUser` — agrupa por año sin nuevo esquema
- `RankingController` (alias `TorneosRankingController` por colisión con la polla)
- Mi Carrera extendida: Fair Play + Logros + Historial por temporada

8 tests nuevos (ReputationTest + DrawTiebreakerTest); 375 tests passing.

---

## Sesión G — Recordatorios de partidos + patrocinadores + cierre v2.1
**Recordatorios:**
- `MatchReminderNotification` (mail): partido próximo a jugador convocado
- Comando `torneos:match-reminders {--minutes=1440}`: idempotente via `tournament_match_notifications`
- Migración `000035` tournament_match_notifications
- Scheduler: `->hourly()->withoutOverlapping()->appendOutputTo(logs/torneos-reminders.log)`
- No toca los 3 schedulers de la polla

**Patrocinadores:**
- Migración `000036` tournament_sponsors (name, logo_url, link_url, sort_order, is_active)
- `SponsorController` (index/store/destroy con scoping)
- Se muestran en portal público

**Estado final:** 380 tests passing. Guía integral: `docs/TESTING_GUIDE_FUTGO_v2.1.md`.

⚠ Solo convocados/confirmados registrados reciben recordatorios. Email en local = driver log.

---

## Sesión P-0 — Checklist de producción (bloqueantes 🔴)

**APP_DEBUG y variables de entorno:**
- `.env.example` actualizado con bloque de producción comentado: `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://tu-dominio.com`
- `LOG_LEVEL=error` en prod documentado como comentario

**Vistas de error personalizadas:**
- `resources/views/errors/404.blade.php` — mismo layout, voseo, mensaje amigable, botones "Volver al inicio" y "Página anterior"
- `resources/views/errors/500.blade.php` — mismo layout, voseo, pasos sugeridos, botones "Volver al inicio" y "Reintentar"
- Ambas usan `@extends('layouts.app')` — se activan automáticamente en producción (`APP_DEBUG=false`)

**Backups automatizados (spatie/laravel-backup ^9.0):**
- `composer.json`: `spatie/laravel-backup` agregado
- `config/backup.php`: solo-DB, compresión gzip, retención 7 días mínimo (+ 14 diarios, 4 semanales, 2 mensuales), destino local + opcional `BACKUP_DISK=r2`
- `routes/console.php`: `backup:run --only-db` diario 03:00 + `backup:clean` 03:30, ambos con `withoutOverlapping()` y log en `logs/backup.log`
- Notificaciones por mail a `BACKUP_NOTIFICATION_EMAIL`
- No toca los schedulers de la polla ni el de torneos

**Storage de medios (Cloudflare R2):**
- `config/filesystems.php`: disco `r2` con driver S3 apuntando a variables `R2_*`; clave `media_disk` lee `MEDIA_DISK` del `.env`
- `.env.example`: variables `R2_*` y `MEDIA_DISK` documentadas
- `ProfileController`, `ClubController`, `TournamentController`: todos usan `config('filesystems.media_disk', 'public')` — sin hardcodeo de `'public'`
- Compatibilidad retroactiva: `TournamentController::handleImageUploads` maneja URLs antiguas `/storage/...` usando disk `'public'`

**Estado final:** 458 tests passing (sin cambios en tests — solo infraestructura y vistas de error).

⚠ Para producción en Hostinger: configurar `MEDIA_DISK=r2`, `R2_*` y `BACKUP_NOTIFICATION_EMAIL` en el `.env` real. El `.env.example` documenta todos los valores necesarios.
