# Changelog

## v1.0.0 — MVP release (2026-05-26)

Primer release público de la plataforma **Soy Pachón Mundial** —
polla de pronósticos del Mundial FIFA 2026.

### Funcionalidad incluida

**Paso 1 — Estructura base**
- Laravel 11 con PHP 8.3, MySQL 8
- Tailwind CSS 3 + Alpine.js vía Vite
- 5 tablas de negocio: `users`, `matches`, `predictions`,
  `invitation_codes`, `rankings` (+ `settings`, `match_notifications`)

**Paso 2 — Fixture y seeders**
- 72 partidos de fase de grupos del Mundial 2026 (fixture FIFA,
  fuente ESPN, fechas en GMT-5)
- 32 partidos de eliminatoria como plantillas (Clasificado A1, etc.)
- 1 admin + 5 usuarios de prueba + 10 códigos `INV-001..010`

**Paso 3 — Autenticación**
- Registro con teléfono obligatorio (regex 7-15 dígitos)
- Login email/contraseña, recuperación vía email
- Códigos de invitación (1 uso, desactivables)
- Middleware `ensure.active` redirige a `/activate` si no tiene código

**Paso 4 — Mis Pronósticos**
- Vista con 104 partidos agrupados por fase
- Inputs auto-save (Alpine.js onblur)
- Bloqueo automático 5 min antes (`predictions:lock`, cron cada min)
- Polling de estados cada 30s sin recargar
- Filtro único: Todos / Pendientes / Grupo A-L / fases eliminatoria

**Paso 5 — Motor de puntos y ranking**
- `PredictionScoringService`: 5/3/2/1/0 pts (regla estricta same-side
  para 1 pt, sin espejo cruzado)
- Comando `predictions:calculate {match_id}` recalcula puntos y
  ranking completo con desempate por exactos
- Vista `/ranking` (privada, solo usuarios activos)
- Auditoría `/ranking/u/{user}` con solo partidos finalizados

**Paso 6 — Panel de administración**
- `/admin` protegido por middleware admin
- Dashboard con KPIs, top 3, últimos calculados
- Generador de códigos SPM-XXXX (alfabeto sin 0/O/I/1/L)
- Gestión de usuarios (toggle activo, buscador)
- Editor de fixture (equipos/fecha/sede de cualquier partido)
- Ingreso de resultados inline con cálculo automático
- Configuración: acumulado COP, nombre torneo, mensaje welcome,
  URL del video YouTube

**Paso 7 — Notificaciones por email**
- Driver `log` en local (todos los emails al `storage/logs/laravel.log`)
- Tabla `match_notifications` con UNIQUE(user, match, type) para
  garantizar idempotencia
- Notificación `PredictionReminderNotification` (15 min antes del
  cierre, solo a usuarios sin pronóstico con `notifications_enabled=1`)
- Comando `notifications:reminders` + scheduler cada minuto
- Toggle opt-out en `/perfil`

### Tests

77 tests passing (265 assertions) cubriendo:
- Auth (registro con teléfono, login, activación, recuperación)
- Pronósticos (CRUD, validación, bloqueo)
- Comando lock + comando calculate
- Scoring service (20 casos unitarios)
- Ranking (auth, ordenamiento, auditoría)
- Panel admin (acceso, códigos, resultados, settings, fixture)
- Notificaciones (envío, idempotencia, opt-out, contenido)
- Welcome con video embed

### Stack

- PHP 8.3
- Laravel 11.46
- MySQL 8 (charset utf8mb4)
- Tailwind CSS 3.4
- Alpine.js 3
- Vite 5
