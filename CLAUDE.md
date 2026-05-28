# CLAUDE.md — Contexto del proyecto SoyPachonMundial

> Este archivo se lee automáticamente al inicio de cada sesión.
> Mantenelo actualizado cuando cambien convenciones, stack o decisiones grandes.

---

## 1. ¿Qué es este proyecto?

**Soy Pachón Mundial** — plataforma web de pronósticos para el Mundial FIFA 2026.
Producción: **https://soypachonmundial.online** (Hostinger Premium).
Repositorio: https://github.com/efabianpq/soypachonmundial
Marca pública: **@SoyPachon** (con `@` dorado + "SoyPachon" en Barlow extrabold).

Usuarios reales pagan **$30.000 COP** por cupo y pronostican los 104 partidos del Mundial.
El acumulado se reparte **60% / 25% / 15%** entre el top 3 al cierre de la Final (19-jul-2026).

---

## 2. Stack técnico

| Capa | Tecnología | Notas |
|---|---|---|
| Lenguaje | PHP 8.3.30 | dentro de Laragon, **NO en PATH del sistema** |
| Framework | Laravel 11.46 | structure slim (Laravel 11), `bootstrap/app.php` |
| BD producción | MySQL 8 (Hostinger) | utf8mb4 |
| BD local dev | MySQL 8.4.3 vía Laragon | `soypachonmundial` / root / sin password |
| BD tests | SQLite in-memory | configurado en `phpunit.xml` |
| Frontend | Blade + Alpine.js 3 + Tailwind CSS 3 + Vite 5 | sin SPA, server-rendered |
| Email local | driver `log` | escribe a `storage/logs/laravel.log` |
| Email producción | SMTP Hostinger | `notificaciones@soypachonmundial.online` |
| PDF | `barryvdh/laravel-dompdf ^3.1` | letter landscape para auditoría |
| Forms plugin | `@tailwindcss/forms` | requerido por config Tailwind |

---

## 3. Entorno local (Laragon en Windows)

⚠️ **Los binarios de Laragon NO están en el PATH del sistema.** Prepender antes de cualquier comando PHP/Composer/MySQL:

```powershell
$env:Path = "C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64;C:\laragon\bin\composer;C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin;$env:Path"
```

Esto vive también en `~/.claude/projects/C--laragon-www-soyPachonMundial/memory/laragon-toolchain-paths.md` (memoria persistente).

Node + git **sí** están en el PATH del sistema.

Servidor de dev: `php artisan serve` en `http://127.0.0.1:8000`.

---

## 4. Comandos frecuentes

```powershell
# tests (SQLite memoria, ~3-5 seg)
php artisan test
php artisan test --filter=PredictionsTest

# build assets (Vite → public/build/)
npm run build

# servidor de dev
php artisan serve

# limpiar caches después de cambiar .env, rutas, vistas
php artisan optimize:clear

# correr scheduler manual (cron local no existe en Laragon)
php artisan schedule:work

# comandos custom del dominio
php artisan predictions:lock                       # bloquea pronósticos vencidos
php artisan predictions:calculate {match_id}       # calcula puntos de un partido
php artisan notifications:reminders                # envía recordatorios 15min antes

# seeders
php artisan migrate:fresh --seed                   # fixture limpio + admin + users de prueba
php artisan db:seed --class=DemoSeeder             # 10 demo users + 15 matches calculados + ranking
```

---

## 5. Estructura clave del proyecto

```
app/
├── Http/Controllers/
│   ├── Admin/             # panel admin (6 controllers)
│   ├── Auth/              # register/login/activate/password-reset
│   ├── PredictionsController.php     # index, update JSON, states JSON, byMatch JSON
│   ├── RankingController.php         # index, data JSON, show auditoría
│   └── AuditExportController.php     # CSV + PDF
├── Models/
│   ├── Game.php           # tabla "matches" (Match es palabra reservada en PHP 8)
│   ├── Prediction.php
│   ├── User.php
│   ├── Setting.php        # tabla settings clave-valor
│   ├── MatchNotification.php
│   └── ...
├── Services/
│   ├── PredictionScoringService.php  # lógica pura, 20 unit tests
│   ├── PredictionsCalculator.php     # orquesta scoring + ranking
│   └── RankingService.php
├── Support/Settings.php   # helper para BD settings (prizePool, videoUrl, etc.)
└── Notifications/
    └── PredictionReminderNotification.php

resources/views/
├── layouts/app.blade.php  # único layout, usa <x-nav>
├── components/            # design system Blade
│   ├── nav.blade.php
│   ├── match-card.blade.php       # Alpine-driven, 3 estados
│   ├── leaderboard.blade.php      # con medallas en top 3 + botón Ver Pronósticos
│   ├── badge.blade.php
│   ├── stat-card.blade.php
│   └── btn.blade.php
├── welcome.blade.php       # hero simple con CTA a /como-funciona
├── como-funciona.blade.php # guía pública completa (anchors + calculadora Alpine)
├── predictions/index.blade.php   # vista principal del participante (modal con $dispatch)
├── ranking/                # index + show (auditoría)
├── audit/                  # index + pdf
└── admin/                  # dashboard + 6 vistas

routes/
├── web.php                 # todo agrupado por auth/guest/active/admin
└── console.php             # 2 schedulers (lock + reminders) cada minuto

database/seeders/
├── DatabaseSeeder.php      # corre los seeders básicos en migrate:fresh --seed
├── MatchSeeder.php         # 72 partidos fase grupos del fixture FIFA real
├── EliminatorySeeder.php   # 32 placeholders (Clasificado A1, etc.)
├── DemoSeeder.php          # SEPARADO — no entra en migrate:fresh; corre con --class
└── ...
```

---

## 6. Design System

**Fuentes:** Barlow Condensed (display), DM Sans (body), JetBrains Mono (datos).

**Paleta** (definida en `tailwind.config.js`, reemplaza completa la default de Tailwind):

| Token | HEX | Uso |
|---|---|---|
| `pitch` | `#0a3d2e` | primario, CTA, headers |
| `pitch-deep` | `#06281e` | hover primario |
| `pitch-light` | `#14593f` | avatares, secundarios |
| `pitch-mist` | `#e8efe9` | badges sobre fondo claro |
| `gol` | `#f4d03f` | acento dorado, racha, trofeo |
| `gol-deep` | `#d4a82a` | hover acento |
| `alerta` | `#e74c3c` | live, error, cierre |
| `bone` | `#f5f1e8` | fondo global |
| `bone-soft` | `#faf7ef` | superficies elevadas |
| `ink` | `#1a1a1a` | texto principal |
| `ink-soft` / `ink-mute` | `#4a4a48` / `#8a8884` | secundarios |
| `line` / `line-soft` | `#e5dfd1` / `#efeadc` | bordes |

**Colorimetría de puntos** (unificada en match-card, modal y audit table):

```
5 pts → bg-gol text-pitch border-gol-deep         (oro · exacto)
3 pts → bg-pitch text-bone border-pitch-deep      (verde fuerte · ganador+1)
2 pts → bg-pitch-mist text-pitch border-pitch     (verde pálido · solo ganador)
1 pt  → bg-gol/30 text-pitch-deep border-gol/50   (oro tinte · un marcador)
0 pts → bg-line-soft text-ink-mute border-line    (gris · falla)
```

Si necesitás recrear esto en una nueva vista, copiá las mismas 5 clases — son la fuente de verdad.

Handoff original: `C:\Users\Usuario\AppData\Local\Temp\spm_branding\design_handoff_soypachon_design_system\` (descomprimido desde el ZIP del usuario).

---

## 7. Reglas de negocio críticas

### Sistema de puntuación

Solo cuenta el **tiempo reglamentario** (no penales ni prórroga).

| Pts | Condición |
|---:|---|
| 5 | Ambos marcadores exactos en su lado |
| 3 | Ganador correcto + un marcador exacto **en su lado** |
| 2 | Solo ganador correcto, ningún marcador exacto |
| 1 | Ganador **incorrecto** + un marcador exacto en su lado (XOR same-side) |
| 0 | Cualquier otra cosa, incluido el "espejo" (pred 1-2 vs result 2-1 → 0 pts) |

**Importante**: la regla de 1 pt es **estricta same-side** — el espejo cruzado NO suma. Esta regla fue corregida (ver commit `cb01773`).

**Desempate**: por cantidad de exactos (5 pts) descendente.

### Distribución de premios

`App\Support\Settings::prizeBreakdown()` retorna **60/25/15** del pool total. No hay descuento para plataforma — los costos van aparte.

### Bloqueo de pronósticos

`lock_datetime = match_datetime - 5 minutos`. El comando `predictions:lock` corre cada minuto vía scheduler y marca `predictions.is_locked=true` + `matches.status='live'`.

---

## 8. Convenciones de código

### Commits

Formato: `tipo: descripción corta en español`

Tipos:
- `feat:` — feature nueva
- `fix:` — corrección
- `design:` — cambios visuales (en aplicación del design system)
- `build:` — assets, dependencies, .gitignore
- `release:` — tags de versión
- `deploy:` — docs de producción
- `refactor:` — sin cambio funcional

Co-author al final:
```
Co-Authored-By: Claude Opus 4.7 <noreply@anthropic.com>
```

Identidad git (no usar global, siempre per-command):
```bash
git -c user.name="efabianpq" -c user.email="efabianpq@users.noreply.github.com" commit ...
```

### Componentes Blade

Los componentes con Alpine **no deben usar `$root.methodName(...)`** — `$root` devuelve un DOM element, no la data. Usar **`$dispatch('event-name', {data})`** con listener `.window` en el padre. Ver `match-card.blade.php` y modal en `predictions/index.blade.php` como referencia (commit `a98b59c`).

### Tests

- Feature tests usan `RefreshDatabase` + SQLite memoria
- Tests del scoring service usan `#[DataProvider]` para casos parametrizados
- 86 tests baseline pasando (309 assertions) — mantenerlos verdes
- Para correr tests del dominio crítico: `php artisan test --filter=PredictionScoringServiceTest`

### Modelos

- `Match` está reservado en PHP 8, por eso el modelo se llama **`Game`** con `$table = 'matches'`
- Cuidado con foreign keys: las predictions referencian `match_id` no `game_id`

---

## 9. Producción / Deploy

### Workflow para cambios visuales o estructurales

```bash
# LOCAL
npm run build              # genera public/build/ con hash nuevo
git add -A
git commit -m "..."
git push

# SERVIDOR (SSH Hostinger puerto 65002)
cd ~/soypachonmundial
git pull origin master

# IMPORTANTE: PHP 8.3 y Composer en Hostinger necesitan ruta completa
/opt/alt/php83/usr/bin/php /usr/local/bin/composer install --no-dev --optimize-autoloader
/opt/alt/php83/usr/bin/php artisan migrate --force        # si hay migraciones nuevas
/opt/alt/php83/usr/bin/php artisan optimize:clear
/opt/alt/php83/usr/bin/php artisan config:cache
/opt/alt/php83/usr/bin/php artisan route:cache
/opt/alt/php83/usr/bin/php artisan view:cache
```

Para evitar tipear las rutas cada vez, crear alias en `~/.bashrc`:
```bash
alias php83="/opt/alt/php83/usr/bin/php"
alias composer83="/opt/alt/php83/usr/bin/php /usr/local/bin/composer"
```

Luego `source ~/.bashrc` y ya funciona `php83 artisan ...` y `composer83 install ...`

⚠️ **`public/build/` se commitea al repo** (excepción al default de Laravel) porque Hostinger Premium no tiene Node disponible para builds en el servidor. Sin esto, el sitio queda con CSS viejo. Ver commit `c069e5a` por contexto.

Guía completa en `DEPLOY.md`.

### .env de producción

Variables clave que NO están en el repo y deben configurarse manualmente en el server:
- `APP_KEY` (generado, único)
- `APP_NAME="@SoyPachon"`
- `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` (de hPanel)
- `MAIL_PASSWORD` (del email de Hostinger)

Template en `.env.production.example`.

### Cron en Hostinger

Un solo cron job cada minuto:
```
cd /home/u123XXXXXX/soypachonmundial && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

Esto dispara los 2 schedulers: `predictions:lock` + `notifications:reminders`.

---

## 10. Lecciones aprendidas y gotchas

| Tema | Lección |
|---|---|
| Alpine `$root` | No accede a la data — usar `$dispatch` + listener `.window` |
| Tailwind purge | Las clases arbitrarias (`grid-cols-[48px_1fr]`) solo se compilan si están en alguna Blade. No generar clases dinámicas desde JS sin tenerlas también en Blade |
| Hostinger sin Node | Por eso `public/build` se commitea |
| Colorimetría de puntos | Mantener las **5 clases unificadas** en match-card, modal, audit table — son fuente de verdad |
| Distribución premios | **60/25/15** — el total cubre 100% del pozo. Si llega un pedido de "10% para plataforma", aclarar antes de cambiar (porque el ejemplo aritmético del cliente asume 100% para premios) |
| Tests del welcome | Cuando se cambia mucho la home, recordar actualizar `WelcomeVideoTest` y `SettingsAdminTest` que pueden buscar elementos viejos |
| Browser cache | Después de cada deploy con cambios CSS, Ctrl+Shift+R en el navegador o probar en incógnito |
| MAIL_MAILER local | `log` en desarrollo — los emails se escriben en `storage/logs/laravel.log`. Para verlos: `Get-Content storage\logs\laravel.log -Tail 80` |
| Logout | Redirige a **`/`** (home) no a `/login` — para que el usuario vea la portada |
| Sesiones SSH Hostinger | Puerto **65002** (no 22). Llave SSH recomendada. |
| MailMessage `->table()` | No existe en `MailMessage` — usar `->line()` por cada ítem. Ver `WelcomeNotification` como referencia. |
| Email templates vendor | Publicados en `resources/views/vendor/mail/html/`. Los colores del design system van en `themes/default.css`. El `message.blade.php` tiene un footer hardcodeado en el `x-slot:footer` — nuestro `footer.blade.php` lo sobrescribe correctamente ignorando el slot. |

---

## 11. Estado del proyecto

### Funcionalidad implementada (MVP completo)

- ✅ Auth: register con teléfono, login, activación con código SPM-XXXX, recuperación
- ✅ Pronósticos: 104 partidos, autosave Alpine, bloqueo 5min antes, polling 30s
- ✅ Motor de puntos: scoring service + comando + recalculo automático del ranking
- ✅ Ranking público para usuarios activos con medallas + botón Ver Pronósticos
- ✅ Vista auditoría por usuario (`/ranking/u/{id}`) con resumen final y aprovechamiento %
- ✅ Modal "Ver Pronósticos" por partido (bloqueado o finalizado)
- ✅ Panel admin: dashboard, códigos, usuarios, fixture, resultados, settings
- ✅ Exportación auditoría: CSV + PDF (dompdf, landscape, branding)
- ✅ Notificaciones por email (driver log local, SMTP en prod) — recordatorio 15min antes
- ✅ Email de bienvenida al activar código SPM (`WelcomeNotification`) — rama `feat/email-smtp`
- ✅ Templates HTML de email con branding SPM (pitch/gol/bone) — `resources/views/vendor/mail/`
- ✅ SMTP Hostinger verificado: `smtp.hostinger.com:465 SSL` — ver `.env.example` para variables
- ✅ Página pública `/como-funciona` con calculadora Alpine de premios
- ✅ Design system aplicado completo (handoff Claude Design)

### Próximas evoluciones probables

- Notificaciones por WhatsApp (Fase 3 según requerimientos doc)
- Resultados desde API-Football (Fase 3)
- Integración con MercadoPago / PSE para pagos online
- Sistema de invitaciones automáticas por email

### Estado actual

| Métrica | Valor |
|---|---|
| Tests | 89 passing (325 assertions) |
| Tag de release | `v1.0.0` (`c774a8f`) |
| Último commit master | ver `git log --oneline -1` |
| Producción | https://soypachonmundial.online |

---

## 12. Cómo trabajar conmigo en sesiones futuras

### Para arrancar bien una sesión

1. Iniciá con un objetivo claro: "quiero agregar X feature" o "necesito arreglar Y bug"
2. Yo voy a leer este `CLAUDE.md` automáticamente — no hace falta que lo pegues
3. Si necesitás contexto histórico de algo específico:
   - `git log --oneline -20` muestra los últimos 20 commits con su mensaje
   - `git show <hash>` muestra el detalle de un commit puntual
   - `CHANGELOG.md` tiene el resumen de funcionalidades por versión
   - `DEPLOY.md` tiene el detalle operativo de producción
4. Si querés que tome decisiones de diseño, pedíme primero un plan antes de codear

### Convenciones de comunicación

- En español, voseo argentino-colombiano
- Mensajes concisos, sin emoji decorativo
- Tablas para comparativas, código en bloques marcados con lenguaje
- Si voy a hacer algo grande/irreversible, te pregunto primero
- Si encuentro algo fuera del scope que vale la pena arreglar, lo flagueo con `mcp__ccd_session__spawn_task` o lo menciono al final

### Cuando el contexto se sienta saturado

- **Empezá una sesión nueva**. No tiene costo: este CLAUDE.md más git history me dan todo lo que necesito.
- Mencioná brevemente qué hicimos al final de la sesión anterior si el commit no lo refleja claro
- Para temas muy específicos (algún bug que reproducís repetidamente), considerá agregarlo a la sección "Lecciones aprendidas" arriba

### Mantener CLAUDE.md vivo

Cuando se tomen decisiones importantes que afecten cómo se trabaja en el proyecto:
- Cambios de tech stack
- Nuevas convenciones de código
- Gotchas descubiertos
- Patrones nuevos que se establecen

**Pedíme actualizar esta `CLAUDE.md`** — yo lo hago en el commit del cambio. Mejor 5 minutos en mantenerlo que perder horas reconstruyendo decisiones después.

---

*Última actualización: cuando se agregaron las funcionalidades de modal Ver Pronósticos, exportación auditoría y página ¿Cómo funciona?. Si modificás algo importante, actualizá esta línea.*
