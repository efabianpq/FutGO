# CLAUDE.md — FutGO v2

## 1. Qué es FutGO

Plataforma de gestión de torneos deportivos amateur + red social deportiva construida alrededor de ellos.

**Objetivo del producto:** darle a la comunidad de fútbol amateur (jugadores, capitanes, organizadores) un lugar único para (a) organizar y jugar torneos con toda la operación digitalizada (fixture, resultados, stats, credenciales), y (b) conectar entre partidos: encontrar rivales, armar amistosos, seguir la reputación de con quién jugás, y descubrir gente/clubes/canchas cerca tuyo — todo sin fricción de moderación humana en el camino crítico (reglas explícitas, no ML, nada bloqueante).

**Estado general: aplicación funcionalmente completa (100%).** Los tres pilares (Torneos, FutGO Social en sus 3 fases, y el hub de navegación/descubrimiento) están implementados y en producción. Deuda técnica remanente es menor y está documentada en §8.

**Modelo de usuario: sin diferenciación organizador/jugador.** Todo usuario autenticado es un "usuario general" que puede crear torneos, jugar, organizar y participar en lo social sin gestión de permisos previa (pensado para distribución pública en Play Store/App Store, registro libre e inmediato). Solo existe un rol especial: `admin` (maestro, ve y administra todo). El acceso a un torneo puntual se rige por pertenencia (creador/`tournament_admins`/capitán/jugador) o por `visibility=public`, nunca por rol — ver §4.3.

URL local: `http://futgo.test:8080`

---

## 2. Stack tecnológico

| Capa | Tecnología |
|---|---|
| Backend | PHP 8.3.30 · Laravel 11.46 |
| Base de datos | MySQL 8.0.30 (dev/prod) · SQLite in-memory (tests) |
| Frontend | Blade + Alpine.js 3 + Tailwind 3 + Vite 5 |
| Servidor local | Apache (Laragon), puerto 8080 |
| Colas | `QUEUE_CONNECTION=database` (jobs de generación de tarjetas PNG) |
| Storage de medios | Driver configurable vía `MEDIA_DISK`: `public` (dev) / `r2` (prod, Cloudflare R2 vía S3 driver) |
| Backups | `spatie/laravel-backup` — `backup:run --only-db` diario 03:00 + `backup:clean` 03:30, rotación 7 días, opcional a R2 |
| Imágenes | GD (tarjetas PNG) con fallback a SVG si no está disponible |
| Scheduler | 1 cron/minuto (Hostinger) → `scheduler.sh` → `php artisan schedule:run` |
| Tests | PHPUnit, **510 tests passing** |

### Comandos frecuentes
```
php artisan test
php artisan migrate --seed
php artisan optimize:clear
npm run build
php artisan serve --port=8001   # si el vhost no responde
```

### PATH Laragon (PowerShell — prepender antes de cualquier comando)
```powershell
$env:Path = "C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64;C:\laragon\bin\composer;C:\laragon\bin\mysql\mysql-8.0.30-winx64\bin;$env:Path"
```

### Convenciones
- Idioma: español, voseo (tanto en UI como en mensajes de commit).
- Commits: `tipo: descripción corta en español`.
- `Co-Authored-By: Claude Sonnet 4.6 <noreply@anthropic.com>`.

---

## 3. Mapa de módulos y estado

| Módulo | Estado |
|---|---|
| **Polla Mundial** | ❌ ELIMINADA (2026-07-01) — migrada a otra aplicación; código, rutas, tablas y el gate de activación por código de invitación fueron removidos por completo |
| **Torneos** | ✅ v2.1 completo (Sesiones A–G) |
| **FutGO Social — Fase 1** | ✅ completa (S1-A a S1-F) |
| **FutGO Social — Fase 2** | ✅ completa (S2-A, S2-B) |
| **FutGO Social — Fase 3** | ✅ completa (S3-A, S3-B) |
| **Deuda técnica TX-1** | ✅ resuelta — reclamo de perfil |
| **Deuda técnica TX-2** | ✅ resuelta — tarjetas PNG / WhatsApp / OG tags |
| **Navegación v3 (UX-1)** | ✅ completa — nav agrupado + dashboard de Inicio + buscador global |
| **Producción / seguridad (P-0, P-1)** | ✅ checklist resuelto |

Visión y roadmap original de Social en `PROPUESTA_FUTGO_SOCIAL_v3.md`. Historial sesión por sesión en `docs/HISTORIAL_SESIONES.md`.

---

## 4. Módulo Torneos — modelo de datos y servicios

### 4.1 Tablas clave

| Tabla | Rol |
|---|---|
| `users` | `futgo_id` (FG-XXXXXX), `avatar_url`, `document`, `role` (`user`/`admin`) |
| `clubs` | Equipo permanente. `captain_user_id`, `shield_url` |
| `club_players` | Plantilla permanente del club. `user_id` nullable, `is_captain`, `verification_status` |
| `teams` | Participación de un club en un torneo (`club_id`, `tournament_id`) |
| `team_players` | Snapshot copiado al enrolar. Fuente de stats por torneo |
| `tournaments` | `status` draft→open→in_progress→finished, `format`, `points_win/draw/loss`, `tiebreaker_order` JSON, `visibility`, `mvp_enabled` |
| `tournament_phases` | `status` pending/active/completed, `type` groups/knockout |
| `tournament_matches` | `status`, `match_number` único por torneo, `mvp_team_player_id` |
| `match_lineups` | Fuente de verdad para `matches_played`/`minutes_played`/stats derivadas |
| `match_call_ups` | Convocatoria previa (≠ alineación) |
| `match_events` | Goles, tarjetas, sustituciones por jugador |
| `player_stats` | Stats por torneo+team_player |
| `player_career_stats` | Acumulado histórico por user (1 fila, reconstruible) |
| `standings` | Recalculado por `StandingsCalculatorService` (delete+insert) |
| `futgo_rankings` | Cache de ranking (reconstruido al finalizar torneo o por cron) |
| `fair_play_scores` | Cache fair play jugador/equipo |
| `achievements` / `user_achievements` | Catálogo data-driven + asignaciones |
| `credential_validations` | Auditoría de validaciones QR |
| `tournament_sponsors` | Patrocinadores (sin cobro) |
| `roster_movements` | Historial bajas/cambios de plantilla |
| `profile_claims` | Reclamo de perfil de jugador `por_verificar` (ver §7) |

### 4.2 Servicios y reglas de negocio críticas

**FixtureGeneratorService**
- Valida: torneo en `open`, sin fixture previo, equipos suficientes.
- `knockout_only` exige potencia de 2 (≥4 equipos).
- Cruce estándar: A1vB2 / B1vA2 (si classifies==2 y grupos pares).
- Deja el torneo en `in_progress`.

**StandingsCalculatorService**
- delete+insert (no updateOrCreate) — recálculo limpio.
- Solo partidos `finished`.
- Desempate: `tiebreaker_order` del torneo → fair_play → sorteo determinista (crc32+md5, auditado en `standing_draws`).
- Bloquea recálculo si la fase está `completed`.

**PhaseClosureService**
- Cierra fase de grupos → activa siguiente fase knockout.
- Tercer puesto se puebla al cerrar semifinal (no al cerrar grupos).
- Bloquea si hay partidos pendientes.

**PlayerStatsCalculatorService**
- `match_lineups` es la fuente de verdad para PJ/minutos/clean_sheets/V-E-D.
- `match_events` para goles/asist/tarjetas.
- MVP desde `tournament_matches.mvp_team_player_id` (solo si `mvp_enabled`).

**PlayerCareerStatsService**
- `refreshForUser/Team/Tournament`.
- Solo jugadores registrados (`user_id` no nulo).
- Se llama tras cada store/destroy de resultado y al finalizar torneo.

**ReputationService** (orquesta al finalizar torneo)
- `RankingService::rebuild()` — fórmula: goles·4 + asist·2 + MVP·6 + victorias·3 + vallas·2 + PJ·1 + fair_play·0.5.
- `FairPlayService` — fórmula jugador: `max(0, 100 − 3·amarillas − 10·rojas − 5·inasistencias)`.
- `AchievementService::evaluateForUser` — idempotente (firstOrCreate).

**ClubMembershipService**
- `enroll(club, torneo)` copia plantilla permanente al torneo.
- Jugador agregado en `open` → `active`; en `in_progress` → `pending` (requiere aprobación admin).
- Quitar miembro en curso → `inactive` en ese torneo (preserva stats).

**CredentialService**
- QR codifica solo `?fg=FG-XXXXXX&sig=HMAC` — sin datos sensibles.
- Firma inválida degrada con aviso (no bloquea).
- Solo jugadores con cuenta tienen credencial.

### 4.3 Identidad y acceso

- **Sin diferenciación organizador/jugador.** `users.role` solo tiene dos valores: `user` (cualquier persona, puede crear torneos, jugar, todo) y `admin` (maestro de plataforma, ve/administra todo vía middleware `admin` → `EnsureAdmin`).
- Cualquier usuario autenticado puede crear un torneo (`admin.torneos.create`/`store`, sin gate de rol). El creador queda auto-adjunto en `tournament_admins` (`Tournament::admins()->attach()`).
- Gestión de un torneo puntual (`/admin/torneos/{tournament}/...`) la autoriza cada controlador por-torneo: `admin` global, o el usuario está en `tournament_admins` de ESE torneo (`TournamentController::authorizeAccess/scopedQuery`, replicado en `TeamAdminController`, `FixtureController`, `MatchSchedulerController`, `PhaseController`, `StandingsController`, `SponsorController`, `MatchResultController`). No hay policy central; el patrón se repite por controlador.
- Capitán = derivado POR CLUB (`clubs.captain_user_id` + `club_players.is_captain`). No hay rol global; independiente de `tournament_admins`.
- `EnsureTournamentParticipant` (hub `/torneos/{slug}`): acceso si es admin global, `tournament_admins` del torneo, capitán o jugador de un equipo del torneo. 403 en cualquier otro caso.
- Portal público `/t/{slug}` y estadísticas (`StatsController`) — sin auth, solo torneos `visibility=public` (o admin/participante para los privados).
- Registro (`/register`) es libre e inmediato: no hay activación por código de invitación ni aprobación previa (columna `is_active`/`invitation_code` eliminadas junto con Polla).
- Convocatoria y responder convocatoria: capitán arma, jugador confirma/declina desde "Mi Carrera".

### 4.4 Scheduler e infraestructura compartida

- Único cron cada minuto → `scheduler.sh` → `php artisan schedule:run`.
- Comandos activos: `torneos:match-reminders` (hourly), `torneos:rebuild-reputation` (cron), `social:expire-opportunities` (hourly), `social:detect-no-shows` (hourly), `social:rebuild-reliability` (diario 04:00).
- `backup:run --only-db` (03:00 diario) + `backup:clean` (03:30).
- Storage: `MEDIA_DISK` en `.env` (`public` dev / `r2` prod); `config/filesystems.php` expone disco `r2` (S3 driver). Ningún controlador hardcodea `'public'` (excepción: compatibilidad de URLs antiguas `/storage/...` en `TournamentController::handleImageUploads`).

---

## 5. FutGO Social — modelo de datos base (Fase 1, S1-A)

Namespace de modelos: `App\Models\Social\*`. Migraciones `2026_06_25_000001..011`.
Tipos polimórficos usan **morph map** (`AppServiceProvider`): `user`, `club`, `tournament`, `opportunity`, `friendly_match`, `message`, `achievement`, `feed_event`, `venue` (alias estables en vez de FQCN).

| Tabla | Modelo | Rol |
|---|---|---|
| `users.play_level` / `clubs.play_level` | `Concerns\HasPlayLevel` | Nivel declarado (recreativo/intermedio/competitivo/elite_amateur). Nullable. **Filtro obligatorio del matching**, no decorativo |
| `opportunities` | `Social\Opportunity` | Entidad central. `type` y `status` STRING (extensible sin migración). Creador `user_id` y/o `club_id`. `payload` JSON tipado. Tipos: BUSCAR_RIVAL/JUGADOR/REFUERZO/EQUIPO |
| `opportunity_responses` | `Social\OpportunityResponse` | Respuesta a una oportunidad. Estados pendiente/aceptada/rechazada/contrapropuesta. Una sola aceptada (garantía en servicio) |
| `friendly_matches` | `Social\FriendlyMatch` | Amistoso confirmado, sin `tournament_id`. Doble confirmación: cada club reporta marcador; coinciden→`jugado`/`acordado`, difieren→`en_disputa` |
| `reliability_events` | `Social\ReliabilityEvent` | Eventos polimórficos (subject user/club): no_show, cancelacion_tardia, respuesta_rapida, calificacion_+/− |
| `reliability_scores` | `Social\ReliabilityScore` | Cache 0-100 por sujeto. `is_paused`: 2 no-shows/30d → pausa manual |
| `follows` | `Social\Follow` | Polimórfica única: usuario sigue club/jugador/torneo. Alimenta el Feed |
| `conversations` | `Social\Conversation` | Hilo, vínculo opcional (morph) a opportunity/friendly_match |
| `conversation_participants` | `Social\ConversationParticipant` | Participante (user y/o club), `last_read_at` |
| `messages` | `Social\Message` | Mensaje. `type` structured (MVP) / free (Fase 2) |
| `content_reports` | `Social\ContentReport` | Moderación día-uno. Reporte polimórfico, revisión admin |
| `feed_events` | `Social\FeedEvent` | UN registro por evento del sistema. `actor`/`subject`, `city`/`required_level`, `payload` denormalizado |
| `venues` | `Social\Venue` | Cancha del catálogo compartido (ver S3-B) |

### 5.1 Oportunidades (publicar / responder / aceptar) — S1-B

`App\Services\Social\OpportunityService` orquesta el ciclo:
- `publish(User, array)` — creador es club (RIVAL/JUGADOR/REFUERZO, exige capitanía) o jugador (EQUIPO). `required_level` obligatorio. Deriva `expires_at` de la ventana si no se pasa.
- `respond(Opportunity, User, array)` — no se responde la propia; RIVAL/EQUIPO responden como club, JUGADOR/REFUERZO como jugador; sin duplicar respuesta pendiente.
- `accept(OpportunityResponse)` — transaccional con `lockForUpdate`. Según tipo: RIVAL→`FriendlyMatch` confirmado (cierra); JUGADOR→fila en `club_players` + descuenta `payload['cupos']` (sigue abierta si quedan); REFUERZO→`payload['assignment']` puntual (cierra); EQUIPO→suma al publicante a la plantilla del respondente (cierra).
- `reject` / `counter` (contrapropuesta → `en_negociacion`).
- `cancel(Opportunity, reason)` — amistoso confirmado se cancela siempre; si ≤24h antes del partido genera `reliability_event` `cancelacion_tardia`.
- `expireDue()` — marca `vencida` (comando `social:expire-opportunities`, scheduler hourly).

Moderación día-uno: `App\Support\Social\ContentFilter` + `App\Rules\CleanText` (palabras prohibidas) en descripción y mensajes; longitud máxima; `content_reports` vía botón "Reportar". Exploración pública `/oportunidades` sin auth, filtro de nivel obligatorio. Controlador `Social\OpportunityController`.

### 5.2 Ciclo de vida del Amistoso — S1-C

`App\Services\Social\FriendlyMatchService`:
- `reportResult(FriendlyMatch, Club, home, away)` — transaccional con `lockForUpdate`. Coinciden → `jugado`/`acordado` + `final_*`. Difieren → `en_disputa`.
- `escalate(FriendlyMatch, Club)` — solo en disputa.
- `resolveByAdmin(FriendlyMatch, User admin, home, away)` — fija resultado oficial.
- `cancel(FriendlyMatch, Club, reason)` — solo `confirmado`; ≤24h antes → `cancelacion_tardia`. **Única fuente de verdad de cancelación** (`OpportunityService::cancel` delega acá).
- Métricas read-time (sin cache): `userFriendlies`/`userMetrics`, `clubFriendlies`/`clubMetrics`. Solo `jugado` cuenta para reputación.

Capitán: "Mis amistosos" `/amistosos`. Admin: `/admin/amistosos` (disputas + resolución + historial de cancelaciones).

### 5.3 Confiabilidad — S1-D

`ReliabilityService` (clona patrón `FairPlayService`). Fórmula: 100 + delta por evento en ventana 90d — pesos: no_show −20, cancelacion_tardia −10, respuesta_rapida +5, calificacion_positiva +8, calificacion_negativa −12.
Pausa automática: 2+ no-shows en 30d → `is_paused=true`, bloquea `publish` (`OpportunityException::paused()`), redirect a `/oportunidades/reactivar`.
Detección automática de no-show: comando `social:detect-no-shows` (hourly, idempotente). `social:rebuild-reliability` (diario 04:00).

### 5.4 Seguir entidades + Feed de sistema — S1-E

**Seguir** (`FollowService` + `Social\FollowController`): TOGGLE único `POST /seguir/{type}/{id}` (club|user|tournament). Componente `<x-social.follow-button>`.

**Feed** (`FeedService` + `Social\FeedController`, `/feed`): relevancia calculada en lectura (sin filas por usuario) — evento relevante si el usuario sigue actor/subject, o coincide ciudad+nivel. Feed vacío → oportunidades de entrada de su ciudad. Paginado (15/pág).

Generación de eventos no bloqueante (`FeedService::record()` atrapa toda excepción): oportunidad_publicada/aceptada, amistoso_confirmado, resultado_amistoso, resultado_torneo, logro_desbloqueado.

Badge de no leídos en navbar = eventos posteriores a `users.feed_last_read_at` (O(1), sin tabla de lecturas). Ciudad/nivel editables en el perfil.

### 5.5 Moderación + fichas públicas — S1-F

`ModerationService` (resolveReport: dismissed/hidden/suspended; hideEntity; suspendUser/unsuspendUser). Panel `/admin/moderacion`. `Opportunity::scopeVisible()` + `abort_if(is_hidden, 404)`. `User::isSuspended()` chequeado en `publish`/`respond`.

**Ficha pública de jugador** `/j/{futgo_id}` (sin auth, nunca expone email/teléfono/documento): foto, nombre, nivel, ciudad, métricas sociales, logros, historial de temporadas, oportunidades BUSCAR_EQUIPO abiertas, score de confiabilidad solo si ≥ `MIN_SCORE_VISIBLE = 80`, botón seguir.

**Ficha pública de club** extendida: nivel declarado, links a fichas de jugadores, score de confiabilidad (siempre visible para capitán/admin, público si ≥80), oportunidades abiertas.

---

## 6. FutGO Social — Fase 2 (S2-A, S2-B)

### 6.1 "Jugué con vos" + Agenda deportiva — S2-A

Sin migraciones: todo derivado en lectura.

**"Jugué con vos"** (`PlayedWithService`): historial de partidos compartidos entre jugadores, sin solicitud ni aceptación.
- `sharedPlayers(User)` → colección ordenada por partidos compartidos. Sin N+1: 3 queries fijas.
- `sharedCount(User, User)` → conteo entre dos jugadores.
- Fuentes: **torneos** (self-join `match_lineups` sobre `match_id`) + **amistosos** (self-join `club_players` sobre amistosos `jugado`, plantilla actual).
- Acciones directas: "Retar a un amistoso" / "Invitar a mi equipo" — pre-completan alta de oportunidad vía `?tipo=…&target={futgo_id}` (`payload.directed_to_user_id`).

**Agenda deportiva unificada** (`SportsAgendaService`, `/agenda`): vista de lectura que agrega por día, cronológica: partidos de torneo + convocatoria (confirmar/declinar inline), amistosos confirmados con recordatorio de resultado, oportunidades por vencer (ventana 7 días). Excluye torneos/amistosos cancelados.

### 6.2 Mensajería libre — S2-B

Sin migraciones (esquema diseñado desde S1-A). `messages.is_hidden` ahora se respeta en lectura.

`ConversationService`:
- **Creación automática** desde `OpportunityService::accept` (dentro de la transacción): `ensureForAcceptedResponse`. Un chat SIEMPRE nace de un acuerdo previo. BUSCAR_RIVAL → vinculado al amistoso (participantes = capitanes); resto → vinculado a la oportunidad (publicante + respondente). `firstOrCreate` idempotente. Primer mensaje `type=structured` generado por el sistema.
- `postMessage(Conversation, User, body, ?asClubId)` — mensaje `type=free`, actualiza `last_message_at`.
- `markRead`/`hasUnread`/`unreadCount(User)` — comparando `last_read_at` vs `last_message_at` (sin tabla de lecturas). Alimenta badge "Mensajes".
- `forUser(User)` — lista de conversaciones.

`Social\ConversationController` (`/mensajes`): index/show/store/shareContact (publica WhatsApp explícitamente)/reportMessage. Acceso por participación validado en controlador (`hasParticipant` → 403). `max:1000` + `CleanText`. `Message::scopeVisible()` filtra ocultos.

---

## 7. FutGO Social — Fase 3 (S3-A, S3-B)

### 7.1 Recomendaciones por reglas + modo rápido — S3-A

Capa de inteligencia **por reglas explícitas (sin ML)**, determinista y auditable. Migraciones `2026_06_27_000001` (`opportunities.is_express`) y `2026_06_27_000002` (`clubs.level_suggestion_dismissed_at`).

`SuggestionService`:
- **Clubs compatibles para BUSCAR_RIVAL** (`compatibleRivalsFor`): hasta `MAX_SUGGESTIONS=5`. Filtros duros: misma ciudad, nivel igual/adyacente (±1), activo ≤`ACTIVITY_WINDOW_DAYS=30`, confiabilidad ≥`MIN_RELIABILITY=60`, nunca pausado. Orden por score compuesto (confiabilidad + bonus nivel + bonus recencia). Sin N+1 (4 queries fijas).
- **Recategorización de nivel** (`levelRecategorization`): ≥`RECATEGORIZATION_WINS=3` victorias vs. nivel superior → sugiere subir. Solo aviso, dismissable (`clubs.level_suggestion_dismissed_at`).

**Modo rápido (express)**: `opportunities.is_express`. Form simplificado `/oportunidades/rapida`, siempre BUSCAR_RIVAL, pre-completa club/ciudad/nivel, propone disponibilidad más cercana. `expires_at = window_start` (vence solo, sin lógica nueva de scheduler). Badge "⚡ Urgente" en listado/ficha/Feed.

**Head-to-head**: derivado de `FriendlyMatch` (`clubHeadToHead`/`headToHeadCount`), sin tabla nueva.

### 7.2 Venues / canchas — S3-B

Catálogo compartido `venues`, no pertenece a ningún club/torneo. Cualquier usuario registrado propone una cancha; solo registrador o admin edita. Migraciones `2026_06_27_000003` (tabla `venues`) y `2026_06_27_000004` (`venue_id` en `friendly_matches`/`opportunities`).

`Social\VenueController` (`/canchas`): index (filtro ciudad/búsqueda, público), show (`/c/{slug}`, sin auth), search (JSON autocompletado Alpine.js), create/store/edit/update (auth).
Widget reutilizable `social.canchas._search_widget` integrado al form de BUSCAR_RIVAL (texto libre como fallback). `OpportunityService::acceptRival` copia `venue_id` al `FriendlyMatch`. Perfil de cancha: partidos jugados + próximos, badge Ocupada/Disponible. `Venue::canBeEditedBy`, `Venue::generateUniqueSlug`.

---

## 8. Deuda técnica y decisiones de diseño conocidas

| # | Ítem | Estado / impacto |
|---|---|---|
| 1 | Anular partido de eliminatoria ya avanzada no revierte rondas siguientes | ✅ Resuelta (TX-3) — `FixtureGeneratorService::downstreamMatchesFor()` detecta la ronda siguiente (y tercer puesto) ya poblada y `MatchResultController::destroy` bloquea la anulación con aviso; el admin debe anular primero la ronda siguiente |
| 2 | Reclamo de perfil `por_verificar` | ✅ Resuelta (TX-1, §9) |
| 3 | Stats de club agregadas en lectura (sin tabla propia) | ✅ Resuelta (TX-7) — tabla cache `club_stats` (PJ/G/E/P, goles, top 10 goleadores en JSON) poblada por `ClubStatsService::refreshForClub()/rebuild()`, enganchada en `ReputationService::consolidateTournament()` y `rebuildAll()`; `ClubController::show` lee la cache (fallback perezoso si un club nunca se refrescó) |
| 4 | Selector de MVP en planilla de resultados sin UI | ✅ Resuelta — checkbox `mvp_enabled` en el form de torneo, `<select>` en `resultado.blade.php`, validación/persistencia en `MatchResultController::store`, tests en `MatchDynamicsTest.php` |
| 5 | Fotos en disco local | ✅ Resuelta P-0 — `MEDIA_DISK=r2` (Cloudflare R2) |
| 6 | Convocatoria previa no pre-llena la alineación | ✅ Resuelta (TX-4) — `MatchResultController::show` consulta `match_call_ups` confirmados; la planilla pre-marca solo esos jugadores (si no hay convocatoria cargada, mantiene el fallback de todo el plantel activo) |
| 7 | QR con firma inválida igual resuelve al jugador (con aviso) | Decisión de diseño intencional — comunicar a árbitros |
| 8 | Tarjetas compartibles solo SVG | ✅ Resuelta (TX-2, §10) |
| 9 | Portal público sin paginación (límite 12/12/10) | ✅ Resuelta (TX-6) — `TournamentReportService::paginatedFinishedMatches/paginatedUpcomingMatches/paginatedTopScorers` (pageName distinto por listado: `resultados_page`/`proximos_page`/`goleadores_page`), usados solo en `PublicTournamentController::show`; tarjetas compartibles y export PDF/CSV siguen usando los métodos originales sin paginar. Posiciones (`groupStandings`) queda fuera de alcance por ahora (agrupado en memoria) |
| 10 | Ranking/fair play no son tiempo real (cache) | ✅ Resuelta (TX-5) — `torneos:rebuild-reputation` agendado diario (04:30, antes faltaba pese a estar documentado); timestamp "Actualizado hace X" en `/torneos/ranking`; botón "Recalcular ahora" solo para admin global (`admin.ranking.recalculate` → `ReputationService::rebuildAll()`) |
| 11 | `por_verificar` no reciben recordatorios por email | ✅ Resuelta (TX-1) — al vincular cuenta entra a fair play (#12) y recordatorios (#13) |

**Pendientes de roadmap (no bloqueantes, próximas fases posibles):**
- Score de confiabilidad en tabla de perfiles.
- Eventos de Feed para seguidores de un torneo.
- Mensajería estructurada/plantillas y adjuntos (foto) desde UI.
- Fotos de canchas (upload a R2).

---

## 9. Reclamo de perfil — jugadores `por_verificar` (TX-1)

Un capitán puede anotar a un jugador informal sin cuenta usando solo nombre + documento (`club_players.verification_status='por_verificar'`, `user_id` nulo). Ese jugador acumula historial en `team_players`/`player_stats`. Al registrarse, **reclama** ese registro y, tras aprobación humana, hereda el historial.

Migración `2026_06_28_000001`: tabla `profile_claims` (`user_id`, `club_player_id`, `club_id`, `document`, `status` pending/escalated/approved/rejected, `resolved_by_user_id`, timestamps, `resolution_note`).

| Componente | Rol |
|---|---|
| `App\Models\Torneos\ProfileClaim` | Scopes `open`/`pending`/`escalated` |
| `App\Services\Torneos\ProfileClaimService` | Orquesta el ciclo |
| `Torneos\ProfileClaimController` | Jugador (`/reclamos`) + capitán (`/reclamos/aprobaciones`) |
| `Admin\Torneos\ProfileClaimController` | Bandeja de escalados `/admin/torneos/reclamos` |

**Detección automática**: registros `por_verificar` sin cuenta cuyo documento coincide con `users.document` (normalizado). Se dispara al registrarse (documento opcional) y al cargar/cambiar documento en el perfil → banner "Reclamar mi perfil".

**Ciclo** (`ProfileClaimService`):
- `claim` — valida documento, sin reclamo vivo, no es ya miembro. Nace `pending`; sin capitán activo → `escalated`.
- `approve` — transaccional. Autoriza capitán (si pendiente) o admin (siempre). Vincula `club_player`, **hereda historial** (`transferHistory`: backfill de `team_players` por documento), cierra reclamo, rechaza otros vivos. Post-commit refresca career stats + fair play.
- `reject` — sin cambios. `escalate` — manual si el capitán no responde.

**Garantías**: nunca vincula sin aprobación humana; un registro no admite dos reclamos vivos; documento aprobado deja de ser candidato.

---

## 10. Tarjetas PNG, WhatsApp y OG tags (TX-2)

Sin migraciones, sin cambios a lógica de negocio de Torneos.

| Componente | Rol |
|---|---|
| `ShareCardPngService` | GD: gradiente 1080×1080, texto TTF con fallback bitmap, lookup multi-ruta de fuente. Guarda en `cards/` del disco `MEDIA_DISK` |
| `GenerateShareCardPng` (job) | Encolable (`database`); con `QUEUE_CONNECTION=sync` corre en el mismo request |
| `TournamentShareController::cardPng`/`matchCardPng` | Sirve desde caché o genera al vuelo; degrada a SVG si no hay GD |
| `Social\FriendlyMatchShareController` | Tarjeta SVG/PNG de amistoso `jugado`, sin auth |

Rutas: `torneos.public.img.png`, `torneos.public.img.match.png`, `social.amistosos.img.card`, `social.amistosos.img.png`.

**WhatsApp**: botón nativo (`wa.me`) en portal público de torneo, perfil de club, "Mis amistosos". Menú "Imágenes" con columnas SVG/PNG.

**OG tags**: ficha pública de jugador (nombre+nivel+ciudad+PJ+goles, avatar) y perfil de club (nombre+torneos+partidos+ciudad, escudo). Soporte completo en `layouts/app.blade.php` vía `@yield`.

**Configuración de fuente en Hostinger**: `SHARE_CARD_FONT_PATH` (env) → `resources/fonts/Inter-Bold.ttf` → paths de sistema Linux/Windows → fallback bitmap GD.

---

## 11. Navegación v3 — nav agrupado, dashboard de Inicio, buscador global (UX-1)

Sin migraciones. Rediseño de navegación: de 11 enlaces planos a 4 dominios + herramientas transversales (patrón LinkedIn/Instagram).

### Estructura del nav (`components/nav.blade.php`)

**Barra principal (desktop), 4 dominios** vía `<x-nav-dropdown>`:

| Grupo | Contenido |
|---|---|
| 🏠 Inicio | enlace directo → `dashboard` |
| ⚽ Jugar ▾ | Oportunidades · Amistosos · Modo rápido ⚡ · Agenda |
| 🏆 Competir ▾ | Mis Torneos · Buscar Torneo · Ranking de la plataforma |
| 👥 Comunidad ▾ | Canchas · Buscar jugadores y clubes |

**Header derecho (siempre visible)**: 🔍 Buscar (panel Alpine, submit a `social.search`), 🔔 Feed (badge no leídos), 💬 Mensajes (badge), 👤 Avatar ▾ (hub: Mi Carrera, Mis Equipos, tema, Configurar perfil, Reclamar mi perfil, instalar PWA, Salir).

### Dashboard de Inicio (`DashboardController` + `inicio.blade.php`)

`/dashboard` es vista real (antes redirect), igual para todo usuario autenticado. Solo lectura, reutiliza servicios existentes: saludo + acciones rápidas, recordatorios (convocatoria pendiente / resultado pendiente), "Tu semana" (agenda ≥ hoy, máx 8), "Sugeridas para vos" (oportunidades por ciudad), "Novedades" (preview de Feed, sin marcar leído).

### Buscador global (`GlobalSearchController`, `/buscar`)

Con término ≥2 caracteres cruza Jugadores (nunca expone email/teléfono/documento) / Clubes / Torneos (`visibility=public`) / Canchas activas. Máx 8 por grupo.

> ⚠️ Gotcha Blade: `@if` pegado a una palabra (`Cancha@if(...)`) no compila como directiva pero su `@endif` sí, dejando un `endif` huérfano. Usar ternario o separar con carácter no-palabra.

---

> Historial detallado sesión por sesión en `docs/HISTORIAL_SESIONES.md`. Visión de producto de Social en `PROPUESTA_FUTGO_SOCIAL_v3.md`.
