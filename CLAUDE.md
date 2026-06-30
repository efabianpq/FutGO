# CLAUDE.md — FutGO v2

## 1. Proyecto
FutGO — plataforma de gestión de torneos deportivos y pronósticos.
URL local: http://futgo.test:8080

## 2. Módulos
- **Polla Mundial:** CONGELADO — no tocar.
- **Torneos:** v2.1 completo (Sesiones A–G). 458 tests passing.
- **FutGO Social:** Fase 1 **COMPLETA** — modelo de datos base (S1-A) + **Oportunidades** publicar/responder/aceptar (S1-B) + **Amistosos** con doble confirmación, disputa/escalamiento/resolución admin, cancelación con penalización (S1-C) + **Confiabilidad**: score, penalizaciones y pausa automática (S1-D) + **Seguir entidades + Feed de sistema** con contador de no leídos en el navbar (S1-E) + **Moderación** panel admin, ocultamiento, suspensión; **fichas públicas** de jugador `/j/{futgo_id}` y club extendida; score de confiabilidad con umbral de visibilidad (S1-F, ver §7.1 y §11). Fase 2 **COMPLETA**: **"Jugué con vos"** (historial de partidos compartidos derivado + acciones retar/invitar) y **Agenda deportiva unificada** (S2-A, ver §7.2 y §11) + **Mensajería libre** en conversaciones existentes (S2-B: chat sin tiempo real vinculado a oportunidad aceptada o amistoso confirmado, primer mensaje estructurado, compartir contacto explícito, reporte de mensaje; ver §7.3 y §11). **Fase 3 COMPLETA**: **Recomendaciones por reglas (sin ML) + modo rápido** (S3-A: sugerencias de rivales compatibles, recategorización de nivel, oportunidad express con vencimiento corto, historial de compatibilidad head-to-head; ver §7.4 y §11) + **Venues/canchas** catálogo compartido (S3-B: registro por cualquier usuario, perfil público `/c/{slug}`, búsqueda por ciudad con autocompletado, vinculación a amistosos y oportunidades BUSCAR_RIVAL; ver §7.5 y §11). **Sesión TX-1**: **Reclamo de perfil** de jugadores `por_verificar` (deuda #2, ver §12). **Sesión TX-2**: tarjetas PNG, WhatsApp y OG tags (deuda #8, ver §13). **Sesión UX-1**: **rediseño de navegación (nav v3)** — bar reagrupado en 4 dominios + header transversal (buscar/Feed/Mensajes/avatar), **dashboard de Inicio** y **buscador global** (ver §14). **620 tests passing** (la suite quedó en verde tras UX-1). Pendiente: score de confiabilidad en tabla de perfiles; eventos de Feed para seguidores de un torneo. Visión en `PROPUESTA_FUTGO_SOCIAL_v3.md`.

## 3. Stack
- PHP 8.3.30 (Laragon) · Laravel 11.46 · MySQL 8.0.30
- Frontend: Blade + Alpine.js 3 + Tailwind 3 + Vite 5
- DB local: `futgo` / root / sin password · Tests: SQLite in-memory
- Apache puerto 8080

## 4. PATH Laragon (PowerShell — prepender antes de cualquier comando)
```
$env:Path = "C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64;C:\laragon\bin\composer;C:\laragon\bin\mysql\mysql-8.0.30-winx64\bin;$env:Path"
```

## 5. Comandos frecuentes
```
php artisan test
php artisan migrate --seed
php artisan optimize:clear
npm run build
php artisan serve --port=8001   # si el vhost no responde
```

## 6. Convenciones
- Idioma: español, voseo
- Commits: `tipo: descripción corta en español`
- `Co-Authored-By: Claude Sonnet 4.6 <noreply@anthropic.com>`

---

## 7. Modelo de datos — tablas clave

| Tabla | Rol |
|---|---|
| `users` | `futgo_id` (FG-XXXXXX), `avatar_url`, `document`, `modules`, `role` |
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

### 7.1 FutGO Social — Fase 1 (modelo de datos implementado, sesión S1-A)

Namespace de modelos: `App\Models\Social\*`. Migraciones `2026_06_25_000001..011`.
Tipos polimórficos usan **morph map** (`AppServiceProvider`): `user`, `club`, `tournament`, `opportunity`, `friendly_match`, `message` (alias estables en vez de FQCN).

| Tabla | Modelo | Rol |
|---|---|---|
| `users.play_level` / `clubs.play_level` | `Concerns\HasPlayLevel` | Nivel declarado (recreativo/intermedio/competitivo/elite_amateur). Nullable. **Filtro obligatorio del matching**, no decorativo |
| `opportunities` | `Social\Opportunity` | Entidad central. `type` y `status` STRING (extensible sin migración). Creador `user_id` y/o `club_id`. `payload` JSON tipado. Tipos: BUSCAR_RIVAL/JUGADOR/REFUERZO/EQUIPO |
| `opportunity_responses` | `Social\OpportunityResponse` | Respuesta a una oportunidad. Estados pendiente/aceptada/rechazada/contrapropuesta. Una sola aceptada (garantía en servicio) |
| `friendly_matches` | `Social\FriendlyMatch` | Amistoso confirmado, sin `tournament_id`. **Doble confirmación**: cada club reporta marcador; coinciden→`jugado`/`acordado`, difieren→`en_disputa` (no afecta reputación). Ver `applyAgreementFromReports()` |
| `reliability_events` | `Social\ReliabilityEvent` | Eventos polimórficos (subject user/club): no_show, cancelacion_tardia, respuesta_rapida, calificacion_+/−. Referencia opcional a oportunidad/partido |
| `reliability_scores` | `Social\ReliabilityScore` | Cache 0-100 por sujeto (patrón `fair_play_scores`). `is_paused`: regla 2 no-shows/30d → pausa manual |
| `follows` | `Social\Follow` | Polimórfica única: usuario sigue club/jugador/torneo. Único por (follower, followable). Alimenta el Feed |
| `conversations` | `Social\Conversation` | Hilo, vínculo opcional (morph) a opportunity/friendly_match. Esquema listo para mensajería libre Fase 2 |
| `conversation_participants` | `Social\ConversationParticipant` | Participante (user y/o club), `last_read_at` |
| `messages` | `Social\Message` | Mensaje. `type` structured (MVP) / free (Fase 2) |
| `content_reports` | `Social\ContentReport` | Moderación día-uno. Reporte polimórfico (opportunity/message/user/club), revisión admin |

> Score de confiabilidad implementado en S1-D (`ReliabilityService`). Moderación y perfiles públicos en S1-F. Mensajería libre pendiente Fase 2. Tests del modelo: `tests/Feature/Social/SocialModelTest.php` (20).

#### Flujo de Oportunidades (Sesión S1-B)

`App\Services\Social\OpportunityService` orquesta todo el ciclo:
- `publish(User, array)` — el creador es club (RIVAL/JUGADOR/REFUERZO, exige capitanía) o jugador (EQUIPO). `required_level` obligatorio (filtro de matching). Deriva `expires_at` de la ventana si no se pasa.
- `respond(Opportunity, User, array)` — no se responde la propia; RIVAL/EQUIPO responden como club, JUGADOR/REFUERZO como jugador; sin duplicar respuesta pendiente.
- `accept(OpportunityResponse)` — **transaccional con `lockForUpdate`**: si falla la creación de la entidad, la oportunidad no se cierra. Según el tipo crea: RIVAL→`FriendlyMatch` confirmado (cierra); JUGADOR→fila en `club_players` + descuenta `payload['cupos']` (sigue abierta si quedan, varias aceptadas posibles); REFUERZO→`payload['assignment']` puntual sin tocar plantilla (cierra); EQUIPO→suma al jugador publicante a la plantilla del club respondente (cierra).
- `reject` / `counter` (contrapropuesta → oportunidad `en_negociacion`).
- `cancel(Opportunity, reason)` — el amistoso confirmado se cancela siempre; si la cancelación cae ≤24h antes del partido, genera `reliability_event` `cancelacion_tardia` sobre el club. Cerradas-con-amistoso siguen siendo cancelables.
- `expireDue()` — marca `vencida` lo que pasó su vigencia (comando `social:expire-opportunities`, scheduler hourly).

Moderación día-uno: `App\Support\Social\ContentFilter` + regla `App\Rules\CleanText` (palabras prohibidas) en descripción y mensajes; longitud máxima por campo; `content_reports` vía botón "Reportar". Exploración pública (`/oportunidades`, sin auth) con filtro de nivel obligatorio (default = nivel del que mira, `?nivel=todos` lo fuerza) y eager loading sin N+1. Controlador `Social\OpportunityController`; vistas en `resources/views/social/oportunidades/`. Tests: `tests/Feature/Social/OpportunityFlowTest.php` (22).

#### Ciclo de vida del Amistoso (Sesión S1-C)

Migración aditiva `2026_06_25_000012` agrega a `friendly_matches`: `home/away_reported_at`, `escalated_at`/`escalated_by_club_id`, `resolved_by_user_id`/`resolved_at`. `App\Services\Social\FriendlyMatchService` orquesta:
- `reportResult(FriendlyMatch, Club reporter, home, away)` — **transaccional con `lockForUpdate`**: cada capitán reporta su marcador; `applyAgreementFromReports()` resuelve. Coinciden → `jugado`/`acordado` + `final_*` (el resultado se asienta en el historial — derivado read-time — en la misma transacción que fija el estado). Difieren → `en_disputa`. Sirve también para RECTIFICAR (re-reportar en disputa).
- `escalate(FriendlyMatch, Club)` — solo en disputa; marca `escalated_at`/`escalated_by_club_id`.
- `resolveByAdmin(FriendlyMatch, User admin, home, away)` — el admin de plataforma fija el resultado oficial → `jugado`/`acordado`, `resolved_by_user_id`.
- `cancel(FriendlyMatch, Club by, reason)` — solo `confirmado`; si ≤24h antes del partido genera `reliability_event` `cancelacion_tardia`. **Es la única fuente de verdad de cancelación de amistosos: `OpportunityService::cancel` delega acá.**
- Métricas read-time (patrón de agregación en lectura, sin tabla de cache): `userFriendlies`/`userMetrics` (total partidos torneos+amistosos, % presentación = jugados/convocatorias aceptadas, récord amistoso) y `clubFriendlies`/`clubMetrics`. `jugado` solo cuenta; disputas/cancelados quedan fuera del historial activo y de las métricas (un partido en disputa o cancelado no afecta reputación).

Capitán: `Social\FriendlyMatchController` ("Mis amistosos" `/amistosos`, report/escalate/cancel — solo el capitán de un equipo participante actúa). Admin: `Admin\Social\FriendlyMatchController` (`/admin/amistosos`: bandeja de disputas + resolución + historial de cancelaciones). Integrado a Mi Carrera (sección Amistosos + métricas sociales) y al perfil del club. Enlaces "Amistosos" en el nav y en el admin nav. Vistas en `resources/views/social/amistosos/` y `resources/views/admin/social/`. Tests: `tests/Feature/Social/FriendlyMatchTest.php` (10).

#### Seguir entidades + Feed de sistema (Sesión S1-E)

Migración aditiva `2026_06_26_000001`: tabla `feed_events` + columnas `users.city` y `users.feed_last_read_at`. Morph map extendido con `achievement` y `feed_event`.

| Tabla | Modelo | Rol |
|---|---|---|
| `feed_events` | `Social\FeedEvent` | UN registro por evento del sistema (nunca uno por usuario). `actor`/`subject` son las dos entidades que el evento conecta; `city`/`required_level` son la regla de distribución; `payload` JSON denormalizado para renderizar sin joins. Tipos: oportunidad_publicada/aceptada, amistoso_confirmado, resultado_amistoso, resultado_torneo, logro_desbloqueado |
| `follows` (de S1-A) | `Social\Follow` | Implementada la UI/controller acá: toggle libre sin aprobación ni notificación |

**Seguir** (`FollowService` + `Social\FollowController`): un único endpoint TOGGLE `POST /seguir/{type}/{id}` (`type` = club|user|tournament). Componente reutilizable `<x-social.follow-button :followable type>` en el perfil del club, el portal público del torneo y disponible para jugadores. Un jugador no se sigue a sí mismo. `Tournament::followers()` agregado (morphMany).

**Feed** (`FeedService` + `Social\FeedController`, `/feed`): **relevancia calculada en lectura** (sin filas por usuario) — un evento es relevante si el usuario sigue a su `actor` o `subject`, **o** coincide con su `city` + `play_level` (nivel nulo del evento = para todos en esa ciudad). Feed vacío (usuario nuevo) → `entryOpportunities()` muestra oportunidades activas de su ciudad como contenido de entrada. Paginado (15/pág).

**Generación de eventos** (desde los servicios que ya existen, **no bloqueante** — `FeedService::record()` atrapa toda excepción y devuelve null; la acción principal nunca falla por el Feed; se registra post-commit): `OpportunityService::publish` (oportunidad_publicada, broadcast ciudad/nivel) y `accept` (oportunidad_aceptada + amistoso_confirmado para RIVAL); `FriendlyMatchService::reportResult`/`resolveByAdmin` (resultado_amistoso); `AchievementService::evaluateForUser` (logro_desbloqueado, solo seguidores del jugador); `MatchResultController::store`/`storeWalkover` (resultado_torneo, broadcast por ciudad del torneo).

**Notificaciones en plataforma**: contador de no leídos en el navbar (badge) = eventos relevantes posteriores a `users.feed_last_read_at` (O(1) en almacenamiento, sin tabla de lecturas). Se comparte al componente `components.nav` vía `View::composer` en `AppServiceProvider`. Abrir el Feed (1ª página) o `POST /feed/leido` marca todo como leído → el badge baja a cero. Ciudad/nivel editables desde el perfil. Tests: `tests/Feature/Social/FollowAndFeedTest.php` (9).

### 7.2 FutGO Social — Fase 2 · "Jugué con vos" + Agenda deportiva (Sesión S2-A)

Sin migraciones nuevas: ambas features son **derivadas en lectura** de datos que ya existen.

**"Jugué con vos"** (`App\Services\Social\PlayedWithService`): historial de partidos compartidos entre jugadores, sin solicitud ni aceptación.
- `sharedPlayers(User)` → colección `{user, shared}` ordenada por partidos compartidos (desc). **Sin N+1**: 3 queries fijas (alineaciones de torneo + amistosos + carga de usuarios), sin importar cuántos jugadores haya.
- `sharedCount(User $a, User $b)` → conteo entre dos jugadores (mismas queries acotadas al otro id).
- Dos fuentes con agregación (`COUNT(DISTINCT` del id del partido, portable SQLite/MySQL — sin CONCAT ni CTE): **torneos** = self-join de `match_lineups` sobre `match_id` (fuente de verdad de quién jugó, rivales o compañeros); **amistosos** = self-join de `club_players` (plantilla actual) sobre amistosos `jugado`. Nota: para amistosos la participación se deriva de la plantilla **actual** del club (no hay alineación histórica de amistosos).
- **Acciones directas** sobre el dato derivado (ficha pública `/j/{futgo_id}` y Mi Carrera): "Retar a un amistoso" (link a crear `BUSCAR_RIVAL` pre-completada) e "Invitar a mi equipo" (link a crear `BUSCAR_JUGADOR` pre-completada). El pre-completado pasa `?tipo=…&target={futgo_id}`: `OpportunityController::create` resuelve el `target` (columnas públicas), pre-llena tipo/ciudad/nivel y deja un hidden `target_user_id`; `store` lo guarda en `payload.directed_to_user_id`/`directed_to_name` (la oportunidad sigue siendo pública; solo queda registrado a quién se apuntó). En el perfil público: conteo "Jugaste X veces con [nombre]" para el visitante autenticado. En Mi Carrera: sección "Jugué con" (top 12) con conteo y acciones.

**Agenda deportiva unificada** (`App\Services\Social\SportsAgendaService`, `Social\AgendaController`, `/agenda`, nav "Agenda"): vista de **lectura** que agrega por día y en **orden cronológico** (sin fecha al final) todo lo pendiente/programado del usuario:
- Partidos de torneo de sus equipos (`scheduled`/`live`) + su convocatoria — convocatoria `convocado` muestra **confirmar/declinar inline** (reusa `torneos.convocatoria.respond`).
- Amistosos `confirmado` de sus clubs — si `scheduled_at` ya pasó, **recordatorio de cargar resultado** (link a `/amistosos`).
- Oportunidades propias activas próximas a vencer (ventana 7 días).
- **Excluye lo cancelado**: torneos con `status='cancelled'` y amistosos `cancelado` no aparecen. Cada ítem es un objeto homogéneo `{kind, date, title, subtitle, status, action, …}`.

Tests: `tests/Feature/Social/PlayedWithAndAgendaTest.php` (8) — historial compartido (torneo + amistoso), "retar" pre-completa y persiste el destinatario, agenda en orden cronológico, exclusión de cancelados, convocatoria pendiente inline, render de recordatorios.

### 7.3 FutGO Social — Fase 2 · Mensajería libre en conversaciones existentes (Sesión S2-B)

Sin migraciones nuevas: el esquema de `conversations`/`conversation_participants`/`messages` se diseñó desde S1-A. Esta sesión activa su uso. La columna `messages.is_hidden` (de la migración de moderación `2026_06_26_000002`) ahora se respeta en lectura.

`App\Services\Social\ConversationService` orquesta el ciclo:
- **Creación automática** (desde `OpportunityService::accept`, dentro de su transacción): `ensureForAcceptedResponse(Opportunity, OpportunityResponse)`. Una conversación SIEMPRE nace de un acuerdo previo — no hay forma de iniciar un chat con un desconocido. Para BUSCAR_RIVAL el acuerdo es un amistoso confirmado → la conversación se vincula al **amistoso** (subject=`friendly_match`, participantes = los dos capitanes); en el resto se vincula a la **oportunidad** (subject=`opportunity`, participantes = publicante + respondente). `firstOrCreate` por subject → idempotente. El **primer mensaje es estructurado** (`type=structured`, sin emisor humano: `Message::isSystem()`), lo genera el sistema y resume el acuerdo (voseo).
- `ensureForFriendly(FriendlyMatch)` — variante directa para amistosos (también la usa el caso RIVAL).
- `postMessage(Conversation, User, body, ?asClubId)` — persiste un mensaje **libre** (`type=free`), actualiza `last_message_at` y marca leído para el emisor. El club que firma sale del participante (`participantFor`).
- `markRead` / `hasUnread` / `unreadCount(User)` — el no leído se calcula comparando `conversation_participants.last_read_at` con `conversations.last_message_at` (sin tabla de lecturas). `unreadCount` alimenta el badge "Mensajes" del navbar (vía `View::composer`, junto al del Feed).
- `forUser(User)` — lista de conversaciones del usuario (recientes primero) con subject, último mensaje y participantes eager-cargados. Scope `Conversation::forUser`.

`Social\ConversationController` (`/mensajes`, bajo `auth`): `index` (lista), `show` (hilo de chat — recarga simple, sin tiempo real; marca leído al abrir), `store` (enviar mensaje libre), `shareContact` (publica el `phone_whatsapp` del usuario como un mensaje libre más — decisión **explícita**, el sistema nunca lo expone solo; avisa si no hay teléfono cargado), `reportMessage` (genera `content_report` con `reportable_type='message'`). **Acceso por participación validado en el controlador** (`Conversation::hasParticipant`, aborta 403), nunca solo en la vista. Mensajes con `max:1000` + `CleanText` (filtro de palabras prohibidas). No se reporta el propio mensaje (403). Los mensajes ocultos por moderación se filtran con `Message::scopeVisible()`. Vistas en `resources/views/social/conversaciones/` (index + show). Enlace "Mensajes" con badge en el nav (desktop + mobile). Tests: `tests/Feature/Social/ConversationTest.php` (10).

### 7.4 FutGO Social — Fase 3 · Recomendaciones por reglas + modo rápido (Sesión S3-A)

Capa de **inteligencia por reglas (sin ML)**: filtros explícitos + score compuesto, **determinista y auditable** (cada decisión sale de una regla documentada en código, no de una caja negra). Migraciones aditivas `2026_06_27_000001` (`opportunities.is_express`) y `2026_06_27_000002` (`clubs.level_suggestion_dismissed_at`).

`App\Services\Social\SuggestionService` orquesta las dos recomendaciones:
- **Clubs compatibles para un BUSCAR_RIVAL** (`compatibleRivalsFor(Opportunity)` / `compatibleRivals(excludeClubId, city, level)`): sugiere hasta `MAX_SUGGESTIONS = 5` clubs **antes de que lleguen respuestas**. Filtros DUROS: misma ciudad (OBLIGATORIO — la ciudad del club se deriva de su capitán, `users.city`), nivel **igual o adyacente** (±1 en el ranking de `PLAY_LEVELS`), **activo recientemente** (publicó o respondió una oportunidad ≤ `ACTIVITY_WINDOW_DAYS = 30`), confiabilidad ≥ `MIN_RELIABILITY = 60`, y **nunca un club pausado** por no-shows (`reliability_scores.is_paused`). Orden por **score compuesto** = confiabilidad + bonus de cercanía de nivel (exacto +20 / adyacente +10) + bonus de recencia (≤7d +15 / ≤30d +8), desempate estable por id asc. Sin N+1: 1 query de candidatos + 1 de scores + 2 de actividad agregada. Cada sugerencia trae `{club, reliability, score, reasons[]}` (razones legibles para la UI). Se muestra al dueño en la ficha de su oportunidad (`oportunidades.show`) cuando es BUSCAR_RIVAL abierto.
- **Recategorización de nivel** (`levelRecategorization(Club)`): si el club ganó ≥ `RECATEGORIZATION_WINS = 3` amistosos **jugados** (resultado confirmado) contra rivales de nivel **estrictamente superior**, sugiere subir al nivel siguiente. Solo un AVISO para el capitán (no fuerza nada); se muestra en el perfil del club a capitán/admin con opción **Ignorar** → persiste `clubs.level_suggestion_dismissed_at` y no vuelve a aparecer (`ClubController::dismissLevelSuggestion`, ruta `torneos.clubes.level-suggestion.dismiss`). Devuelve null si: sin nivel declarado, ya en el máximo, pocas victorias, o ya ignorado.

**Modo rápido (express)**: oportunidad de vigencia corta para necesidades de último momento ("necesito rival para mañana"). `opportunities.is_express` (boolean queryable). Formulario simplificado `OpportunityController::createExpress` (`/oportunidades/rapida`, ruta `social.oportunidades.express`): siempre BUSCAR_RIVAL, menos campos, pre-completa club/ciudad/nivel con los datos del usuario y propone la disponibilidad más cercana (mañana 20:00). En `store`/`buildPublishData` el express fija `expires_at = window_start`, de modo que **vence solo al llegar la fecha** vía el `expireDue()` y el scheduler `social:expire-opportunities` ya existentes (sin lógica nueva). Se destaca con **badge "⚡ Urgente"** en el listado (`oportunidades.index`), en la ficha (`oportunidades.show`) y en el Feed (payload `express` del evento `oportunidad_publicada`). Helper `Opportunity::isExpress()` + scope `Opportunity::scopeExpress()`.

**Historial de compatibilidad (head-to-head)**: derivado en lectura de `FriendlyMatch` (sin tabla nueva). `FriendlyMatchService::clubHeadToHead(Club)` agrupa las perspectivas de `clubFriendlies` por rival → `{opponent, count, won, drawn, lost}` ordenado por enfrentamientos; `headToHeadCount(clubA, clubB)` cuenta directos. Se muestra en el perfil del club ("Ya jugaron antes"). Tests: `tests/Feature/Social/SuggestionAndExpressTest.php` (9).

### 7.5 FutGO Social — Fase 3 · Venues / canchas (Sesión S3-B)

Entidad compartida `venues`: catálogo de instalaciones deportivas mantenido por la comunidad. **No pertenece a ningún club ni torneo.** Cualquier usuario registrado puede proponer una cancha; solo el registrador o un admin global puede editarla.

Migraciones: `2026_06_27_000003_create_venues_table` (tabla `venues`) y `2026_06_27_000004_add_venue_to_friendly_matches_and_opportunities` (`venue_id` nullable en `friendly_matches` y `opportunities`).

| Tabla | Modelo | Rol |
|---|---|---|
| `venues` | `App\Models\Social\Venue` | Cancha del catálogo. `name`, `slug` único auto-generado, `city`, `address`, `surface_type`, `approx_capacity`, `maps_url`, `photos` JSON, `registered_by_user_id`, `is_active` |
| `friendly_matches.venue_id` | FK nullable → `venues` | Cancha donde se juega el amistoso. Se copia automáticamente al crear el FriendlyMatch desde una Opportunity aceptada (BUSCAR_RIVAL) |
| `opportunities.venue_id` | FK nullable → `venues` | Cancha propuesta en la oportunidad (BUSCAR_RIVAL) |

**Flujo**:
- `VenueController` (`/canchas`): `index` (listado paginado con filtro ciudad/búsqueda, público), `show` (perfil `/c/{slug}`, sin auth: datos, partidos jugados allí, amistosos próximos con "disponibilidad"), `search` (JSON con autocompletado para Alpine.js, filtra por ciudad+término), `create`/`store`/`edit`/`update` (auth). Morph map alias `venue` registrado en `AppServiceProvider`.
- **Widget de búsqueda** `social.canchas._search_widget`: componente Alpine.js reutilizable. Llama a `/canchas/buscar?ciudad=X&q=Y`, muestra resultados inline; si no existe la cancha, link "Registrarla ahora". Integrado en el form de publicación de oportunidad BUSCAR_RIVAL (reemplaza el campo de texto libre, que queda como fallback).
- **Propagación automática**: `OpportunityService::acceptRival` copia `opportunity.venue_id` al `FriendlyMatch` creado. El texto libre `cancha_propuesta` en `payload` sigue disponible como fallback.
- **Perfil de cancha**: partidos jugados = `FriendlyMatch` con `status=jugado` vinculados vía `venue_id`; próximos = `confirmado` + `scheduled_at >= now()`. Badge de "Ocupada/Disponible" derivado de upcoming.
- `Venue::canBeEditedBy(User)`: solo registrador o admin.
- `Venue::generateUniqueSlug(name)`: slug automático con sufijo numérico para evitar colisiones.
- Enlace "Canchas" agregado al nav (entre Amistosos y Buscar Torneo).
- Tests: `tests/Feature/Social/VenueTest.php` (13).

---

## 8. Servicios y reglas de negocio críticas

**FixtureGeneratorService**
- Valida: torneo en `open`, sin fixture previo, equipos suficientes
- `knockout_only` exige potencia de 2 (≥4 equipos)
- Cruce estándar: A1vB2 / B1vA2 (si classifies==2 y grupos pares)
- Deja el torneo en `in_progress`

**StandingsCalculatorService**
- delete+insert (no updateOrCreate) — recálculo limpio
- Solo partidos `finished`
- Desempate: `tiebreaker_order` del torneo → fair_play → sorteo determinista (crc32+md5, auditado en `standing_draws`)
- Bloquea recálculo si la fase está `completed`

**PhaseClosureService**
- Cierra fase de grupos → activa siguiente fase knockout
- Tercer puesto se puebla al cerrar semifinal (no al cerrar grupos)
- Bloquea si hay partidos pendientes

**PlayerStatsCalculatorService**
- `match_lineups` es la fuente de verdad para PJ/minutos/clean_sheets/V-E-D
- `match_events` para goles/asist/tarjetas
- MVP desde `tournament_matches.mvp_team_player_id` (solo si `mvp_enabled`)

**PlayerCareerStatsService**
- `refreshForUser/Team/Tournament`
- Solo jugadores registrados (user_id no nulo)
- Se llama tras cada `store`/`destroy` de resultado y al finalizar torneo

**ReputationService** (orquesta al finalizar torneo)
- `RankingService::rebuild()` — fórmula: goles·4 + asist·2 + MVP·6 + victorias·3 + vallas·2 + PJ·1 + fair_play·0.5
- `FairPlayService` — fórmula jugador: max(0, 100 − 3·amarillas − 10·rojas − 5·inasistencias)
- `AchievementService::evaluateForUser` — idempotente (firstOrCreate)

**ClubMembershipService**
- `enroll(club, torneo)` copia plantilla permanente al torneo
- Jugador agregado en `open` → `active`; en `in_progress` → `pending` (requiere aprobación admin)
- Quitar miembro en curso → `inactive` en ese torneo (preserva stats)

**CredentialService**
- QR codifica solo `?fg=FG-XXXXXX&sig=HMAC` — sin datos sensibles
- Firma inválida degrada con aviso (no bloquea)
- Solo jugadores con cuenta tienen credencial

**Scheduler (Hostinger)**
- Único cron cada minuto → `scheduler.sh` → `php artisan schedule:run`
- Comandos activos: `torneos:match-reminders` (hourly), `torneos:rebuild-reputation` (cron), + 3 schedulers de la polla
- `backup:run --only-db` (03:00 diario) + `backup:clean` (03:30) — gestionados por `spatie/laravel-backup`

**Storage de medios**
- Controlado por `MEDIA_DISK` en `.env` (`public` en dev, `r2` en prod)
- `config/filesystems.php` expone disco `r2` (Cloudflare R2, driver S3)
- Todos los controladores usan `config('filesystems.media_disk')` — ninguno hardcodea `'public'`
- Excepción: compatibilidad con URLs antiguas `/storage/...` en `TournamentController::handleImageUploads`

---

## 9. Reglas de identidad y acceso

- Capitán = derivado POR CLUB (`clubs.captain_user_id` + `club_players.is_captain`). No hay rol global.
- `torneo_admin` en `users.role` → acceso a `/admin/torneos` (scoped a sus torneos via `tournament_admins`).
- `admin` global → ve todos los torneos.
- Middleware: `EnsureModule`, `EnsureTorneoAdmin`.
- Portal público `/t/{slug}` — sin auth, solo torneos `visibility=public`.
- Convocatoria y responder convocatoria: capitán arma, jugador confirma/declina desde "Mi Carrera".

---

## 10. Deuda técnica vigente (no resuelta)

| # | Limitación | Impacto |
|---|---|---|
| 1 | Anular partido de eliminatoria ya avanzada no revierte rondas siguientes | Bajo, pero puede dejar standings inconsistentes |
| 2 | ~~Reclamo de perfil para jugadores `por_verificar` sin UI~~ RESUELTO (TX-1, ver §12): flujo de reclamo con aprobación del capitán/admin, hereda historial, credencial y notificaciones | — |
| 3 | Stats de club agregadas en lectura (sin tabla propia) | Aceptable hoy; no escala bien con muchos torneos |
| 4 | Selector de MVP en planilla de resultados sin UI (modelo/pipeline listos) | MVP solo asignable vía código/test |
| 5 | ~~Fotos en disco local, no S3~~ RESUELTO P-0: `MEDIA_DISK=r2` activa Cloudflare R2 | Requiere configurar variables R2_* en prod |
| 6 | Convocatoria previa no pre-llena la alineación | Doble trabajo para capitán/admin |
| 7 | QR con firma inválida igual resuelve al jugador (con aviso) | Decisión de diseño, comunicar a árbitros |
| 8 | ~~Tarjetas compartibles en SVG, no PNG~~ RESUELTO (TX-2, ver §13): endpoints PNG con GD (`/{slug}/img/{card}/png`), tarjeta de amistoso, botón WhatsApp nativo, OG tags en fichas de jugador/club. Degrada a SVG si GD no está disponible. | — |
| 9 | Portal público sin paginación (límite 12/12/10) | Sin paginación para ver el resto |
| 10 | Ranking/fair play no son tiempo real (cache) | Admin puede creerlos desactualizados |
| 11 | ~~`por_verificar` no reciben recordatorios por email~~ RESUELTO (TX-1, ver §12): al reclamar y vincular su cuenta, el jugador entra al fair play del equipo (#12) y recibe recordatorios de partido (#13) | — |

---

## 11. Próximos pasos / Fase activa

**Producción (checklist `INFORME_PRODUCCION_FUTGO_v2_1.md`)**
- ✅ `APP_DEBUG=false` en prod — documentado en `.env.example` con bloque de producción comentado
- ✅ Backups automatizados — `spatie/laravel-backup` integrado, `backup:run --only-db` diario 03:00, rotación 7 días, opcional destino R2 vía `BACKUP_DISK=r2`
- ✅ Storage persistente — `MEDIA_DISK` desacopla dev (public) de prod (r2); disco `r2` configurado en `filesystems.php`
- ✅ Vistas de error — `errors/404.blade.php` y `errors/500.blade.php` con layout propio, voseo, botones de regreso
- ✅ Throttle en login/registro/recuperación y en `/torneos/validar` — named rate limiters en `AppServiceProvider`; 5/min (auth), 3/min (password-reset), 30/min por usuario (credential-validate)
- ✅ N+1 en `StatsController::jugador()` corregido — lineups eager-loaded via `with(['lineups' => fn($q) => ...])`, eliminando query por partido en el map()

**Seguridad y rendimiento (P-1 — resueltos)**
- ✅ Rate limiting autenticación: named limiters `auth` (5/min), `password-reset` (3/min) — `AppServiceProvider`; throttle en POST /login, /register, /forgot-password, /reset-password
- ✅ Rate limiting credencial QR: limiter `credential-validate` (30/min por user id) — aplicado a GET y POST /torneos/validar
- ✅ N+1 StatsController: `lineups` ahora eager-loaded junto a homeTeam, awayTeam, phase, events; map() accede `$match->lineups->first()` sin query adicional
- ✅ Mass assignment User auditado: `ProfileController::update()` usa asignación explícita; `RegisterController::store()` usa array explícito; ningún endpoint usa `$request->all()` sobre User — test de regresión agregado
- Tests: 471 (+13 nuevos) — `tests/Feature/Auth/RateLimitTest.php`, `CredentialRateLimitTest.php`, `StatsEagerLoadTest.php`, `MassAssignmentTest.php`

**FutGO Social (ver `PROPUESTA_FUTGO_SOCIAL_v3.md`)**
- ✅ **Sesión S1-A — modelo de datos Fase 1** (ver §7.1): 11 migraciones (`2026_06_25_000001..011`), 12 modelos en `App\Models\Social\*` + trait `HasPlayLevel`, morph map en `AppServiceProvider`. Migraciones reversibles, sin tocar Torneos/Polla (salvo columnas aditivas `play_level`). 20 tests → **491 passing**
- ✅ **Sesión S1-B — flujo de Oportunidades publicar/responder** (ver §7.1): `OpportunityService` (publish/respond/accept transaccional por tipo/reject/counter/cancel/expireDue), `Social\OpportunityController` + rutas (`social.oportunidades.*`, exploración pública + acciones bajo `auth`), 4 vistas Blade, filtro de contenido (`ContentFilter`/`CleanText`), reporte de contenido, comando `social:expire-opportunities` (scheduler hourly), enlace "Oportunidades" en el nav. 22 tests nuevos → **513 passing**
- ✅ **Sesión S1-C — ciclo de vida del Amistoso + doble confirmación** (ver §7.1): migración aditiva `2026_06_25_000012`, `FriendlyMatchService` (reportResult transaccional / escalate / resolveByAdmin / cancel / métricas read-time), `Social\FriendlyMatchController` ("Mis amistosos") + `Admin\Social\FriendlyMatchController` (disputas + cancelaciones), 2 vistas + integración de amistosos y métricas sociales en Mi Carrera y perfil del club, enlaces de nav. `OpportunityService::cancel` refactorizado para delegar la cancelación del amistoso (única fuente de verdad). 10 tests nuevos → **523 passing**
- ✅ **Sesión S1-D — Confiabilidad: score, penalizaciones y pausa automática**: `ReliabilityService` (`refreshForUser`/`refreshForClub`/`rebuild`/`reactivate`) — clona patrón `FairPlayService`. Fórmula: 100 + delta por evento en ventana 90d; pesos en constante `WEIGHTS` (no_show −20, cancelacion_tardia −10, respuesta_rapida +5, calificacion_positiva +8, calificacion_negativa −12). Pausa automática: 2+ no-shows en 30d → `is_paused=true` en `ReliabilityScore`; bloquea `OpportunityService::publish` con `OpportunityException::paused()`; redirect a `/oportunidades/reactivar` (pantalla de confirmación con checkbox antes de reactivar). Detección automática de no-show: comando `social:detect-no-shows` (scheduler hourly) — detecta amistosos `confirmado` con `scheduled_at` pasado y sin reportes, registra `reliability_event` `no_show` para ambos clubs e idempotente. Comando `social:rebuild-reliability` (scheduler diario 04:00) reconstruye todos. Rutas: `social.oportunidades.reactivar` (GET/POST). 12 tests nuevos → **535 passing**
- ✅ **Sesión S1-E — Seguir entidades + Feed de sistema** (ver §7.1): migración aditiva `2026_06_26_000001` (`feed_events` + `users.city`/`feed_last_read_at`), modelo `Social\FeedEvent`, `FollowService` + `Social\FollowController` (toggle libre club/user/tournament) con componente `<x-social.follow-button>` en perfiles de club y portal de torneo, `FeedService` + `Social\FeedController` (`/feed` paginado, relevancia por follows o ciudad+nivel calculada en lectura, contenido de entrada para usuario nuevo). Generación de eventos NO bloqueante desde los servicios existentes (oportunidades/amistosos/logros/resultados de torneo). Contador de no leídos en el navbar (badge contra `feed_last_read_at`, vía `View::composer`). Ciudad/nivel editables en el perfil. 9 tests nuevos → **544 passing**
- ✅ **Sesión S1-F — Moderación MVP + fichas públicas** (ver §7.1): migración aditiva `2026_06_26_000002` (`users.is_suspended`/`suspended_until`/`suspended_reason`, `opportunities.is_hidden`, `messages.is_hidden`, `content_reports.resolution_action`). `ModerationService` (resolveReport con 3 acciones: dismissed/hidden/suspended; hideEntity; suspendUser/unsuspendUser; trazabilidad completa). `Admin\Social\ModerationController` + panel `/admin/moderacion` (reportes pendientes + historial, enlace en admin nav). `Opportunity::scopeVisible()` + `abort_if(is_hidden, 404)` en show — contenido oculto desaparece del listado público sin borrarse. `User::isSuspended()` con chequeo de vencimiento; check en `OpportunityService::publish` y `::respond` (excepción `OpportunityException::suspended()`). **Ficha pública de jugador** `/j/{futgo_id}` (`Social\PlayerPublicController`, sin auth, select explícito de columnas, nunca expone email/teléfono/documento): foto, nombre, nivel, ciudad, métricas sociales, logros, historial de temporadas, oportunidades BUSCAR_EQUIPO abiertas, score de confiabilidad solo si ≥ 80, botón seguir. **Ficha pública de club** extendida: nivel declarado en cabecera, links a ficha del jugador por cada miembro, score de confiabilidad del club (siempre para capitán/admin, público si ≥ 80), oportunidades abiertas actuales. **Visibilidad del score de confiabilidad**: umbral `PlayerPublicController::MIN_SCORE_VISIBLE = 80` configurable; score propio siempre visible en el perfil privado. 16 tests nuevos → **560 passing**
- ✅ **Fase 1 COMPLETA** — S1-A a S1-F implementadas.
- ✅ **Sesión S2-A — "Jugué con vos" + Agenda deportiva** (ver §7.2): sin migraciones (todo derivado en lectura). `PlayedWithService` (historial de partidos compartidos sin N+1: torneos vía `match_lineups` + amistosos vía `club_players`; `sharedPlayers`/`sharedCount`) integrado a la ficha pública del jugador (conteo "Jugaste X veces" + acciones) y a Mi Carrera (sección "Jugué con"). Acciones **Retar** (`BUSCAR_RIVAL`) / **Invitar** (`BUSCAR_JUGADOR`) pre-completan el alta de oportunidad (`?tipo=…&target={futgo_id}` → tipo/ciudad/nivel pre-llenados + `payload.directed_to_user_id`). `SportsAgendaService` + `Social\AgendaController` (`/agenda`, nav "Agenda"): agenda unificada por día/cronológica (partidos de torneo + convocatoria inline, amistosos confirmados con recordatorio de carga, oportunidades por vencer), excluye torneos/amistosos cancelados. 8 tests nuevos → **568 passing**.
- ✅ **Sesión S2-B — Mensajería libre en conversaciones existentes** (ver §7.3): sin migraciones (esquema de conversaciones diseñado desde S1-A). `ConversationService` (creación automática al aceptar oportunidad/confirmar amistoso con primer mensaje estructurado del sistema, `postMessage` libre, `markRead`/`hasUnread`/`unreadCount`, `forUser`). `Social\ConversationController` (`/mensajes`: index/show/store/shareContact/reportMessage), acceso por participación validado en el controller (`hasParticipant` → 403), `max:1000`+`CleanText`, reporte de mensaje → `content_report`, `Message::scopeVisible()` respeta `is_hidden`. Hook en `OpportunityService::accept` (dentro de la transacción). Badge "Mensajes" en el nav (composer junto al del Feed). 2 vistas Blade. 10 tests nuevos → **578 passing**.
- ✅ **Sesión S3-A — Recomendaciones por reglas + modo rápido** (ver §7.4): migraciones aditivas `2026_06_27_000001` (`opportunities.is_express`) y `2026_06_27_000002` (`clubs.level_suggestion_dismissed_at`). `SuggestionService` (sugerencias de rivales compatibles por filtros duros + score compuesto determinista, excluye pausados; sugerencia de recategorización de nivel tras N victorias contra nivel superior, con dismiss persistido). **Modo rápido** (express) con form simplificado pre-completado (`/oportunidades/rapida`), badge de urgencia en listado/ficha/Feed, vencimiento automático vía `expires_at`. **Head-to-head** derivado de FriendlyMatch (`clubHeadToHead`/`headToHeadCount`) en el perfil del club. Integración en `OpportunityController` (sugerencias en show + createExpress), `ClubController` (recategorización + head-to-head + dismiss). 9 tests nuevos → **587 passing**.
- ✅ **Sesión S3-B — Venues/canchas** (ver §7.5): migraciones `2026_06_27_000003` (tabla `venues`) y `2026_06_27_000004` (`venue_id` nullable en `friendly_matches` y `opportunities`). `App\Models\Social\Venue` con slug auto-generado, `canBeEditedBy`, `surfaceLabel`, helpers de partidos/próximos. `Social\VenueController` (index/show público, search JSON, create/store/edit/update auth). Widget Alpine.js `social.canchas._search_widget` (autocompletado por ciudad/término, link "Registrarla ahora" si no existe). `OpportunityService::acceptRival` copia `venue_id` al FriendlyMatch. Widget integrado en form de BUSCAR_RIVAL (texto libre como fallback). Perfil `/c/{slug}` sin auth con partidos jugados y disponibilidad. Enlace "Canchas" en el nav. Morph map alias `venue`. 13 tests nuevos → **600 passing**.
- ✅ **Fase 3 COMPLETA** — S3-A + S3-B implementadas.
- ✅ **Sesión TX-1 — Reclamo de perfil de jugadores `por_verificar`** (deuda técnica #2, ver §12): migración `2026_06_28_000001` (tabla `profile_claims`), `ProfileClaim` (Torneos), `ProfileClaimService`, detección automática en registro (documento opcional) y al cargar documento en el perfil, flujo capitán/admin con notificaciones, herencia de historial + refresco de career/fair play. Resuelve deuda #2, #12 (fair play del equipo) y #13/#11 (notificaciones de partido). 7 tests nuevos → **607 passing**.
- ✅ **Sesión TX-2 — Tarjetas PNG, WhatsApp y OG tags** (deuda técnica #8, ver §13): `ShareCardPngService` con GD (gradiente, texto TTF con lookup multi-ruta + fallback bitmap), `GenerateShareCardPng` job encolable. Endpoints `/{slug}/img/{card}/png` y `/{slug}/img/partido/{match}/png` (sin migraciones). Tarjeta SVG de amistoso (`social.share.amistoso`, ruta `social.amistosos.img.card`) + endpoint PNG `social.amistosos.img.png`. Botón "WhatsApp" nativo (`wa.me`) en portal público de torneo y perfil de club. Menú "Imágenes" ampliado con columnas SVG/PNG. OG tags dinámicos en ficha pública de jugador (`og_description` con métricas, `og_image` con avatar) y soporte OG en `layouts.app` (perfil de club con escudo). Font lookup: `SHARE_CARD_FONT_PATH` → `resources/fonts/` → `/usr/share/fonts/` → `C:\Windows\Fonts\` → fallback bitmap. Degrada a SVG si GD no disponible. 13 tests nuevos → **620 passing**.
- ✅ **Sesión UX-1 — Rediseño de navegación (nav v3) + dashboard de Inicio + buscador global** (ver §14): nav reagrupado de 11 enlaces planos a 4 dominios con dropdown (Inicio · Jugar · Competir · Comunidad) + herramientas transversales en el header derecho (🔍 buscar · 🔔 Feed · 💬 Mensajes · avatar = hub de Perfil/cuenta). Nueva pantalla de Inicio (`DashboardController` + `inicio.blade.php`) que agrega agenda/recordatorios/sugeridas/novedades (todo derivado de servicios ya existentes). `GlobalSearchController` (`/buscar`) cruza jugadores/clubes/torneos/canchas. `/dashboard` pasa de redirect a vista real (polla pura sigue redirigiendo a pronósticos). Sin migraciones. 1 test actualizado (NavigationTest) → suite en verde.
- Pendiente próximas fases: score de confiabilidad en tabla de perfiles; eventos de Feed para seguidores de un torneo; mensajería estructurada/plantillas y adjuntos (foto) desde UI; fotos de canchas (upload a R2).

---

## 12. Reclamo de perfil — jugadores `por_verificar` (Sesión TX-1)

Resuelve la deuda técnica más antigua (#2): un capitán puede anotar a un jugador informal **sin cuenta** usando solo nombre + documento (`club_players.verification_status = 'por_verificar'`, `user_id` nulo). Ese jugador acumula historial en `team_players`/`player_stats`. Cuando se registra, **reclama** ese registro y, tras aprobación humana, hereda el historial.

Migración aditiva `2026_06_28_000001`: tabla `profile_claims` (`user_id`, `club_player_id`, `club_id` denormalizado, `document`, `status` pending/escalated/approved/rejected, `resolved_by_user_id`, `escalated_at`/`resolved_at`, `resolution_note`).

| Componente | Rol |
|---|---|
| `App\Models\Torneos\ProfileClaim` | Reclamo. Scopes `open`/`pending`/`escalated`; helpers `isOpen`/`isEscalated` |
| `App\Services\Torneos\ProfileClaimService` | Orquesta todo el ciclo |
| `App\Http\Controllers\Torneos\ProfileClaimController` | Jugador (`/reclamos`: index/store/escalate) + capitán (`/reclamos/aprobaciones`: approve/reject) |
| `App\Http\Controllers\Admin\Torneos\ProfileClaimController` | Bandeja de **escalados** `/admin/torneos/reclamos` (approve/reject) |
| `ProfileClaimSubmittedNotification` / `ProfileClaimResolvedNotification` | Mail al aprobador (capitán/admin) y al reclamante |

**Detección automática** (`findCandidatesFor`/`countCandidatesFor`): registros `por_verificar` sin cuenta cuyo documento coincide con `users.document` (normalizado: minúsculas, sin puntos/guiones). Excluye registros con reclamo vivo y clubs donde el usuario ya es miembro. Se dispara: al **registrarse** (campo `documento` ahora opcional en el form) y al **cargar/cambiar el documento en el perfil** → flash `claim_candidates` (banner en el layout). Enlace "Reclamar mi perfil" en el menú de perfil.

**Ciclo** (`ProfileClaimService`):
- `claim(User, ClubPlayer)` — valida: registro `por_verificar` sin cuenta, documento coincide, **sin reclamo vivo** (doble reclamo bloqueado), usuario no es ya miembro del club. Nace `pending`; si el club **no tiene capitán activo** (capitán nulo o usuario inexistente) nace `escalated`. Notifica al aprobador.
- `approve(ProfileClaim, User approver)` — **transaccional**: autoriza (capitán del club si pendiente, o admin siempre); guard "un documento no se vincula a dos `user_id` en el mismo club"; vincula `club_player` (`user_id` + `registrado`); **hereda historial** (`transferHistory`: backfill de `team_players` `por_verificar` del club que coinciden por documento → `user_id` + `registrado`, saltando los que romperían `unique(team_id, user_id)`); cierra el reclamo; rechaza otros reclamos vivos del mismo registro. Post-commit refresca `PlayerCareerStatsService::refreshForUser` y `FairPlayService::refreshForUser`. El jugador queda con credencial QR (ya tiene `futgo_id`), entra al fair play del equipo (#12) y recibe recordatorios de partido (#13, vía `team_players.user_id`).
- `reject(ProfileClaim, User, ?note)` — el registro queda **sin cambios**.
- `escalate(ProfileClaim)` — el reclamante escala manualmente un pendiente que el capitán no responde.
- `pendingForCaptain`/`escalatedForAdmin` — bandejas. Badge "Reclamos por aprobar" en el nav (composer `pendingClaimApprovals`). Enlace "Reclamos" en el admin nav.

**Garantías**: nunca vincula sin aprobación humana; un registro no admite dos reclamos vivos; un documento aprobado deja de ser candidato/reclamable. Tests: `tests/Feature/Torneos/ProfileClaimTest.php` (7): detección en registro, reclamo pendiente + notificación, aprobación vincula y transfiere stats, rechazo sin cambios, doble reclamo bloqueado, documento no a dos usuarios, escalamiento resuelto por admin.

---

## 13. Tarjetas PNG, WhatsApp y OG tags (Sesión TX-2)

Resuelve la deuda técnica #8: tarjetas compartibles ahora disponibles también en PNG, mejora de viralización con botones nativos de WhatsApp y Open Graph en fichas públicas.

Sin migraciones nuevas. Ningún cambio al módulo Torneos ni a la lógica de negocio.

| Componente | Rol |
|---|---|
| `App\Services\Torneos\ShareCardPngService` | Genera PNG de tarjetas con GD. Gradiente en canvas 1080×1080, primitivas de texto (TTF con fallback bitmap), lookup multi-ruta de fuente (`SHARE_CARD_FONT_PATH` env → `resources/fonts/` → Linux/Windows). Almacena en `cards/` del disco MEDIA_DISK con caché larga |
| `App\Jobs\GenerateShareCardPng` | Job encolable (driver `database`): delega en el servicio. Recibe `type`, `tournament_id`, `match_id`, `friendlyMatchId`. Con `QUEUE_CONNECTION=sync` se ejecuta en el mismo request |
| `TournamentShareController` | Nuevos métodos `cardPng` + `matchCardPng`: sirven desde caché o generan en el momento; degradan a SVG si GD no está disponible |
| `Social\FriendlyMatchShareController` | Nuevo controlador. Tarjeta SVG (`/amistosos/{id}/img`) y PNG (`/amistosos/{id}/img/png`) para amistosos `jugado` |
| `resources/views/social/share/amistoso.blade.php` | Template SVG del resultado del amistoso: misma estructura que `partido.blade.php` pero con leyenda "AMISTOSO" y origen de datos en `homeClub`/`awayClub` |

**Rutas nuevas:**
- `torneos.public.img.png` → `GET /t/{slug}/img/{card}/png`
- `torneos.public.img.match.png` → `GET /t/{slug}/img/partido/{match}/png`
- `social.amistosos.img.card` → `GET /amistosos/{friendlyMatch}/img` (sin auth)
- `social.amistosos.img.png` → `GET /amistosos/{friendlyMatch}/img/png` (sin auth)

**WhatsApp:**
- Portal público de torneo: botón `<a href="wa.me/?text=...">` con nombre del torneo y URL.
- Menú "Imágenes" ampliado con columnas SVG (abrir) y PNG (descargar).
- Perfil de club: botón WhatsApp en la cabecera junto a "Seguir" y "Gestionar".
- "Mis amistosos": dropdown por cada partido jugado con SVG, PNG y WhatsApp.

**OG tags:**
- `social/jugador/show.blade.php`: `og_description` con nombre + nivel + ciudad + PJ + goles; `og_image` con `avatar_url` si tiene.
- `torneos/clubes/show.blade.php`: `og_description` con nombre + torneos + partidos + ciudad; `og_image` con `shield_url`.
- `layouts/app.blade.php`: soporte completo de OG (`og:type`, `og:title`, `og:description`, `og:url`, `og:image`, `twitter:card`) vía `@yield` — disponible para cualquier vista autenticada que quiera publicar OG tags en el futuro.

**Configuración de fuente para Hostinger:**
El servicio busca en orden: `SHARE_CARD_FONT_PATH` (env) → `resources/fonts/Inter-Bold.ttf` → `/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf` → otros paths de Linux/Windows. Si no se encuentra ninguna, degrada a fonts bitmap GD (siempre disponible). Para mejor calidad en prod: copiar un `.ttf` a `resources/fonts/` y hacer `git add resources/fonts/`.

Tests: `tests/Feature/Torneos/ShareCardPngTest.php` (13): endpoint PNG válido (goleadores/posiciones/partido), torneo privado 404, `gdAvailable()` sin crash, `storagePath` correcto, tarjeta SVG amistoso válida, amistoso no jugado 404, PNG amistoso válido, OG description en ficha jugador, OG image con/sin avatar, job serializable.

---

## 14. Navegación v3 — nav agrupado, dashboard de Inicio y buscador global (Sesión UX-1)

Rediseño de la experiencia de navegación. El navbar pasó de **11 enlaces planos** (Mi Carrera, Agenda, Mis Equipos, Mis Torneos, Oportunidades, Amistosos, Canchas, Buscar Torneo, Ranking, Feed, Mensajes) a una estructura por **dominios de acción + herramientas transversales**, alineada a patrones de apps sociales (LinkedIn/Instagram). Sin migraciones; ningún cambio a la lógica de negocio.

### Estructura del nav (`resources/views/components/nav.blade.php`)

**Barra principal (desktop): 4 dominios.** El componente reutilizable `<x-nav-dropdown>` (`components/nav-dropdown.blade.php`) renderiza cada grupo con label + descripción; el estado Alpine vive en el `x-data` del `<nav>` padre y se referencia por nombre (`state="jugarOpen"`, etc.).

| Grupo | Destino / dropdown |
|---|---|
| 🏠 **Inicio** | enlace directo → `dashboard` |
| ⚽ **Jugar** ▾ | Oportunidades · Amistosos · Modo rápido ⚡ (`social.oportunidades.express`) · Agenda |
| 🏆 **Competir** ▾ | Mis Torneos · Buscar Torneo · Ranking de la plataforma |
| 👥 **Comunidad** ▾ | Canchas · Buscar jugadores y clubes (→ `social.search`) |
| **Pronósticos** ▾ | solo si el usuario tiene módulo polla (sin cambios; admin incluido) |

**Header derecho (siempre visible, herramientas transversales):**
- 🔍 **Buscar** — botón que despliega un panel con input (Alpine `searchOpen` + `$refs.searchInput`), submit GET a `social.search`. En mobile es un enlace directo a `/buscar`.
- 🔔 **Feed** — enlace a `social.feed.index` con badge de no leídos (`feedUnreadCount`).
- 💬 **Mensajes** — enlace a `social.conversaciones.index` con badge (`messagesUnreadCount`).
- 👤 **Avatar ▾** — hub de Perfil/cuenta (reemplaza al ítem "Perfil" del bar): Mi Carrera, Mis Equipos (gated por `torneosAccess`), tema claro/oscuro, Configurar perfil, Reclamar mi perfil (+ badge `pendingClaimApprovals`), instalar PWA, Salir.

**Decisiones de diseño** (feedback del usuario): la **Agenda** dejó de ser menú propio (es una vista, no un dominio) y se integra al Inicio + dropdown Jugar; los **Mensajes** salieron del bar al header porque son transversales (llegan desde cualquier módulo); el **Perfil** es el avatar arriba a la derecha, evitando duplicar un dropdown homónimo. El menú mobile se reorganizó por secciones (Jugar/Competir/Comunidad/Mi perfil) con accesos rápidos a buscar y mensajes.

### Dashboard de Inicio (`App\Http\Controllers\DashboardController` + `resources/views/inicio.blade.php`)

`/dashboard` pasó de **redirect** (a Mi Carrera) a **vista real**. Es una pantalla de agregación de LECTURA (no inventa datos; reutiliza servicios existentes). Los usuarios sin módulo Torneos (polla pura) siguen redirigiendo a `predictions.index` desde el controlador. Secciones:
- **Saludo** + acciones rápidas (⚡ Modo rápido, Publicar oportunidad).
- **Recordatorios** destacados: ítems de la agenda con `status` `convocatoria_pendiente` (confirmar/declinar inline vía `torneos.convocatoria.respond`) o `resultado_pendiente` (cargar resultado del amistoso).
- **Tu semana**: próximos ítems de `SportsAgendaService::for()` con fecha ≥ hoy, agrupados por día (máx. 8).
- **Sugeridas para vos**: `Opportunity::visible()->active()->inCity($user->city)` excluyendo las propias (vacío si el usuario no cargó ciudad → CTA a completar perfil).
- **Novedades**: preview de `FeedService::relevantQuery()` (5 ítems) **sin marcar leído** — el badge del navbar baja recién al abrir `/feed`.

El logo y el ítem Inicio apuntan a `dashboard` (degrada a `predictions.index` / `profile.show` según módulos).

### Buscador global (`App\Http\Controllers\Social\GlobalSearchController` + `social/search/index.blade.php`)

Ruta `social.search` (`/buscar`, bajo `auth`). Con término ≥ 2 caracteres cruza cuatro entidades descubribles (máx. 8 por grupo), agrupadas en la vista:
- **Jugadores** — `User` con `futgo_id` no nulo, por nombre o `futgo_id`; nunca expone email/teléfono/documento. Enlace a `social.player.show`.
- **Clubes** — por nombre → `torneos.clubes.show`.
- **Torneos** — solo `visibility=public`, por nombre → `torneos.public.show`.
- **Canchas** — activas, por nombre o ciudad → `social.canchas.show`.

> ⚠️ Gotcha Blade encontrado y evitado: `@if` pegado a una palabra (ej. `Cancha@if(...)`) NO se compila como directiva (queda literal) pero su `@endif` sí, produciendo un `endif` huérfano. Usar ternario o separar con un carácter no-palabra (`}`, espacio).

Tests: `tests/Feature/UI/NavigationTest.php` actualizado (`test_dashboard_torneos_renderiza_inicio` reemplaza al viejo redirect a Mi Carrera). Suite completa en verde.

---

> Historial detallado de cada sesión de desarrollo en `docs/HISTORIAL_SESIONES.md`
