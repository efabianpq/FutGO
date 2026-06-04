# TOURNAMENT_MODULE_OVERVIEW — Módulo Torneos (FutGO v2)

Mapa funcional del módulo de gestión de torneos amateur. Para cada funcionalidad se
indica: **objetivo**, **quién la usa**, **dependencias** y su lugar en la **secuencia lógica**.

---

## Secuencia lógica del ciclo de vida de un torneo

```
Creación torneo (draft)
        ↓
Apertura de inscripciones (open)
        ↓
Inscripción de equipos (capitán)  ──→  Aprobación de equipos (admin torneo)
        ↓
Registro de jugadores (capitán: por email)
        ↓
Generación de fixture (admin)  → torneo pasa a in_progress
        ↓
Programación de partidos (fecha/hora/sede/estado)
        ↓
Planilla del partido (documento maestro)
        ↓
Resultados + Eventos (goles, asistencias, tarjetas) + Convocatoria (titular/suplente/min)
        ↓
Recálculo automático: Standings + Estadísticas de jugadores
        ↓
Cierre de fase de grupos (admin) → genera y activa la eliminatoria
        ↓
Eliminatoria: al cerrar cada ronda, los ganadores avanzan y los perdedores de
semifinal pasan al tercer puesto (AUTOMÁTICO al finalizar todos los partidos de la ronda)
        ↓
Final → el torneo pasa a finished
```

---

## Roles del módulo

| Rol | Cómo se determina | Alcance |
|-----|-------------------|---------|
| **Administrador global** (`role=admin`) | Columna `users.role` | Ve y gestiona todo |
| **Administrador de torneo** (`role=torneo_admin`) | Columna `users.role` | Gestiona los torneos donde es `tournament_admin` |
| **Capitán** | Es `teams.captain_user_id` de algún equipo (en sincronía con `team_players.is_captain`) | Gestiona su(s) equipo(s) y plantilla(s) |
| **Jugador** | Tiene registro en `team_players` (con o sin `user_id`) | Consulta su actividad y la de sus torneos |

> La condición de capitán se deriva **por equipo**, nunca de un rol global en `users`. Un mismo usuario
> puede capitanear varios equipos en torneos distintos y, a la vez, ser jugador no-capitán en otros.
> Los **jugadores no registrados** (sin cuenta) existen como `team_players` con `user_id` NULL,
> `verification_status='por_verificar'` y datos básicos (`full_name`, `document`).

El acceso al módulo se controla con `users.modules` (`polla`, `torneos`, `full`) vía middleware `ensure.module:torneos`.

---

## Funcionalidades

### 1. Gestión de torneos
- **Objetivo:** crear y configurar el torneo (formato, grupos, puntuación, desempates, categoría, sede, premios, reglamento, fechas, estadísticas a trackear).
- **Quién:** administrador de torneo / global.
- **Dependencias:** ninguna (punto de partida).
- **Rutas:** `admin.torneos.{index,create,store,show,edit,update,status,destroy}`.
- **Notas:** estado secuencial `draft → open → in_progress → finished` (solo avanza). `max_teams` se autocalcula en formatos con grupos. Solo se elimina en `draft`.

### 2. "Mis Torneos" (entrada del módulo)
- **Objetivo:** listado de los torneos donde el usuario participa (admin/capitán/jugador) con estado, equipos, fase activa, próximo partido y accesos directos por rol.
- **Quién:** todos los usuarios con módulo torneos.
- **Dependencias:** torneos existentes con participación del usuario.
- **Ruta:** `torneos.index` (`/torneos`).

### 3. Equipos y jugadores
- **Objetivo:** inscribir equipos (capitán) y construir la plantilla; aprobar/rechazar equipos (admin).
- **Quién:** capitán (inscribe, agrega jugadores por email); admin (aprueba/rechaza).
- **Dependencias:** torneo en estado `open`.
- **Rutas:** `torneos.equipo.{inscribir,store,show,players.add,players.addGuest,players.remove,players.approve,players.reject}`, `admin.torneos.equipos.{index,show,approve,reject}`.
- **Reglas:** un usuario no puede estar en dos equipos del mismo torneo; el capitán queda como jugador activo marcado `is_captain`; no se puede quitar al capitán.
- **Jugadores no registrados:** el capitán puede dar de alta jugadores reales sin cuenta (`addGuestPlayer`): se guardan con `user_id` NULL, `full_name`, `document` opcional y `verification_status='por_verificar'`. Anti-duplicados dentro del torneo por `user_id` (registrados) y por `document` (no registrados).
- **"Mis Equipos" (`torneos.mis-equipos`):** índice central de todos los equipos que el usuario capitanea, across torneos, con conteos y accesos a la plantilla.

### 4. Centro de Gestión de Equipos (Team Hub)
- **Objetivo:** panel del equipo del usuario: plantilla, solicitudes pendientes, partidos y récord.
- **Quién:** capitán y jugadores del equipo (y admin del torneo).
- **Dependencias:** equipo inscrito.
- **Ruta:** `torneos.equipo.show` → `TeamHubController`.

### 5. Generación de fixture
- **Objetivo:** crear fases, grupos y partidos según el formato; pasa el torneo a `in_progress`.
- **Quién:** administrador de torneo.
- **Dependencias:** equipos aprobados suficientes (exactos en grupos/round_robin; potencia de 2 ≥4 en knockout).
- **Servicio:** `FixtureGeneratorService::generate`. **Ruta:** `admin.torneos.fixture.generate`.
- **Formatos:** `groups_and_knockout`, `round_robin`, `knockout_only`. Las rondas de eliminatoria se crean como placeholders y se nombran (Final/Semifinal/Cuartos…) + Tercer Puesto opcional.

### 6. Programación de partidos
- **Objetivo:** fijar fecha/hora, sede, estado (programado/en vivo/postpuesto) y observaciones de calendario.
- **Quién:** administrador de torneo.
- **Dependencias:** fixture generado; partido no finalizado.
- **Rutas:** `admin.torneos.partidos.{programar,programar.update}`.

### 7. Planilla del partido (documento maestro)
- **Objetivo:** acta oficial del partido — **fuente única** desde la que se actualizan eventos, estadísticas y posiciones.
- **Quién:** administrador de torneo.
- **Dependencias:** partido con ambos equipos asignados (programado/en vivo).
- **Rutas:** `admin.torneos.partidos.{resultado,store,live,destroy,pdf}`.
- **Captura:** convocatoria (asistencia, titular/suplente, minuto entrada/salida — autogenerada desde el roster), marcador final, marcador por periodos (1er/2do tiempo, prórroga, penales), eventos por jugador (gol, autogol, asistencia, amarilla, roja con minuto), cuerpo arbitral, observaciones arbitrales, cuerpo técnico, faltas acumulativas, tiempos muertos y confirmación del capitán.
- **Integraciones al guardar (transacción):** crea `match_lineups` + `match_events`; aplica roja → jugador `inactive`; recalcula `standings` (si es fase de grupos) y `player_stats` de ambos equipos; marca la fase completada si corresponde; **avanza la eliminatoria** si la ronda terminó.
- **Exportación:** PDF A4 (réplica de planilla oficial de fútbol sala) con roster pre-impreso y áreas de captura en blanco para uso físico.

### 8. Resultados y eventos
- **Objetivo:** registrar/editar/anular el marcador y los eventos.
- **Quién:** administrador de torneo.
- **Dependencias:** planilla del partido.
- **Notas:** editar un partido finalizado requiere **anular** primero (revierte stats/standings de forma limpia). En empate de eliminatoria, los penales definen el ganador.

### 9. Estadísticas
- **Objetivo:** goleadores, asistencias, tarjetas y minutos por jugador; perfil individual con historial.
- **Quién:** todos (torneos públicos) / admin y participantes (privados).
- **Dependencias:** resultados cargados (deriva de `player_stats`).
- **Rutas:** `torneos.estadisticas.{index,jugador}`. **Servicio:** `PlayerStatsCalculatorService`.

### 10. Tabla de posiciones (Standings)
- **Objetivo:** clasificación por grupo con desempates configurables (DG, GF, head-to-head, etc.).
- **Quién:** admin (vista completa + recálculo manual); participantes (resumen en el Hub).
- **Dependencias:** fase de grupos con resultados.
- **Rutas:** `admin.torneos.standings.{index,recalculate}`. **Servicio:** `StandingsCalculatorService`.

### 11. Cierre de fase de grupos y eliminatorias
- **Objetivo:** cerrar la fase de grupos y poblar/activar la eliminatoria con los clasificados.
- **Quién:** administrador de torneo (cierre de grupos es manual, con pantalla de clasificados proyectados).
- **Dependencias:** todos los partidos de grupos finalizados; existe ronda de eliminatoria posterior.
- **Rutas:** `admin.torneos.phases.{close,close.execute}`. **Servicio:** `PhaseClosureService`.
- **Progresión de eliminatoria:** **automática** al finalizar todos los partidos de una ronda — los ganadores avanzan, los perdedores de semifinal pasan al tercer puesto, y al cerrar la final el torneo queda `finished`.

### 12. Cronograma público
- **Objetivo:** calendario completo del torneo con filtros por estado/fase/equipo; vista por equipo.
- **Quién:** todos los usuarios del módulo.
- **Dependencias:** fixture generado.
- **Rutas:** `torneos.cronograma.{index,team}`.

### 13. Hub del torneo (Centro de información)
- **Objetivo:** vista pública del torneo — próximos partidos, últimos resultados, tabla resumida, goleadores, equipos participantes y bases del torneo.
- **Quién:** participantes del torneo (admin/capitán/jugador).
- **Dependencias:** torneo creado.
- **Ruta:** `torneos.hub` (middleware `ensure.tournament_participant`).

### 14. Portal del Jugador (`/mi-actividad`)
- **Objetivo:** dashboard personal — mis torneos, mis partidos, mis estadísticas y mis sanciones (disciplina).
- **Quién:** jugadores.
- **Dependencias:** participación como jugador.
- **Ruta:** `torneos.mi-actividad`.

### 15. Portal del Capitán (`/capitan`)
- **Objetivo:** centro de control de los equipos que capitanea — plantilla, gestión de jugadores, partidos, estadísticas del equipo y alertas de seguimiento.
- **Quién:** capitanes.
- **Dependencias:** ser capitán de ≥1 equipo (403 si no).
- **Ruta:** `torneos.capitan`.

### 16. Hoja de vida deportiva del jugador (`/torneos/mi-carrera`)
- **Objetivo:** trayectoria PERMANENTE del jugador across torneos — acumulado total (PJ, goles, asistencias, tarjetas, MVP, minutos, V/E/D, vallas invictas), Mis torneos, Mis equipos y Mi historial (detalle por torneo).
- **Quién:** jugadores.
- **Dependencias:** participación como jugador; foto de perfil opcional (`/perfil` → POST `/perfil/foto`).
- **Persistencia:** tabla agregada `player_career_stats` (1 fila/usuario), consolidada por `PlayerCareerStatsService` tras cada recalc de stats y al finalizar el torneo. Lectura O(1).

### 17. Perfil permanente del club (`/torneos/clubes/{club}`)
- **Objetivo:** identidad del equipo que persiste entre torneos — escudo, historial de participaciones por torneo, estadísticas acumuladas y jugadores/goleadores históricos.
- **Quién:** todos (lectura); creador del club o admin (subir escudo).
- **Modelo:** `clubs` agrupa las inscripciones (`teams.club_id`). Cada `Team` es la participación del club en un torneo. Stats del club agregadas en lectura sobre partidos finalizados.
- **Rutas:** `torneos.clubes.show`, `torneos.clubes.shield`.

### 18. Navegación por roles
- **Objetivo:** navbar y accesos rápidos que muestran solo lo que el usuario puede usar.
- **Quién:** todos.
- **Lógica:** Mis Torneos (siempre); Mi Actividad + Mi Carrera (jugadores); Mis Equipos + Panel Capitán (capitanes); Gestión Torneos (admin de torneo).

---

## Gaps detectados (clasificados)

### Crítico — RESUELTO en esta auditoría
- **Progresión de eliminatoria ronda a ronda:** antes, al terminar una ronda de knockout los ganadores no avanzaban ni se poblaba el tercer puesto → el torneo no podía llegar al campeón. **Implementado:** avance automático de ganadores, poblado de tercer puesto y cierre del torneo al definir la final. Desempate por penales en eliminatoria.

### Importante (pendiente, requiere alcance mayor)
- **Invitaciones de jugadores:** existe la tabla/modelo `tournament_invitations` pero no hay flujo de UI ni envío de email. Hoy el capitán agrega jugadores que **ya estén registrados** (por email). Un flujo de invitación con aceptación es el siguiente paso natural.
- **Reprogramación en cascada al anular un partido de eliminatoria:** anular un resultado de una ronda ya avanzada no revierte automáticamente las rondas siguientes (las llaves quedan con los equipos anteriores). Aceptable para la demo; conviene una limpieza en cascada futura.

### Deseable
- **Edición de datos del equipo** (nombre/color/escudo) luego de la inscripción.
- **Banner de campeón** en el Hub al finalizar el torneo (hoy el campeón se ve en el cronograma con el ganador resaltado).
- **Optimización N+1 menor** en los dashboards (`MyTournamentsController`, portales) que hacen consultas por torneo/equipo; aceptable a escala de demo.

---

## Servicios (lógica de negocio)

| Servicio | Responsabilidad |
|----------|-----------------|
| `FixtureGeneratorService` | Genera fixture por formato; avanza clasificados (grupos→knockout) y ganadores (knockout→knockout); puebla tercer puesto. |
| `StandingsCalculatorService` | Recalcula posiciones (delete+insert) con puntos y desempates del torneo. |
| `PlayerStatsCalculatorService` | Recalcula estadísticas por jugador desde `match_lineups` + `match_events`. |
| `PhaseClosureService` | Cierra la fase de grupos y activa la eliminatoria. |
