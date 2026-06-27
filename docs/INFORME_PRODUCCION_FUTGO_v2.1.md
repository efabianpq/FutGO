# Informe de estado y readiness para producción — FutGO v2.1

**Fecha:** 2026-06-25
**Alcance del informe:** módulo Torneos (v2), excluye módulo Polla Mundial (congelado, no se modifica).

---

## 1. Alcance y funcionalidades actuales

FutGO es una plataforma de gestión de torneos deportivos amateur (fútbol como deporte inicial, aunque el modelo de datos es agnóstico). El módulo Torneos quedó completo tras las Sesiones A–G, sobre 380 tests automatizados pasando antes de esta revisión (458 tras el último cambio de UI).

### 1.1 Gestión del torneo (admin)
- CRUD completo de torneos: formato (grupos+eliminación / solo eliminación / todos contra todos), categoría, ciudad, sede, cupos máximos, sistema de puntos configurable (victoria/empate/derrota), criterios de desempate ordenables, cuota de inscripción, premios, reglamento, logo/banner.
- Generación automática de fixture (grupos + cruces de eliminatoria, rondas nombradas, tercer puesto opcional).
- Cierre de fase de grupos con clasificación automática a la siguiente ronda.
- Ingreso de resultados con convocatoria/alineación, eventos por jugador (goles, asistencias, tarjetas), selector de MVP opcional por torneo.
- Tabla de posiciones con recálculo automático: puntos personalizados, diferencia de gol, head-to-head, fair play, y sorteo determinista auditado como último criterio.
- Altas/bajas/cambios de jugador durante el torneo, con aprobación de incorporaciones tardías por el admin.
- Patrocinadores del torneo (gestión básica sin facturación).
- Exportación de resultados/posiciones/estadísticas a PDF y CSV.

### 1.2 Equipos y jugadores
- Modelo de **club permanente y transversal**: el equipo (`clubs`) existe independientemente del torneo; `teams` es la participación de ese club en un torneo concreto. Un jugador pertenece al club, no al torneo.
- Gestión de plantilla permanente por el capitán (alta/baja/cambio de capitán), independiente de la inscripción a torneos puntuales.
- Soporte de jugadores **no registrados** ("por verificar"): se pueden anotar por nombre/documento sin cuenta en la plataforma.
- Perfil de jugador permanente ("Mi Carrera"): acumulado histórico cross-torneo, logros, fair play, historial por temporada.
- Perfil de club permanente: escudo, historial de participaciones, estadísticas agregadas.
- Credencial QR antifraude por jugador (identificador público `FG-XXXXXX` + firma HMAC, sin datos sensibles en el QR) y pantalla de validación para árbitros/admins.
- Convocatoria previa al partido con confirmación/declinación del jugador.

### 1.3 Portal público y descubrimiento
- Portal sin autenticación por torneo (`/t/{slug}`): tabla de posiciones, resultados, próximos partidos, goleadores, patrocinadores. Solo torneos `visibility=public`.
- Listado público de descubrimiento (`/torneos` sin sesión): "Inscripciones abiertas" y "En juego", con búsqueda y filtro por ciudad.
- **Recién rediseñado** (este informe): tarjetas con franja horizontal de 4 columnas (Ciudad/Equipos/Inscripción/Inicio y Ciudad/Equipos/Partidos/Fase) + barra de progreso de cupos o de partidos jugados, responsive 2×2 en móvil.
- Tarjetas compartibles en SVG (goleadores, posiciones, MVP, resultado de partido) para WhatsApp/redes.
- Open Graph tags para preview de link al compartir.

### 1.4 Reputación y gamificación
- Ranking cacheado (jugador/equipo × global/ciudad/categoría), reconstruido al finalizar torneo o por comando programado.
- Fair play cacheado (jugador y equipo), penaliza tarjetas e inasistencias.
- Logros data-driven (catálogo + asignación automática, 10 logros iniciales).

### 1.5 Notificaciones y automatización
- Recordatorios de partido por email a convocados/confirmados (ventana configurable, idempotente).
- Scheduler único compartido con el módulo Polla (cron de Hostinger cada minuto → `schedule:run`).

---

## 2. Deuda técnica y limitaciones conocidas (heredadas del desarrollo)

Estas ya estaban documentadas como `⚠` en `CLAUDE.md` al cierre de cada sesión y siguen sin resolver:

| # | Limitación | Sesión de origen | Impacto |
|---|---|---|---|
| 1 | Anular un partido de eliminatoria ya avanzada no revierte las rondas siguientes | A | Bajo uso, pero puede dejar standings/cruces inconsistentes si un admin anula tarde |
| 2 | Flujo de "reclamo" de perfil para jugadores `por_verificar` sin UI | A, B, C, D | Estos jugadores nunca podrán vincular su cuenta real ni tener credencial QR sin esto |
| 3 | Stats de club se agregan en lectura (sin tabla propia) | B | Aceptable a escala amateur; no escala bien con muchos torneos/clubes |
| 4 | Captura de MVP en la planilla de resultados sin UI (modelo listo) | B, C | Funcionalidad de MVP a medio implementar |
| 5 | Fotos en disco local, no en S3 | B | **Ver hallazgo crítico §3.3** |
| 6 | Cambio de equipo en torneo `in_progress` bloqueado (solo vía baja+alta aprobada) | C | Es una regla de negocio intencional, no un bug, pero hay que comunicarla a los admins |
| 7 | Convocatoria previa no pre-llena automáticamente la alineación del resultado | C | Doble trabajo para el capitán/admin al cargar resultados |
| 8 | QR con firma inválida igual resuelve al jugador (con aviso) | D | Decisión de diseño documentada, aceptable, pero debe quedar claro a los árbitros |
| 9 | Imágenes compartibles en SVG, no PNG | E | WhatsApp prefiere PNG al adjuntar archivo; impacta la viralización del compartir |
| 10 | Portal público limita a 12 resultados/12 próximos/10 goleadores | E | Intencional por performance, pero sin paginación para ver el resto |
| 11 | Ranking/fair play son cache, no tiempo real | F | Se actualizan solo al finalizar torneo o por cron — un admin puede creer que están "desactualizados" |
| 12 | Fair play de equipo no cuenta jugadores `por_verificar` | F | Relacionado con #2 |
| 13 | Solo jugadores registrados y convocados reciben recordatorios por email | G | Los `por_verificar` quedan fuera de toda notificación |

---

## 3. Hallazgos de esta auditoría (código + configuración)

Revisión técnica enfocada en qué impide salir a producción con usuarios reales. Severidad: 🔴 crítico · 🟠 alto · 🟡 medio · 🟢 bajo/resuelto.

### 3.1 Configuración de entorno
- 🔴 **`APP_DEBUG=true`** en el `.env` actual. Si se replica así en Hostinger, cualquier error expone stack trace completo, rutas del servidor y queries SQL al público. **Debe ser `false` en producción, sin excepción.**
- 🟡 `SESSION_SECURE_COOKIE=false` — debe pasar a `true` una vez el sitio esté bajo HTTPS.
- 🟡 Canal de log `single` (`config/logging.php`) sin rotación: el archivo de log crece indefinidamente. Cambiar a `daily`.
- 🟢 `.env.example` desactualizado (todavía dice `APP_NAME="@SoyPachon"`, `DB_DATABASE=soypachonmundial`) — no es funcional pero genera confusión al desplegar. Actualizar antes de repartir el repo a otro colaborador.
- 🟡 `config/filesystems.php` define un disk `s3` sin credenciales — todo el storage queda forzosamente en disco local (ver 3.3).

### 3.2 Seguridad
- 🟠 **Falta `throttle:` en login, registro y recuperación de contraseña** (`routes/web.php` / `routes/auth.php`). Sin esto, el sitio es vulnerable a fuerza bruta de credenciales.
- 🟡 **Falta throttle en `/torneos/validar`** (validación de credencial QR). Solo está protegido por el middleware de rol; un admin de torneo malicioso o una sesión comprometida podría enumerar identificadores `FG-XXXXXX` por fuerza bruta.
- 🟢 CSRF activo por defecto (Laravel 11) — correcto, sin acción requerida.
- 🟡 Sin headers de seguridad adicionales (CSP, `X-Frame-Options`, `X-Content-Type-Options`) en el middleware global.
- 🟡 `$fillable` de `User` incluye campos sensibles (`role`, `is_active`, `modules`). No se confirmó uso indebido, pero es un vector latente si algún controlador llega a usar `$request->all()` en un update de perfil. Revisar que **todos** los formularios de perfil usen `$request->only([...])` o Form Requests explícitos.
- 🟢 Hashing con bcrypt por defecto — correcto.

### 3.3 Almacenamiento de archivos — 🟠 hallazgo importante
Avatares, escudos de club y logos/banners de torneo se guardan consistentemente en `storage/app/public` (disco local), con borrado correcto del archivo anterior al actualizar — el código en sí está bien hecho. El problema es de **infraestructura**: en muchos planes de hosting compartido (incluido Hostinger según el plan), el filesystem no es persistente entre despliegues o reinicios, y no hay credenciales S3 configuradas como alternativa. Si se sube una nueva versión del código sin preservar `storage/app/public`, **todas las fotos de perfil, escudos y logos se pierden**.

**Antes de promover a producción real con usuarios:** decidir explícitamente uno de:
1. Confirmar con Hostinger que el storage persiste entre despliegues y documentar el procedimiento de deploy para nunca borrar `storage/`.
2. Migrar a un disco S3-compatible (Cloudflare R2, Backblaze B2, AWS S3) — ya hay un disk `s3` declarado, solo falta configurar credenciales y cambiar `FILESYSTEM_DISK`.

### 3.4 Backups de base de datos — 🔴 gap crítico
No existe ningún mecanismo de respaldo automatizado: ni `spatie/laravel-backup`, ni un comando artisan custom, ni un cron de `mysqldump`. **Con usuarios reales y datos de torneos en curso, esto es la prioridad #1 antes de abrir la plataforma**: un error humano, una migración fallida o un incidente en el hosting puede borrar todo sin posibilidad de recuperación.

### 3.5 Rendimiento — 🟠 N+1 confirmado
En `StatsController` (vista de estadísticas de jugador), dentro de un `->map()` se ejecuta una consulta `lineups()->where(...)->first()` por cada partido del historial, sin eager-load previo. El impacto crece linealmente con la cantidad de partidos jugados por el jugador — notorio para jugadores con muchos torneos acumulados ("Mi Carrera"). El resto de controladores auditados (`MyTournamentsController`, `TournamentHubController`, `PublicTournamentController`, `StandingsController`) usan `with()`/`withCount()` correctamente.

### 3.6 Notificaciones por email — 🟡 bloqueante funcional, no técnico
`MAIL_MAILER=log` tanto en `.env` como en `.env.example`. Mientras esto no cambie a un proveedor SMTP real (Mailgun, SES, Postmark, SMTP del propio Hostinger), **ningún recordatorio de partido ni invitación llega de verdad a los usuarios** — quedan solo escritos en el log. Es el ítem más visible para los admins de torneo apenas empiecen a usarla en serio.

### 3.7 Colas — 🟡 medio
No hay jobs ni notificaciones marcadas `ShouldQueue`; los envíos de email (cuando se active SMTP real) serán síncronos y bloquearán el request del usuario que dispare el recordatorio/invitación. No es bloqueante para un lanzamiento inicial con pocos usuarios, pero conviene encolar antes de escalar.

### 3.8 Monitoreo y manejo de errores
- 🟡 Sin Sentry/Bugsnag/Flare — sin visibilidad de errores reales en producción más allá de revisar logs manualmente.
- 🟡 **Faltan `404.blade.php` y `500.blade.php`** en `resources/views/errors/` (solo existe `419.blade.php`). Combinado con el hallazgo de `APP_DEBUG` (§3.1), un error 500 en producción mostraría la pantalla de debug de Laravel Ignition con todo el detalle interno en lugar de una página genérica — **doble urgencia**: corregir `APP_DEBUG` Y crear las vistas de error.
- 🟢 Existe pipeline CI (`.github/workflows`) corriendo la suite de tests en cada push/PR — buena cobertura de regresión.
- 🟡 Sin análisis estático (PHPStan/Larastan, Psalm) — no es bloqueante pero ayudaría a atrapar bugs de tipo antes de que lleguen a producción.

### 3.9 Validación de subida de imágenes
🟢 Correctamente resuelto y uniforme: avatar, escudo de club y logo/banner de torneo aplican la misma validación (`image, mimes:jpg,jpeg,png,webp, max:2048`) en los tres controladores.

### 3.10 Dependencias
🟢 Sin hallazgos de versiones obsoletas o vulnerabilidades evidentes en `composer.json`/`package.json` (Laravel 11, PHP ^8.2, Alpine 3.15, Tailwind 3.4, Vite 5, Axios 1.6). Mención aparte: `spatie/laravel-ignition` es una herramienta de debug que **agrava el riesgo de `APP_DEBUG=true`** en producción al renderizar trazas interactivas — motivo adicional para asegurar que quede en `false`.

---

## 4. Checklist de salida a producción

Organizado por urgencia. Los ítems 🔴 deben resolverse **antes** de dar acceso a administradores/usuarios reales; los 🟠 deben resolverse en la primera semana de uso real; los 🟡 pueden programarse después.

### Antes de abrir el acceso (🔴 bloqueantes)
- [ ] `APP_DEBUG=false` en el `.env` de producción.
- [ ] `APP_ENV=production`, `APP_URL` apuntando al dominio real con HTTPS.
- [ ] Backups automatizados de la base de datos (mínimo: cron diario con `mysqldump` comprimido + rotación; ideal: `spatie/laravel-backup` con copia a almacenamiento externo).
- [ ] Decisión y configuración de storage persistente para imágenes (S3-compatible o confirmación de persistencia en Hostinger).
- [ ] Crear `resources/views/errors/404.blade.php` y `500.blade.php`.
- [ ] Configurar un proveedor SMTP real (`MAIL_MAILER`) si se quiere que los recordatorios/invitaciones lleguen de verdad — si no, comunicarlo explícitamente a los admins para que no asuman que las notificaciones funcionan.

### Primera semana de uso real (🟠)
- [ ] Agregar `throttle` a login/registro/recuperación de contraseña.
- [ ] Agregar `throttle` a `/torneos/validar`.
- [ ] Corregir el N+1 de `StatsController` (eager-load de `lineups`).
- [ ] `SESSION_SECURE_COOKIE=true` una vez HTTPS esté activo.
- [ ] Configurar `daily` en el canal de log con retención razonable (ej. 14 días).
- [ ] Revisar que los formularios de actualización de perfil de usuario no usen `$request->all()` sobre el modelo `User` (riesgo de mass assignment de `role`/`is_active`/`modules`).

### Mejoras recomendadas, no bloqueantes (🟡)
- [ ] Headers de seguridad (CSP, X-Frame-Options, X-Content-Type-Options).
- [ ] Integrar un monitor de errores (Sentry/Bugsnag/Flare — hay tier gratuito en los tres).
- [ ] Encolar notificaciones (`ShouldQueue`) antes de tener volumen alto de torneos simultáneos.
- [ ] Actualizar `.env.example` a los valores reales de FutGO (ya no SoyPachon).
- [ ] Incorporar PHPStan/Larastan al pipeline de CI.
- [ ] Resolver el flujo de "reclamo" de perfil para jugadores `por_verificar` (limitación #2 de la tabla de deuda técnica) — es lo que más friction genera para equipos con jugadores informales.
- [ ] Evaluar convertir las tarjetas compartibles de SVG a PNG para mejor experiencia al compartir en WhatsApp.

---

## 5. Resumen ejecutivo

El módulo Torneos está **funcionalmente completo y bien testeado** (458 tests pasando) para el flujo principal: crear torneo → inscribir equipos → generar fixture → cargar resultados → ver posiciones/estadísticas → portal público. La arquitectura de datos (clubes permanentes, identidad de jugador, reputación) es sólida y pensada para escalar a múltiples torneos.

Lo que falta **no es funcionalidad de producto, sino higiene de producción**: la plataforma hoy tiene cero backups automatizados, el debug de Laravel quedaría expuesto si se despliega tal cual, no hay rate limiting en los puntos de autenticación, el storage de imágenes no está garantizado a sobrevivir un despliegue, y los emails reales no se enviarán hasta configurar SMTP. Ninguno de estos puntos requiere rediseñar nada existente — son cambios de configuración y un puñado de archivos nuevos (vistas de error, throttle en rutas, backup). Resolviendo el bloque 🔴 de la checklist, la plataforma queda en condiciones razonables para que administradores de torneos y jugadores reales empiecen a usarla.
