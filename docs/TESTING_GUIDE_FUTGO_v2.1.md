# Guía Integral de Pruebas — FutGO v2.1

Consolida las guías de prueba en navegador de **todas las sesiones (A → G)** del módulo Torneos.
Las guías detalladas por sesión viven en la raíz (`SESION_X_GUIA_PRUEBA.md`); este documento es el índice maestro + checklist de regresión.

- **App local:** http://futgo.test:8080 (alternativa: `php artisan serve --port=8001`)
- **PATH de Laragon** (prepender antes de cualquier `php artisan`):
  ```powershell
  $env:Path = "C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64;C:\laragon\bin\composer;C:\laragon\bin\mysql\mysql-8.0.30-winx64\bin;$env:Path"
  ```
- **Datos demo:** `php artisan migrate --seed` (incluye AchievementSeeder) + `php artisan db:seed --class=DemoTournamentSeeder --force`
- **Reputación cacheada:** `php artisan torneos:rebuild-reputation`
- **Suite completa:** `php artisan test` → **380 passing**

Usuarios demo (password `Demo2026!`):
- Admin global: `admin@demo.futgo.com`
- Admin torneo: `admin.torneo@demo.futgo.com`
- Capitán Leones: `ldn.capitan@demo.futgo.com`
- Jugador Leones: `ldn.j1@demo.futgo.com`

---

## Sesión A — Identidad unificada jugador/capitán + jugadores no registrados
Detalle: `SESION_A_GUIA_PRUEBA.md`
- Capitanía derivada por equipo (no rol global); `team_players.is_captain`.
- Alta de jugador sin cuenta (`por_verificar`), anti-duplicados por user_id y documento.
- "Mis Equipos" lista equipos capitaneados.
- **Checklist:** crear equipo → soy capitán; agregar jugador sin cuenta → aparece "Por verificar"; no se puede quitar al capitán.

## Sesión B — Perfil permanente (jugador + club) + foto + acumulado histórico
Detalle: `SESION_B_GUIA_PRUEBA.md`
- Club permanente transversal (`clubs` + `teams.club_id`); acumulado en `player_career_stats`.
- Foto de perfil (`/perfil/foto`), componente `<x-avatar>`.
- **Checklist:** subir foto; ver Mi Carrera con acumulado; club muestra historial; finalizar torneo conserva y consolida stats.

## Sesión C — Dinámica del partido (convocatoria, MVP, bajas/cambios)
Detalle: `SESION_C_GUIA_PRUEBA.md`
- Convocatoria previa (capitán arma / jugador confirma-declina); MVP por torneo; bajas y cambios con `roster_movements`.
- **Checklist:** convocar → confirmar desde Mi Carrera; ingresar MVP en planilla; dar de baja conserva stats; cambio solo en `open`.

## Sesión D — Credencial QR antifraude
Detalle: `SESION_D_GUIA_PRUEBA.md`
- Identificador `FG-XXXXXX` único; credencial con QR (SVG, `bacon/bacon-qr-code`); validación por árbitro (QR o manual) auditada.
- **Checklist:** ver credencial (`/torneos/credencial`); validar habilitado/no habilitado (`/torneos/validar`); QR sin datos sensibles; validación manual sin cámara.

## Sesión E — Portal público + contenido compartible + exportación
Detalle: `SESION_E_GUIA_PRUEBA.md`
- Portal `/t/{slug}` (solo públicos, sin auth); tarjetas SVG compartibles; export PDF/CSV (dompdf + BOM UTF-8).
- **Checklist (incógnito):** abrir portal público; torneo privado → 404; descargar PDF/CSV; generar tarjeta de goleadores.

## Sesión F — Reputación (ranking, logros, fair play, temporadas, sorteo)
Detalle: `SESION_F_GUIA_PRUEBA.md`
- Ranking cacheado (`futgo_rankings`) jugadores/equipos × global/ciudad/categoría; logros automáticos; fair play; historial por temporada; desempate `drawing` reproducible/auditable.
- **Fórmulas:**
  - Ranking = goles·4 + asistencias·2 + MVP·6 + victorias·3 + vallas·2 + partidos·1 + fair_play·0.5
  - Fair play (jugador) = max(0, 100 − 3·amarillas − 10·rojas − 5·inasistencias); equipo = promedio.
- **Checklist:** `/torneos/ranking` con filtros; forzar logro y verlo en Mi Carrera; tarjetas bajan fair play; empate absoluto → sorteo reproducible.

## Sesión G — Recordatorios de partidos + patrocinadores
Detalle: `SESION_G_GUIA_PRUEBA.md`
- Comando `torneos:match-reminders` (convocados, idempotente, respeta `notifications_enabled`) en el scheduler junto a la polla; patrocinadores por torneo.
- **Checklist:** ejecutar comando → email en `storage/logs/laravel.log`; re-ejecutar → no duplica; `notifications_enabled=false` → nada; asociar patrocinador → aparece en el portal público.

---

## Despliegue en Hostinger (cron único)

Un solo cron cada minuto ejecuta el scheduler; Laravel orquesta polla + torneos:

```
* * * * * /bin/sh /home/USUARIO/domains/futgo.com/public_html/scheduler.sh
```

`scheduler.sh` (raíz del proyecto) corre `php artisan schedule:run`. Comandos programados (`routes/console.php`):
- `predictions:lock` — cada minuto (polla)
- `notifications:reminders` — cada minuto (polla)
- `results:sync` — cada 5 min (polla)
- `torneos:match-reminders` — cada hora (torneos, Sesión G)

Email: driver `log` en local; SMTP de Hostinger en producción.

---

## Regresión rápida

```powershell
php artisan optimize:clear
php artisan migrate:fresh --seed
php artisan db:seed --class=DemoTournamentSeeder --force
php artisan torneos:rebuild-reputation
php artisan test            # 380 passing
```
