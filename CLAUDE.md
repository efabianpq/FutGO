# CLAUDE.md — FutGO v2

## 1. ¿Qué es este proyecto?
FutGO — plataforma de gestión de torneos deportivos y pronósticos.
Clonado desde SoyPachonMundial v1.0 como base de la v2.
URL local: http://futgo.test:8080
Repositorio: (pendiente crear en GitHub como soypachon-v2 o futgo)

## 2. Módulos
- Módulo Polla Mundial: heredado de v1, CONGELADO, no se modifica.
- Módulo Torneos: desarrollo activo de v2.

## 3. Stack técnico
- PHP 8.3.30 (Laragon, NO en PATH del sistema)
- Laravel 11.46
- MySQL 8.0.30 vía Laragon
- Base de datos local: futgo / root / sin password
- Base de datos tests: SQLite in-memory
- Frontend: Blade + Alpine.js 3 + Tailwind CSS 3 + Vite 5
- Apache en puerto 8080

## 4. PATH de Laragon (prepender antes de cualquier comando)
$env:Path = "C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64;
C:\laragon\bin\composer;
C:\laragon\bin\mysql\mysql-8.0.30-winx64\bin;$env:Path"

## 5. Comandos frecuentes
php artisan test
php artisan migrate --seed
php artisan optimize:clear
npm run build
php artisan serve --port=8001 (alternativa si el vhost no responde)

## 6. Estado actual
- Tests: 189 passing
- Módulo Polla: congelado, no tocar
- Módulo Torneos — Etapa 1 completa:
  ✅ Columna users.modules (DEFAULT 'polla')
  ✅ ENUM role ampliado con 'torneo_admin'
  ✅ Métodos hasModuleAccess / hasTorneosAccess / hasPollaAccess en User
  ✅ Middleware EnsureModule y EnsureTorneoAdmin
  ✅ Rutas base /torneos y /admin/torneos
  ✅ Vistas placeholder
  ✅ 7 tests de acceso por módulo
- Módulo Torneos — Prompt 3 completo — 12 tablas creadas:
  ✅ tournaments (name, slug, sport, status, format, groups_count, teams_per_group, classifies_per_group, stats_config, created_by_user_id, starts_at, ends_at)
  ✅ tournament_admins (tournament_id, user_id)
  ✅ teams (tournament_id, captain_user_id, name, color, shield_url, status)
  ✅ team_players (team_id, user_id, jersey_number, position, status)
  ✅ tournament_phases (tournament_id, name, type, order, is_active)
  ✅ tournament_groups (phase_id, name, order)
  ✅ group_teams (group_id, team_id)
  ✅ tournament_matches (phase_id, group_id, home_team_id, away_team_id, winner_team_id, home_score, away_score, status, scheduled_at, match_number)
  ✅ match_events (match_id, team_player_id, type, minute, notes)
  ✅ standings (phase_id, group_id, team_id, played, won, drawn, lost, goals_for, goals_against, goal_difference, points, position)
  ✅ player_stats (tournament_id, team_player_id, goals, assists, yellow_cards, red_cards, minutes_played, matches_played)
  ✅ tournament_invitations (tournament_id, invited_by_user_id, user_id, email, token, status, expires_at, accepted_at)
  ✅ 10 tests de migraciones en MigrationsTest
- Módulo Torneos — Prompt 4 completo — 12 modelos Eloquent creados en app/Models/Torneos/:
  ✅ Tournament ($fillable, casts datetime/boolean/array, relaciones: creator, admins, teams, phases, invitations, playerStats; helpers: isDraft/isOpen/isInProgress/isFinished/isCancelled)
  ✅ TournamentAdmin (relaciones: tournament, user)
  ✅ Team ($fillable, relaciones: tournament, captain, players, standings, playerStats; helpers: isPending/isApproved/isRejected)
  ✅ TeamPlayer ($fillable, relaciones: team, user, matchEvents, playerStat; helpers: isActive/isInactive)
  ✅ TournamentPhase ($fillable, cast is_active boolean, relaciones: tournament, groups, matches, standings; helpers: isGroups/isKnockout/isActive)
  ✅ TournamentGroup ($table explícito, relaciones: phase, teams BelongsToMany via group_teams, groupTeams, matches, standings)
  ✅ GroupTeam (relaciones: group, team)
  ✅ TournamentMatch ($table='tournament_matches', cast scheduled_at datetime, relaciones: phase, group, homeTeam, awayTeam, winner, events; helpers: isScheduled/isLive/isFinished/isPostponed/hasResult)
  ✅ MatchEvent ($fillable, relaciones: match, teamPlayer; helpers: isGoal/isOwnGoal/isYellowCard/isRedCard)
  ✅ Standing ($fillable, cast last_calculated_at, relaciones: phase, group, team)
  ✅ PlayerStat ($fillable, cast last_calculated_at, relaciones: tournament, teamPlayer)
  ✅ TournamentInvitation ($fillable, casts datetime, relaciones: tournament, invitedBy, user; helpers: isPending/isAccepted/isExpired)
  ✅ 16 tests de modelos en ModelsTest (creación BD, relaciones, tabla, helpers)
- Módulo Torneos — Prompt 5 completo — Panel de administración (CRUD torneos):
  ✅ Admin/Torneos/TournamentController (index, create, store, show, edit, update, updateStatus, destroy)
  ✅ 8 rutas bajo /admin/torneos con middleware ['auth','ensure.active','ensure.torneo_admin']
  ✅ Scoping por rol: torneo_admin solo ve/edita sus torneos (via tournament_admins); admin global ve todos
  ✅ Al crear: slug auto desde nombre (único), creador se agrega como admin en tournament_admins
  ✅ Secuencia de estado forzada draft→open→in_progress→finished (solo avanza un paso, no retrocede ni salta)
  ✅ Solo se elimina torneo en status=draft
  ✅ Vistas: index, create, edit, show (dashboard), _nav (sub-navbar), _form (compartido create/edit con Alpine)
  ✅ Dashboard con stats rápidas (equipos, partidos programados/jugados) + cambio de estado y eliminación con confirmación Alpine
  ✅ Accesos rápidos a equipos/fixture/resultados/estadísticas como placeholders (rutas pendientes en prompts siguientes)
  ✅ Validaciones: name 3-100, format en ENUM, groups_count 1-16 (requerido si formato usa grupos), teams_per_group 2-8, classifies < teams, fechas encadenadas
  ✅ 14 tests en TournamentAdminTest
- Módulo Torneos — Prompt 6 completo — Gestión de equipos y jugadores:
  ✅ Admin/Torneos/TeamAdminController (index, show, approve, reject) con scoping por tournament_admins
  ✅ Torneos/TeamController (inscribir, store, show, addPlayer, removePlayer) para capitanes
  ✅ 9 rutas: 4 admin bajo /admin/torneos/{tournament}/equipos + 5 capitán bajo /torneos/{tournament}/mi-equipo
  ✅ Al inscribir: capitán queda como jugador activo automáticamente, estado=pending
  ✅ Validación: torneo debe estar open para inscribir y agregar jugadores
  ✅ Validación: usuario no puede ser capitán/jugador en dos equipos del mismo torneo
  ✅ Búsqueda de jugadores por email con mensaje claro si no existe en la plataforma
  ✅ Capitán no puede ser quitado del equipo
  ✅ Vistas: admin/torneos/equipos/{index,show} + torneos/equipo/{inscribir,show}
  ✅ Vista show capitán con Alpine.js: form inline agregar jugador + confirmación quitar
  ✅ Card "Equipos" en dashboard del torneo activada con link real y contador
  ✅ 13 tests en TeamsTest
- Módulo Torneos — Prompt 7 completo (refinado) — Generación de fixture:
  ✅ Migración 2026_05_29_000013: home_team_id / away_team_id nullable en tournament_matches (para partidos placeholder de eliminatoria)
  ✅ app/Services/Torneos/FixtureGeneratorService:
     - generate(Tournament): array — valida (status=open, sin fixture previo, equipos), crea fases/grupos/partidos en DB::transaction, cambia torneo a in_progress y retorna resumen {phases,groups,matches}
     - advanceTeams(TournamentPhase): clasifica por standings + classifies_per_group y arma el cruce hacia la fase siguiente (o ganadores en knockout→knockout)
  ✅ match_number: secuencial y único a nivel TORNEO (contador global, no por fase)
  ✅ Validaciones: torneo en open; sin fixture previo; groups_and_knockout y round_robin exigen equipos aprobados === max_teams; knockout_only exige potencia de 2 (≥4)
  ✅ Cruce de llaves: classifies==2 + grupos pares → A1vB2 / B1vA2; fallback seeding 1ro-vs-último para otros casos
  ✅ round_robin = 1 fase groups, único grupo, todos contra todos; knockout_only = emparejamientos iniciales aleatorios (shuffle)
  ✅ Rondas nombradas (Final/Semifinal/Cuartos/Octavos...) + fase Tercer Puesto si third_place_match y hay semifinal
  ✅ Comando torneos:generate-fixture {tournament_id} con resumen y estado resultante
  ✅ Ruta POST /admin/torneos/{tournament}/fixture/generar (FixtureController) + botón en dashboard cuando open y equipos suficientes; el servicio deja el torneo in_progress
  ✅ Fix de bug latente: TournamentGroup::teams() belongsToMany especifica pivote group_id (Laravel inferia tournament_group_id)
  ✅ 13 tests en FixtureGeneratorTest (atomicidad con rollback de status, match_number global único, potencia de 2, in_progress, cruce de advanceTeams)
- Módulo Torneos — Prompt 6B completo — Configuración avanzada del torneo:
  ✅ Migración 2026_05_29_000014: +19 columnas en tournaments (visibility, category, city, venue, max_teams, points_win/draw/loss, tiebreaker_order JSON, knockout_tiebreak, min/max_players_per_team, match_duration, max_substitutions, registration_fee, prize_description, rules, logo_url, banner_url)
  ✅ Migración 2026_05_29_000015: +4 columnas en player_stats (wins, draws, losses, clean_sheets)
  ✅ Modelo Tournament: fillable+casts (tiebreaker_order→array); métodos getDefaultTiebreakerOrder(), isPublic(), isPrivate(), getStatsConfig() (selectables + derivadas wins/draws/losses/clean_sheets default true); scope public()
  ✅ Controller: validación de los campos nuevos; reglas points_win>points_draw>=points_loss, min<max jugadores, fee>=0; max_teams se autocalcula (grupos×equipos) y se valida igualdad en formatos con grupos
  ✅ _form.blade.php reescrito en 6 secciones (info general, formato+puntos+desempates, config deportiva, inscripción/premios, calendario, estadísticas); editor de tiebreaker ordenable con Alpine (↑/↓ + toggle, hidden inputs en orden)
  ✅ 5 tests nuevos en TournamentAdminTest (campos guardan, max_teams calcula/valida, puntos win>draw>=loss, privado fuera de scope public, tiebreaker_order como array)
  ⚠ IMPORTANTE para Prompts 7+ en adelante: usar points_win/draw/loss (no hardcodear 3/1/0), tiebreaker_order para ordenar standings, knockout_tiebreak para empates en eliminación, max_teams para validar el fixture
- Módulo Torneos — Prompt 8 completo — Ingreso de resultados y eventos:
  ✅ StandingsCalculatorService (recalculate(TournamentPhase): acumula PJ/PG/PE/PP/GF/GC/DG/Pts usando points_win/draw/loss, ordena por tiebreaker_order con head_to_head, goal_difference, goals_for; updateOrCreate en standings con posición)
  ✅ PlayerStatsCalculatorService (recalculate(Tournament, Team): goles, asistencias, amarillas, rojas desde match_events; minutos desde substitution_in/out o match_duration; PJ, victorias, empates, derrotas, vallas invictas; updateOrCreate en player_stats)
  ✅ PlayerStat fillable ampliado: wins, draws, losses, clean_sheets
  ✅ MatchResultController (5 métodos: index, show, store, markLive, destroy) con scoping por tournament_admins
  ✅ 5 rutas bajo /admin/torneos/{tournament}/partidos (GET index, GET resultado, POST resultado, PATCH en-vivo, DELETE resultado)
  ✅ Vista partidos/index.blade.php con filtro por fase Alpine, tabla de partidos con badges de estado y botones contextuales (En vivo / Ingresar resultado / Anular)
  ✅ Vista partidos/resultado.blade.php con formulario Alpine dinámico: marcador, eventos por jugador (add/remove inline), resumen y confirmación
  ✅ Dashboard show.blade.php: card "Resultados" activada con link real cuando hay fixture
  ✅ Lógica store: DB::transaction → actualiza match (score/winner/finished), elimina eventos previos, crea nuevos MatchEvent, recalcula standings (si fase grupos), recalcula player_stats (ambos equipos), marca fase completada si todos los partidos están finished
  ✅ Lógica destroy: revierte partido a scheduled, borra eventos, recalcula standings y stats
  ✅ 13 tests en MatchResultTest (acceso, resultado, eventos, standings, player_stats, anulación, re-ingreso, puntos personalizados, en-vivo)
  ⚠ IMPORTANTE para Prompt 9: PlayerStatsCalculatorService cuenta matches_played solo si el jugador tiene al menos un evento en el partido; jugadores sin eventos en un partido no se cuentan. Para calcular minutos_played usa substitution_in/out events; sin ellos asume match_duration completo.
- Módulo Torneos — Prompt 9 completo — Lineup y estadísticas individuales:
  ✅ Fix P8-1: tipo de evento default = primer tipo habilitado en stats_config (no hardcodeado "Gol")
  ✅ Fix P8-2: red_card automáticamente marca team_player.status='inactive' en el mismo DB::transaction
  ✅ Migración 2026_05_29_000016: tabla match_lineups (match_id, team_player_id, team_id, started, minute_in, minute_out, UNIQUE match+player)
  ✅ Modelo MatchLineup con relaciones a TournamentMatch, TeamPlayer, Team; helper minutesPlayed(int)
  ✅ TournamentMatch::lineups() HasMany añadida
  ✅ resultado.blade.php reescrito: sección Convocatoria (checkboxes jugó/titular/minuto_in/out) antes de Eventos; Alpine filtra disponibles en eventos según lineup; submit() genera inputs ocultos planos de lineups[] y events[]
  ✅ MatchResultController::store() persiste match_lineups, aplica red_card→inactive; destroy() también elimina lineups
  ✅ PlayerStatsCalculatorService refactorizado: match_lineups como fuente de verdad para matches_played/minutes_played/clean_sheets/wins/draws/losses; goals/assists/cards siguen desde match_events; calcula también jugadores inactivos que tengan lineup
  ✅ StatsController (Torneos/StatsController) con index (goleadores) y jugador (perfil individual)
  ✅ 2 rutas GET /torneos/{tournament}/estadisticas y /jugador/{teamPlayer}
  ✅ Vista torneos/estadisticas/index.blade.php: tabla con filtro Alpine por equipo; solo jugadores con matches_played>0
  ✅ Vista torneos/estadisticas/jugador.blade.php: resumen PJ/goles/asist/tarjetas/minutos/V-E-D/vallas + historial partido a partido
  ✅ 8 tests en PlayerStatsTest; MatchResultTest actualizado para incluir lineup en test de player_stats
  ✅ 210 tests passing
  ⚠ IMPORTANTE para Prompt 10: el cierre de fase de grupos (is_active=false automático cuando todos los partidos son finished) ya está implementado. advanceTeams() en FixtureGeneratorService asigna clasificados a placeholders de eliminatoria usando standings. El Prompt 10 debe activar la siguiente fase e implementar el flujo de resultados de eliminatoria.
- Módulo Torneos — Prompt 10 completo — Tabla de posiciones y desempates:
  ✅ StandingsCalculatorService reescrito: delete+insert (no updateOrCreate), garantiza recálculo limpio
  ✅ Fuente de verdad: partidos status='finished' (scheduled/live/postponed ignorados)
  ✅ Puntos via points_win/draw/loss del torneo — nunca hardcodeados
  ✅ Criterios de desempate soportados: points, goal_difference, goals_for, wins, head_to_head
  ✅ Head-to-head mejorado: compara pts directos → DG directa → GF directa entre los dos equipos
  ✅ tiebreaker_order del torneo se respeta en orden exacto configurado
  ✅ StandingsController (Admin/Torneos) con index y recalculate manual
  ✅ Rutas GET /admin/torneos/{tournament}/standings y POST /recalculate
  ✅ Vista admin/torneos/standings/index.blade.php: tabla PJ/PG/PE/PP/GF/GC/DG/PTS por grupo, clasificados destacados en verde, panel de sistema de puntos
  ✅ Dashboard show.blade.php: card "Posiciones" activada para torneos con grupos y fixture
  ✅ 14 tests en StandingsTest (victoria/empate/derrota, puntos custom, DG, ordenamiento, h2h, solo finished, delete+insert, recálculo automático, vía HTTP)
  ✅ 224 tests passing
- Módulo Torneos — Prompt 11 completo — Cierre de fase de grupos y generación de eliminatoria:
  ✅ Migración 2026_05_30_000017: columna status (pending/active/completed) en tournament_phases + backfill is_active→active; índice
  ✅ TournamentPhase: status en fillable; helpers isCompleted(), isThirdPlace()
  ✅ PhaseClosureService::closeGroupPhase(TournamentPhase): valida (tipo groups, no cerrada, todos los partidos finished sin cierre parcial, existe knockout siguiente), marca status=completed+is_active=false, llama advanceTeams() (no reinventa el cruce) y activa la fase siguiente (status=active+is_active=true), todo en DB::transaction
  ✅ Helpers del servicio: projectedQualifiers() (clasificados por grupo según standings), canClose(), totalMatches/finishedMatches/pendingMatches(), nextKnockoutPhase()
  ✅ Cruce estándar reutilizado de FixtureGeneratorService::advanceTeams (A1 vs B2 / B1 vs A2); tercer puesto NO se puebla al cerrar grupos (se llena al cerrar semifinal con los perdedores)
  ✅ Bloqueo de fase cerrada: MatchResultController store/markLive/destroy rechazan si phase->isCompleted(); StandingsCalculatorService::recalculate retorna temprano en fase completed; StandingsController::recalculate filtra fases completed
  ✅ PhaseController (Admin/Torneos) close (pantalla) + doClose (ejecuta), con scoping por tournament_admins y ensureBelongs
  ✅ 2 rutas bajo /admin/torneos/{tournament}/phases: GET /{phase}/close (admin.torneos.phases.close) y POST /{phase}/close (admin.torneos.phases.close.execute)
  ✅ Vista resources/views/torneos/phases/close.blade.php: resumen de partidos (total/finalizados/pendientes), clasificados proyectados por grupo, fase siguiente, botón "Cerrar fase y generar eliminatoria" con confirmación Alpine; alerta + botón deshabilitado si hay pendientes
  ✅ Dashboard show.blade.php: tarjeta resumen de fase de grupos abierta (estado, finalizados/total, pendientes, clasificados proyectados, fase siguiente, acción de cierre o alerta) — TournamentController::groupPhaseSummary()
  ✅ 12 tests en PhaseClosureTest (pendientes bloquea, cierra cuando todos finished, marca completed, clasifica por classifies_per_group, genera+activa siguiente ronda, cruces A1vB2/B1vA2, no cierra dos veces, no modifica resultados tras cierre, no recalcula fase cerrada, tercer puesto habilitado, pantalla accesible, cierre vía HTTP)
  ✅ 236 tests passing
- Próximo paso: Prompt 12 — flujo de resultados de eliminatoria (avanzar ganadores ronda a ronda, poblar tercer puesto, cerrar torneo)

## 7. Convenciones (igual que v1)
- Español, voseo
- Commits: tipo: descripción corta en español
- Co-Authored-By: Claude Sonnet 4.6 <noreply@anthropic.com>
