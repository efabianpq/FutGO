# Informe de Seguridad, Disponibilidad y Optimización — FutGO
# Preparación para Alta Demanda y Acceso Público (Play Store / App Store)

**Fecha:** 1 de julio de 2026
**Versión de la aplicación:** FutGO v2 — módulos Torneos + FutGO Social (Fases 1, 2, 3) completos
**Stack:** PHP 8.3.30 · Laravel 11.46 · MySQL 8.0.30 · Blade + Alpine.js 3 + Tailwind 3
**Hosting actual:** Hostinger
**Dominio:** https://futgo.online
**App mobile:** Capacitor (WebView wrapper) — Android (Play Store) + iOS (App Store)

---

## Índice

1. Estado actual de seguridad
2. Sesiones y autenticación
3. Seguridad del transporte y headers HTTP
4. Protección contra ataques comunes
5. Protección de datos sensibles
6. Escalabilidad y rendimiento
7. Disponibilidad y backups
8. Seguridad específica para la app mobile
9. Cumplimiento legal (Play Store / App Store / usuarios)
10. Checklist priorizado de implementación
11. Prompts de implementación para Claude Code

---

## 1. Estado actual de seguridad

### 1.1 Lo que ya está resuelto (no tocar)

| Ítem | Estado | Dónde |
|---|---|---|
| HTTPS activo | ✅ | Hostinger + certificado SSL |
| APP_DEBUG=false en producción | ✅ | .env producción |
| CSRF activo | ✅ | Laravel por defecto |
| Passwords con bcrypt | ✅ | Laravel por defecto |
| Backups automatizados | ✅ | spatie/laravel-backup, diario 03:00, rotación 7 días |
| Storage de medios en R2 | ✅ | MEDIA_DISK=r2 (Cloudflare R2) |
| Vistas de error 404/500 | ✅ | resources/views/errors/ |
| QR sin datos sensibles | ✅ | CredentialService — solo futgo_id + firma HMAC |
| Validación de imágenes subidas | ✅ | image, mimes:jpg,jpeg,png,webp, max:2048 |
| Moderación básica de contenido | ✅ | FutGO Social — reporte de contenido implementado |
| Credenciales de demo separadas | ✅ | DemoSeeder con usuarios específicos |

### 1.2 Lo que falta — resumen ejecutivo

| Categoría | Ítems pendientes | Prioridad |
|---|---|---|
| Sesiones | Duración corta, driver en archivo | 🔴 Antes de publicar |
| Rate limiting | Ausente en login/registro/QR | 🔴 Antes de publicar |
| Headers de seguridad | Sin CSP, HSTS, X-Frame | 🔴 Antes de publicar |
| Datos sensibles | Campo `document` en texto plano | 🟠 Primera semana |
| Caché | Sin caché de respuestas HTTP | 🟠 Primera semana |
| Queue workers | Jobs síncronos en producción | 🟠 Primera semana |
| CDN | Assets servidos desde Hostinger | 🟠 Primera semana |
| Monitoreo | Sin alertas de errores en tiempo real | 🟡 Primer mes |
| Análisis estático | Sin PHPStan en CI | 🟡 Primer mes |
| Sanctum/tokens | Solo sesiones PHP clásicas | 🟡 Con volumen real |

---

## 2. Sesiones y autenticación

### 2.1 Problema actual

Las sesiones se guardan en archivos (`storage/framework/sessions/`). Con cientos de usuarios concurrentes, PHP hace lock de archivo por sesión, el disco se convierte en cuello de botella y las sesiones expiran de forma brusca — el usuario ve una pantalla en blanco o es redirigido al login sin aviso, lo cual en una app mobile es una experiencia muy mala.

El lifetime por defecto de Laravel es 120 minutos. Un usuario que abre la app al día siguiente encuentra su sesión vencida.

### 2.2 Solución inmediata — driver de sesión en base de datos

Mover sesiones a MySQL elimina el problema de lock de archivos y hace las sesiones durables frente a reinicios del servidor.

**Paso 1:** crear la tabla de sesiones:
```bash
php artisan session:table
php artisan migrate
```

**Paso 2:** en `config/session.php`:
```php
'driver' => env('SESSION_DRIVER', 'database'),
'lifetime' => env('SESSION_LIFETIME', 10080),  // 7 días en minutos
'expire_on_close' => false,
```

**Paso 3:** en `.env` de producción:
```env
SESSION_DRIVER=database
SESSION_LIFETIME=10080
```

**Por qué 7 días:** en una app mobile el usuario espera no tener que volver a loguearse salvo que él mismo cierre sesión. 7 días es el estándar de apps como Instagram o WhatsApp para uso casual diario.

### 2.3 Solución a mediano plazo — Redis para sesiones y caché

Si Hostinger lo permite en el plan contratado (planes Business o superiores incluyen Redis), migrar a Redis es el paso natural cuando el volumen crece:

```env
SESSION_DRIVER=redis
CACHE_STORE=redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

Redis mantiene sesiones en memoria RAM — órdenes de magnitud más rápido que MySQL para lecturas/escrituras de sesión, y soporta expiración nativa sin necesidad de limpiar filas viejas.

### 2.4 Protección de sesión contra robo (session fixation / hijacking)

En `config/session.php` verificar que estén activos:

```php
'secure' => env('APP_ENV') === 'production',  // ya configurado en Fase 1
'same_site' => env('APP_ENV') === 'production' ? 'none' : 'lax',  // ya configurado
'http_only' => true,   // la cookie no es accesible desde JavaScript
'encrypt' => true,     // Laravel encripta el contenido de la cookie con APP_KEY
```

El `encrypt => true` es el único que puede faltar — hace que aunque alguien intercepte la cookie, no pueda leer ni modificar su contenido sin el APP_KEY del servidor.

### 2.5 Regeneración de sesión en login

Laravel regenera el session ID automáticamente al hacer login con `Auth::login()` o al usar el scaffold de autenticación. Verificar que ningún flujo de login manual llame `session()->put()` sin antes llamar `session()->regenerate()`. Esto previene ataques de session fixation donde un atacante pre-establece un session ID conocido.

### 2.6 Tokens con Sanctum (para fase futura con API nativa)

Cuando FutGO migre a una API REST + frontend desacoplado (React Native o similar), Sanctum provee tokens por dispositivo con expiración configurable. No implementar ahora para el WebView — las sesiones con cookie funcionan bien — pero documentar el path para no tener que rediseñar más adelante:

```bash
composer require laravel/sanctum
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
php artisan migrate
```

Los tokens de Sanctum se guardan en `personal_access_tokens` y se invalidan individualmente por dispositivo, lo que permite "cerrar sesión en todos mis dispositivos" — funcionalidad estándar en apps móviles.

---

## 3. Seguridad del transporte y headers HTTP

### 3.1 Headers de seguridad HTTP

Los headers de seguridad le indican al browser y al WebView de Capacitor cómo comportarse ante contenido potencialmente peligroso. Ninguno de estos está configurado actualmente.

**Implementación recomendada:** crear un middleware global en Laravel.

```php
// app/Http/Middleware/SecurityHeaders.php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // Fuerza HTTPS por 1 año e incluye subdominios
        $response->headers->set(
            'Strict-Transport-Security',
            'max-age=31536000; includeSubDomains; preload'
        );

        // Previene que el browser "adivine" el tipo de contenido
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // Previene que la app sea embebida en un iframe externo (clickjacking)
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');

        // Controla qué información de referrer se envía al navegar
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Controla qué APIs del browser puede usar el frontend
        $response->headers->set(
            'Permissions-Policy',
            'camera=(self), microphone=(), geolocation=(self), payment=()'
        );

        return $response;
    }
}
```

Registrar en `bootstrap/app.php`:
```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->append(\App\Http\Middleware\SecurityHeaders::class);
})
```

### 3.2 Content Security Policy (CSP)

CSP es el header más poderoso y el más complejo de configurar. Define exactamente desde dónde puede cargar recursos el browser — si un atacante inyecta un script externo, CSP lo bloquea.

Para FutGO con Alpine.js, Vite y Cloudflare R2, la política base es:

```php
$csp = implode('; ', [
    "default-src 'self'",
    "script-src 'self' 'unsafe-inline'",   // unsafe-inline necesario para Alpine.js inline
    "style-src 'self' 'unsafe-inline'",    // necesario para Tailwind generado inline
    "img-src 'self' data: blob: https://*.r2.cloudflarestorage.com https://futgo.online",
    "font-src 'self' data:",
    "connect-src 'self' https://futgo.online",
    "frame-ancestors 'self'",
    "base-uri 'self'",
    "form-action 'self'",
]);

$response->headers->set('Content-Security-Policy', $csp);
```

**Nota importante:** Alpine.js con directivas `x-on:` y `@click` inline requiere `unsafe-inline` en `script-src`. Esto reduce el beneficio del CSP para XSS inline, pero sigue siendo valioso para bloquear carga de scripts externos. Si en el futuro Alpine.js se migra a archivos externos compilados por Vite, se puede eliminar `unsafe-inline`.

### 3.3 HSTS Preload

Una vez que `Strict-Transport-Security` está activo y verificado en producción, registrar el dominio en https://hstspreload.org. Esto hace que Chrome, Firefox y Safari nunca intenten HTTP para `futgo.online`, incluso en la primera visita — eliminando el vector de ataque de SSL stripping.

Requisitos para el preload:
- HTTPS funcionando sin errores
- Redireccionamiento de HTTP a HTTPS activo
- Header HSTS con `max-age >= 31536000`, `includeSubDomains` y `preload`

---

## 4. Protección contra ataques comunes

### 4.1 Rate Limiting — crítico y ausente

Sin rate limiting, cualquiera puede hacer miles de intentos de login por segundo desde un script. Es el ataque más básico y el más fácil de prevenir.

**En `routes/web.php`**, envolver las rutas de autenticación:

```php
Route::middleware(['throttle:10,1'])->group(function () {
    Route::post('/login', [AuthController::class, 'store']);
    Route::post('/register', [RegisterController::class, 'store']);
    Route::post('/forgot-password', [PasswordController::class, 'store']);
    Route::post('/reset-password', [NewPasswordController::class, 'store']);
});
```

`throttle:10,1` = máximo 10 intentos por minuto por IP. Después del décimo intento, Laravel devuelve HTTP 429 (Too Many Requests) automáticamente.

**Para la validación de QR** (`/torneos/validar`), un límite más generoso pero presente:

```php
Route::middleware(['throttle:60,1'])->group(function () {
    Route::get('/torneos/validar', [CredentialValidationController::class, 'index']);
    Route::post('/torneos/validar', [CredentialValidationController::class, 'validate']);
});
```

**Para la API de búsqueda global** (autocompletado de canchas, buscador):

```php
Route::middleware(['throttle:100,1'])->group(function () {
    Route::get('/buscar', [GlobalSearchController::class, 'index']);
    Route::get('/canchas/search', [VenueController::class, 'search']);
});
```

**Para las rutas del Feed y Oportunidades** (usuarios autenticados normales):

```php
Route::middleware(['auth', 'throttle:300,1'])->group(function () {
    // rutas del feed, oportunidades, mensajes
});
```

### 4.2 Protección contra SQL Injection

Laravel con Eloquent usa PDO con prepared statements en todas las queries, lo que previene SQL injection por defecto. El riesgo aparece solo cuando se usa el query builder con interpolación de strings directa:

```php
// PELIGROSO — nunca hacer esto
DB::select("SELECT * FROM users WHERE name = '$name'");

// CORRECTO — siempre usar bindings
DB::select("SELECT * FROM users WHERE name = ?", [$name]);
// o mejor aún
User::where('name', $name)->get();
```

**Acción:** ejecutar búsqueda en el codebase de cualquier uso de `DB::statement()` o `DB::select()` con concatenación de strings. En FutGO con Eloquent puro esto debería ser seguro, pero vale la verificación.

### 4.3 Protección contra XSS

Blade escapa automáticamente todo lo que se imprime con `{{ }}`. El riesgo aparece con `{!! !!}` (salida sin escapar). 

**Acción:** buscar todos los usos de `{!! !!}` en las vistas y verificar que cada uno sea intencional y que la fuente del dato sea confiable (datos propios del sistema, no input del usuario):

```bash
grep -r "{!!" resources/views/ --include="*.php"
```

Los únicos casos aceptables son: SVG generados por el sistema, HTML generado por el propio servidor (como los SVG de tarjetas compartibles), nunca input de usuario.

### 4.4 Mass Assignment Protection

El informe de producción identificó que `$fillable` de `User` incluye `role`, `is_active` y `modules`. Si algún controlador usa `$request->all()` en un update de perfil, un usuario malicioso podría enviarse `role=admin` y escalarse a administrador.

**Acción:** verificar que todos los formularios de actualización de perfil de usuario usen solo los campos explícitos:

```php
// PELIGROSO
$user->update($request->all());

// CORRECTO
$user->update($request->only(['name', 'email', 'avatar_url']));
// o usar Form Requests con validated()
$user->update($request->validated());
```

Buscar en controladores:
```bash
grep -r "request->all()" app/Http/Controllers/ --include="*.php"
```

### 4.5 Protección contra ataques de fuerza bruta con bloqueo progresivo

El rate limiting de Laravel bloquea por IP, pero un atacante con muchas IPs (botnet) puede eludirlo. Para login, agregar bloqueo progresivo por cuenta atacada usando `RateLimiter` de Laravel:

```php
// En el controlador de login, después de un intento fallido
use Illuminate\Support\Facades\RateLimiter;

$key = 'login:' . Str::lower($request->email) . '|' . $request->ip();

if (RateLimiter::tooManyAttempts($key, 5)) {
    $seconds = RateLimiter::availableIn($key);
    throw ValidationException::withMessages([
        'email' => "Demasiados intentos. Intentá de nuevo en {$seconds} segundos.",
    ]);
}

// Si el login falla:
RateLimiter::hit($key, 300); // bloqueo de 5 minutos

// Si el login es exitoso:
RateLimiter::clear($key);
```

Esto bloquea por combinación cuenta+IP — 5 intentos fallidos sobre la misma cuenta desde la misma IP la bloquea 5 minutos, independientemente del throttle global.

### 4.6 Protección de la ruta de validación QR contra enumeración

La ruta `/torneos/validar?fg=FG-XXXXXX` podría ser usada para enumerar todos los `futgo_id` válidos del sistema si no tiene rate limiting adecuado. Ya está contemplado en §4.1. Adicionalmente, las respuestas de "no encontrado" deben tener el mismo tiempo de respuesta que las de "encontrado" para evitar timing attacks:

```php
// En CredentialValidationController
// Agregar un delay mínimo uniforme
usleep(random_int(50000, 150000)); // 50-150ms de delay aleatorio
```

---

## 5. Protección de datos sensibles

### 5.1 Encriptación del campo `document`

El campo `users.document` y `club_players.document` almacenan números de documento de identidad en texto plano. En Colombia, el número de cédula es un dato personal sensible. Si la base de datos es comprometida, estos datos quedan expuestos directamente.

Laravel 9+ incluye encriptación transparente de atributos Eloquent:

```php
// En app/Models/User.php
use Illuminate\Database\Eloquent\Casts\AsEncryptedString;

protected $casts = [
    'document' => AsEncryptedString::class,
];

// En app/Models/Torneos/ClubPlayer.php
protected $casts = [
    'document' => AsEncryptedString::class,
];
```

**Importante:** la encriptación usa el `APP_KEY` del `.env`. Una vez encriptados, los valores en base de datos son ilegibles sin el APP_KEY. Esto tiene dos implicaciones:

1. No se puede hacer `WHERE document = ?` directamente — las búsquedas por documento requieren desencriptar en PHP. En FutGO el documento se usa para detección de duplicados y reclamo de perfil, no para búsquedas masivas, por lo que el impacto de rendimiento es mínimo.

2. Antes de encriptar, hacer backup completo de la base de datos. La migración encripta los valores existentes en un comando de consola:

```bash
php artisan encrypt:model-attributes
```

### 5.2 APP_KEY — protección y rotación

El `APP_KEY` es la clave maestra de Laravel: encripta cookies, sesiones y (con AsEncryptedString) los campos sensibles de la base de datos. Si se compromete, todo lo encriptado puede ser descifrado.

**Reglas de manejo:**
- Nunca commitear el `.env` al repositorio (verificar que `.env` está en `.gitignore`)
- Guardar el APP_KEY de producción en un gestor de secretos (Bitwarden, 1Password, o simplemente un documento offline seguro)
- Si se sospecha que el APP_KEY fue expuesto, rotarlo con `php artisan key:generate` — esto invalida todas las sesiones y requiere re-encriptar campos encriptados

### 5.3 Variables de entorno sensibles

Verificar que el `.env` de producción no contiene valores de desarrollo:

```env
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:...   # único, nunca compartido

DB_PASSWORD=...      # contraseña fuerte, no vacía como en dev
MAIL_PASSWORD=...    # credenciales SMTP reales

# R2 / S3
AWS_SECRET_ACCESS_KEY=...   # credenciales de Cloudflare R2
```

En Hostinger, las variables de entorno pueden configurarse desde el panel de control en vez de en un archivo `.env` físico en el servidor — esto es más seguro porque el archivo no es accesible desde el webroot.

### 5.4 Privacidad en el buscador global

`GlobalSearchController` busca jugadores, clubes, torneos y canchas. Verificar que nunca expone email, teléfono ni documento en los resultados:

```php
// CORRECTO — solo datos públicos
User::select(['id', 'name', 'futgo_id', 'avatar_url', 'city'])
    ->where('name', 'like', "%{$term}%")
    ->get();

// NUNCA incluir en select:
// email, phone, document, password, remember_token
```

Este punto ya está documentado en CLAUDE.md §11 pero vale la verificación explícita en el código.

### 5.5 Logs — no loggear datos sensibles

Verificar que ningún `Log::info()` o `Log::debug()` en el codebase loggea passwords, tokens, documentos o emails completos. Los logs en producción rotan cada 14 días (`daily` driver configurado) pero igual son texto plano en disco.

```bash
grep -r "Log::" app/ --include="*.php" | grep -i "password\|token\|document\|secret"
```

---

## 6. Escalabilidad y rendimiento

### 6.1 N+1 queries — problema confirmado

El informe de producción identificó un N+1 en `StatsController` donde se ejecuta una query dentro de un loop. Con muchos usuarios viendo estadísticas simultáneamente, esto multiplica la carga de MySQL.

**Corrección en `StatsController`:**

```php
// ANTES — N+1: una query por partido
$matches->map(function($match) {
    $lineup = $match->lineups()->where(...)->first(); // query por iteración
});

// DESPUÉS — eager loading: una sola query
$matches->load(['lineups' => function($query) {
    $query->where(...);
}]);
```

**Herramienta de detección:** instalar Laravel Debugbar en desarrollo para identificar N+1 automáticamente:

```bash
composer require barryvdh/laravel-debugbar --dev
```

Debugbar muestra el conteo de queries por request y marca las duplicadas.

### 6.2 Caché de respuestas HTTP

Las páginas más visitadas de FutGO (portal público de torneo, standings, ranking) son de solo lectura y cambian con poca frecuencia. Cachear la respuesta HTTP completa elimina el procesamiento PHP y las queries MySQL para cada visita.

**Caché de rutas públicas en Laravel:**

```php
// En PublicTournamentController
public function show(Tournament $tournament)
{
    $cacheKey = "tournament.public.{$tournament->slug}";
    
    $data = Cache::remember($cacheKey, 300, function() use ($tournament) {
        // toda la lógica de TournamentReportService
        return $this->reportService->getData($tournament);
    });
    
    return view('torneos.public.show', $data);
}
```

El tiempo de 300 segundos (5 minutos) es razonable para standings — un resultado que se carga a las 3pm sigue siendo válido a las 3:05pm, y en ese tiempo pueden servirse miles de requests desde caché sin tocar la base de datos.

**Invalidación del caché al actualizar resultados:**

```php
// En MatchResultController::store(), después de guardar el resultado
Cache::forget("tournament.public.{$tournament->slug}");
Cache::forget("standings.{$phase->id}");
```

**Driver de caché recomendado:**

```env
# Si Redis disponible (mejor):
CACHE_STORE=redis

# Si no hay Redis (aceptable para empezar):
CACHE_STORE=database
```

Para el driver `database`, crear la tabla:
```bash
php artisan cache:table
php artisan migrate
```

### 6.3 Queue Workers — jobs asíncronos

Actualmente, la generación de tarjetas PNG (`GenerateShareCardPng` job) y el envío de emails corre de forma síncrona — bloquean el request del usuario hasta completarse. Con muchos usuarios simultáneos esto genera timeouts.

`QUEUE_CONNECTION=database` ya está configurado según CLAUDE.md. El problema es que si no hay un worker corriendo, los jobs quedan en la tabla `jobs` sin ejecutarse nunca.

**Configurar worker en Hostinger:**

En el panel de Hostinger, en la sección de Cron Jobs, agregar junto al cron del scheduler:

```bash
# Cron existente (cada minuto, scheduler de Laravel)
* * * * * /usr/bin/php /home/usuario/public_html/artisan schedule:run >> /dev/null 2>&1

# Worker de colas (verificar y reiniciar cada 5 minutos)
*/5 * * * * /usr/bin/php /home/usuario/public_html/artisan queue:work --max-time=270 --tries=3 --stop-when-empty >> /home/usuario/logs/queue.log 2>&1
```

`--max-time=270` hace que el worker se detenga solo a los 270 segundos (4.5 minutos), justo antes del próximo cron que lo reinicia. `--stop-when-empty` lo detiene cuando no hay jobs pendientes, ahorrando recursos.

**Hacer que el envío de emails sea asíncrono:**

```php
// En MatchReminderNotification
class MatchReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;
    // resto de la clase sin cambios
}
```

Agregar `implements ShouldQueue` y `use Queueable` a todas las notificaciones que envían email.

### 6.4 Optimizaciones de base de datos

**Índices faltantes frecuentes en apps como FutGO:**

```sql
-- Verificar que existan estos índices (MySQL los muestra con SHOW INDEX FROM tabla)

-- Búsquedas por ciudad (oportunidades, canchas, ranking)
ALTER TABLE opportunities ADD INDEX idx_city_status (city, status);
ALTER TABLE clubs ADD INDEX idx_city_level (city, level);
ALTER TABLE venues ADD INDEX idx_city_active (city, is_active);

-- Feed por usuario y fecha
ALTER TABLE feed_events ADD INDEX idx_user_created (user_id, created_at);

-- Mensajes por conversación
ALTER TABLE messages ADD INDEX idx_conversation_created (conversation_id, created_at);

-- Follows para el feed
ALTER TABLE follows ADD INDEX idx_follower_type (follower_id, followable_type);
```

**Nota:** Laravel crea índices en las migraciones con `->index()`. Verificar en el código de migraciones que las columnas de filtros frecuentes los tengan. Si no, agregar migraciones de índice sin tocar datos.

**Configuración de MySQL para producción:**

En el `my.cnf` de Hostinger (si es accesible), o solicitarlo al soporte:

```ini
innodb_buffer_pool_size = 256M   # caché de datos en RAM (aumentar si hay RAM disponible)
query_cache_size = 64M           # caché de resultados de queries repetidas
max_connections = 150            # máximo de conexiones simultáneas
```

### 6.5 Optimización de assets con Vite

Verificar que el build de producción de Vite esté optimizado:

```bash
npm run build
```

El `vite.config.js` debe incluir:

```javascript
build: {
    rollupOptions: {
        output: {
            manualChunks: {
                vendor: ['alpinejs'],  // Alpine.js en chunk separado con cache largo
            }
        }
    },
    minify: 'terser',
    cssMinify: true,
}
```

Los archivos de Vite tienen hash en el nombre (`app-abc123.js`), lo que permite cache headers muy agresivos en el servidor:

```apache
# En .htaccess de Hostinger
<FilesMatch "\.(js|css|woff2|png|jpg|svg)$">
    Header set Cache-Control "public, max-age=31536000, immutable"
</FilesMatch>
```

### 6.6 Cloudflare como CDN y proxy inverso

Cloudflare gratuito puesto delante de `futgo.online` provee:

- CDN global para assets estáticos (JS, CSS, imágenes)
- Caché de páginas públicas (portal de torneo, ranking)
- Protección DDoS básica sin costo
- Compresión Brotli/GZIP automática
- HTTP/2 y HTTP/3

**Configuración recomendada en Cloudflare:**

```
SSL/TLS: Full (strict)
Auto Minify: JavaScript ✓, CSS ✓, HTML ✓
Brotli: ON
Browser Cache TTL: 4 horas para HTML, 1 año para assets con hash
```

**Regla de caché para el portal público:**

En Cloudflare → Rules → Cache Rules:
- URL matches: `futgo.online/t/*`
- Cache level: Cache Everything
- Edge Cache TTL: 5 minutos

Esto hace que miles de visitas al portal de un torneo en progreso sean servidas desde los servidores de Cloudflare, sin llegar a Hostinger.

---

## 7. Disponibilidad y backups

### 7.1 Estado actual de backups

`spatie/laravel-backup` configurado con:
- `backup:run --only-db` diario a las 03:00
- `backup:clean` a las 03:30
- Rotación de 7 días

**Lo que falta verificar:**

1. Confirmar que los backups se están guardando en R2 (no solo en disco local de Hostinger):

```env
# En .env de producción
BACKUP_DISK=r2
```

2. Configurar notificación si el backup falla:

```php
// En config/backup.php
'notifications' => [
    'mail' => [
        'to' => 'tu-email@dominio.com',
    ],
],
'notifiable' => \Spatie\Backup\Notifications\Notifiable::class,
```

3. Agregar al scheduler la verificación de backups:

```php
// En routes/console.php
Schedule::command('backup:monitor')->daily()->at('04:00');
```

`backup:monitor` alerta si el backup más reciente es muy viejo o muy pequeño (señal de backup corrupto o incompleto).

### 7.2 Estrategia de backup ante escenarios de falla

| Escenario | Tiempo de recuperación estimado | Acción |
|---|---|---|
| Migración fallida | < 30 minutos | Restaurar backup del día anterior desde R2 |
| Error humano (delete masivo) | < 1 hora | Restaurar backup + replay de eventos del día |
| Falla de Hostinger | Depende de Hostinger | Restaurar en nuevo hosting desde backup en R2 |
| Corrupción de base de datos | < 2 horas | Restaurar último backup válido |

**Procedimiento de restauración (documentar y guardar):**

```bash
# Descargar backup desde R2
aws s3 cp s3://bucket-r2/futgo/backup-YYYY-MM-DD.zip . --endpoint-url=https://...r2.cloudflarestorage.com

# Descomprimir
unzip backup-YYYY-MM-DD.zip

# Restaurar MySQL
mysql -u root -p futgo < backup.sql
```

### 7.3 Uptime monitoring

Sin monitoreo externo, si el sitio cae a las 2am nadie se entera hasta que un usuario reporta. Servicios gratuitos:

**UptimeRobot (recomendado, gratuito hasta 50 monitores):**
- Monitor HTTP cada 5 minutos
- Alerta por email si el sitio no responde
- Historial de uptime para el SLA

Configurar monitors para:
- `https://futgo.online` — home
- `https://futgo.online/torneos` — módulo principal
- `https://futgo.online/social` — módulo social

### 7.4 Monitoreo de errores en producción

Sin Sentry o similar, los errores en producción solo son visibles en los logs de Laravel. Con muchos usuarios, un error que afecta al 0.1% de requests puede pasar desapercibido semanas.

**Sentry (tier gratuito hasta 5.000 errores/mes):**

```bash
composer require sentry/sentry-laravel
php artisan sentry:publish --dsn=https://...@sentry.io/...
```

En `config/logging.php`:

```php
'channels' => [
    'stack' => [
        'driver' => 'stack',
        'channels' => ['daily', 'sentry'],
    ],
    'sentry' => [
        'driver' => 'sentry',
        'level' => 'error',
    ],
],
```

Sentry captura cada excepción no manejada con stack trace, contexto del usuario, URL y parámetros del request — sin necesidad de revisar logs manualmente.

---

## 8. Seguridad específica para la app mobile

### 8.1 Certificate Pinning (avanzado)

Certificate pinning hace que la app rechace cualquier certificado SSL que no sea exactamente el de `futgo.online`, incluso si el dispositivo tiene un certificado raíz comprometido. Previene ataques man-in-the-middle sofisticados.

En Capacitor para Android, se configura en `android/app/src/main/res/xml/network_security_config.xml`:

```xml
<?xml version="1.0" encoding="utf-8"?>
<network-security-config>
    <domain-config>
        <domain includeSubdomains="true">futgo.online</domain>
        <pin-set expiration="2027-01-01">
            <pin digest="SHA-256">TU_PIN_BASE64_AQUI</pin>
            <pin digest="SHA-256">PIN_BACKUP_AQUI</pin>  <!-- siempre tener un backup -->
        </pin-set>
    </domain-config>
</network-security-config>
```

Para obtener el PIN del certificado actual:
```bash
openssl s_client -connect futgo.online:443 | openssl x509 -pubkey -noout | openssl pkey -pubin -outform der | openssl dgst -sha256 -binary | base64
```

**Advertencia:** si el certificado SSL de Hostinger rota (renovación automática) y el PIN no se actualiza con una nueva versión de la app, todos los usuarios quedan sin conexión hasta que actualicen. Usar siempre dos PINs (actual + backup del próximo certificado) y planificar la rotación.

### 8.2 Desactivar debug antes de publicar

En `capacitor.config.json`, antes de generar el APK de producción:

```json
"android": {
    "appendUserAgent": "FutGO-Android",
    "webContentsDebuggingEnabled": false
}
```

Con `webContentsDebuggingEnabled: true`, cualquier persona con Chrome en el mismo dispositivo puede inspeccionar el WebView de la app, ver el HTML, el JavaScript, las cookies de sesión y hacer requests arbitrarios como el usuario autenticado.

### 8.3 Detección de dispositivo mobile en Laravel

Para analytics y posible comportamiento diferenciado (layouts, features), detectar el User-Agent de Capacitor:

```php
// En un middleware o helper
public function isMobileApp(Request $request): bool
{
    return str_contains($request->header('User-Agent', ''), 'FutGO-Android')
        || str_contains($request->header('User-Agent', ''), 'FutGO-iOS');
}
```

El `appendUserAgent: "FutGO-Android"` configurado en Capacitor agrega este string al User-Agent de todos los requests de la app.

### 8.4 Deep links y validación de orígenes

Cuando se implementen notificaciones push con links directos a partidos o oportunidades, los deep links deben validar que el recurso solicitado pertenece al usuario autenticado antes de mostrarlo. No asumir que si alguien tiene el link tiene permiso — verificar siempre en el controlador.

---

## 9. Cumplimiento legal

### 9.1 Política de privacidad — obligatoria para Play Store y App Store

**Debe estar publicada en:** `https://futgo.online/privacidad`

**Contenido mínimo requerido:**

```
Qué datos recopilamos:
- Nombre y email (registro)
- Foto de perfil (opcional, subida por el usuario)
- Número de documento (opcional, para verificación de identidad deportiva)
- Estadísticas deportivas (goles, asistencias, partidos)
- Ciudad de residencia/actividad deportiva
- Historial de torneos y amistosos

Cómo usamos los datos:
- Gestión de torneos y estadísticas deportivas
- Identificación del jugador mediante credencial QR
- Matching con otros equipos y jugadores (FutGO Social)
- Envío de recordatorios de partidos por email

Qué datos compartimos:
- Nombre, foto y estadísticas son públicos en el perfil del jugador
- El número de documento nunca se muestra públicamente ni se comparte con terceros
- No vendemos datos a terceros

Cómo eliminás tu cuenta:
- Desde la configuración de perfil, podés solicitar la eliminación de tu cuenta
- Los datos se eliminan en un plazo de 30 días
- Las estadísticas históricas en torneos pueden conservarse de forma anonimizada

Cookies:
- Usamos cookies de sesión estrictamente necesarias para el funcionamiento
- No usamos cookies de tracking ni publicidad

Contacto:
- Email: privacidad@futgo.online (o el que tengas)
```

### 9.2 Declaración de seguridad de datos (Play Store)

En Google Play Console, en la sección "Seguridad de los datos", completar:

| Pregunta | Respuesta FutGO |
|---|---|
| ¿Recopila datos de usuario? | Sí |
| Tipos de datos recopilados | Nombre, email, foto, actividad en la app |
| ¿Se comparten con terceros? | No (excepto infraestructura: Hostinger, Cloudflare) |
| ¿Se cifran los datos en tránsito? | Sí (HTTPS) |
| ¿El usuario puede solicitar eliminación? | Sí |

### 9.3 Eliminación de cuenta — requerido por Apple y Google

Desde 2023, ambas tiendas exigen que las apps con cuentas de usuario tengan un mecanismo para que el usuario elimine su cuenta desde dentro de la app.

**Implementar en FutGO:**

```php
// Ruta en routes/web.php
Route::delete('/perfil/cuenta', [ProfileController::class, 'deleteAccount'])
    ->middleware(['auth', 'throttle:3,60'])
    ->name('profile.delete');
```

```php
// En ProfileController
public function deleteAccount(Request $request)
{
    $request->validate([
        'password' => ['required', 'current_password'],
    ]);

    $user = $request->user();
    
    // Anonimizar datos en vez de borrar en cascada
    // (preserva la integridad de stats de torneos históricos)
    DB::transaction(function() use ($user) {
        $user->update([
            'name' => 'Usuario eliminado',
            'email' => 'deleted_' . $user->id . '@futgo.invalid',
            'avatar_url' => null,
            'document' => null,
            'is_active' => false,
        ]);
        
        // Revocar todas las sesiones
        $user->sessions()->delete();
        
        // Marcar para eliminación definitiva en 30 días
        $user->update(['delete_at' => now()->addDays(30)]);
    });

    Auth::logout();
    return redirect('/')->with('status', 'Tu cuenta fue eliminada.');
}
```

La anonimización en vez del borrado inmediato preserva la integridad de los datos históricos (standings, stats de torneos) donde el usuario participó.

---

## 10. Checklist priorizado de implementación

### 🔴 Antes de publicar en stores (bloqueante)

- [ ] Rate limiting en login, registro y recuperación de contraseña (`throttle:10,1`)
- [ ] Rate limiting en validación QR (`throttle:60,1`)
- [ ] Sesiones en base de datos (`SESSION_DRIVER=database`, `lifetime=10080`)
- [ ] Headers de seguridad HTTP (middleware `SecurityHeaders`)
- [ ] `session.encrypt = true` en `config/session.php`
- [ ] `webContentsDebuggingEnabled: false` en `capacitor.config.json` para el build de producción
- [ ] Página de política de privacidad en `https://futgo.online/privacidad`
- [ ] Función de eliminación de cuenta desde el perfil
- [ ] Verificar que `{!! !!}` en vistas no expone input de usuario sin sanitizar
- [ ] Verificar que ningún controlador usa `$request->all()` sobre el modelo `User`

### 🟠 Primera semana con usuarios reales

- [ ] Encriptación del campo `document` en `User` y `ClubPlayer` (`AsEncryptedString`)
- [ ] Caché de respuestas en portal público de torneo y ranking (`Cache::remember`)
- [ ] Invalidación de caché al guardar resultados y recalcular standings
- [ ] Queue workers corriendo en Hostinger (cron cada 5 minutos)
- [ ] `ShouldQueue` en `MatchReminderNotification` y otros Notifiables de email
- [ ] Cloudflare como proxy/CDN delante de `futgo.online`
- [ ] UptimeRobot configurado para alertas de caída
- [ ] Índices de MySQL para columnas de filtros frecuentes (city, status, created_at)
- [ ] `CACHE_STORE=database` (o Redis si disponible)
- [ ] Corrección del N+1 en `StatsController` (eager loading de `lineups`)

### 🟡 Primer mes

- [ ] Sentry para monitoreo de errores en producción
- [ ] `backup:monitor` en el scheduler para alertas de backup fallido
- [ ] PHPStan/Larastan en el pipeline de CI para análisis estático
- [ ] Revisar logs para detectar datos sensibles loggeados accidentalmente
- [ ] Content Security Policy completa (después de auditar todos los scripts inline)
- [ ] Bloqueo progresivo por cuenta en login (`RateLimiter` por email+IP)
- [ ] Verificar que backups se guardan en R2 y no solo en disco local

### 🔵 Con volumen real (> 1.000 usuarios activos)

- [ ] Redis para sesiones y caché (reemplaza database driver)
- [ ] Sanctum con tokens por dispositivo (si la experiencia de sesión da problemas en mobile)
- [ ] Certificate pinning en la app Android e iOS
- [ ] Separar servidor de base de datos del servidor web (si Hostinger lo permite)
- [ ] Considerar migración a VPS (DigitalOcean, Hetzner) con Forge o Laravel Cloud

---

## 11. Prompts de implementación para Claude Code

Los siguientes prompts están listos para ejecutar en Claude Code en el orden del checklist.

### Prompt A — Rate limiting + headers de seguridad + sesiones (🔴 Antes de publicar)

```
Aplicá los siguientes cambios de seguridad en FutGO (Laravel 11). No toques los 620 tests existentes. Leé cada archivo antes de modificarlo.

CONTEXTO:
- Stack: PHP 8.3, Laravel 11, MySQL 8
- Hosting: Hostinger, dominio futgo.online
- App mobile: Capacitor WebView apuntando a https://futgo.online
- CORS ya configurado en config/cors.php con capacitor://localhost y http://localhost

CAMBIO 1 — Rate limiting en rutas de autenticación
En routes/web.php y/o routes/auth.php, envolvé las rutas de login, registro,
forgot-password y reset-password con throttle:10,1.
Para /torneos/validar (validación QR): throttle:60,1.
Para /buscar y /canchas/search (búsqueda): throttle:100,1.
No uses throttle en rutas que ya lo tengan.

CAMBIO 2 — Middleware de headers de seguridad
Creá app/Http/Middleware/SecurityHeaders.php con estos headers:
- Strict-Transport-Security: max-age=31536000; includeSubDomains; preload
- X-Content-Type-Options: nosniff
- X-Frame-Options: SAMEORIGIN
- Referrer-Policy: strict-origin-when-cross-origin
- Permissions-Policy: camera=(self), microphone=(), geolocation=(self), payment=()
Registralo como middleware global en bootstrap/app.php (append, no prepend).

CAMBIO 3 — Sesiones en base de datos
a) Ejecutá: php artisan session:table && php artisan migrate
b) En config/session.php:
   - 'driver' => env('SESSION_DRIVER', 'database')
   - 'lifetime' => env('SESSION_LIFETIME', 10080)
   - 'expire_on_close' => false
   - 'encrypt' => true
   - 'http_only' => true (verificar que ya esté, si no agregar)
No cambies same_site ni secure (ya están condicionados por APP_ENV).

CAMBIO 4 — Bloqueo progresivo en login por cuenta+IP
En el controlador de login (donde se procesa el POST de autenticación), agregá
RateLimiter por combinación email+IP: máximo 5 intentos, bloqueo de 300 segundos.
Limpiar el contador cuando el login sea exitoso.

VERIFICACIÓN FINAL:
1. php artisan config:clear && php artisan route:clear && php artisan view:clear
2. php artisan test
Los 620 tests deben seguir pasando. Reportá cualquier fallo antes de intentar corregirlo.
Reportá al final cada archivo modificado con una línea de qué cambió.
```

### Prompt B — Encriptación de datos sensibles + caché + N+1 (🟠 Primera semana)

```
Aplicá los siguientes cambios de rendimiento y protección de datos en FutGO (Laravel 11).
No toques los tests existentes.

CONTEXTO:
- Los campos `document` en User y ClubPlayer contienen cédulas colombianas en texto plano
- El N+1 en StatsController está documentado en el informe de producción
- El portal público /t/{slug} es la página más visitada externamente
- CACHE_STORE puede ser 'database' si Redis no está disponible

CAMBIO 1 — Encriptación del campo document
a) Hacer backup de la BD antes de este cambio (documentarlo en el output).
b) En app/Models/User.php: agregar AsEncryptedString cast para el campo 'document'.
c) En app/Models/Torneos/ClubPlayer.php: mismo cast para 'document'.
d) Crear comando artisan que encripte los valores existentes en la BD.
e) Verificar que los flujos que usan 'document' (ProfileClaimService, detección de
   duplicados en ClubMembershipService) sigan funcionando tras la encriptación.

CAMBIO 2 — Corrección del N+1 en StatsController
En app/Http/Controllers/Torneos/StatsController.php, en el método que muestra
el historial partido a partido de un jugador: reemplazar la query dentro del loop
por eager loading con ->load() o ->with() antes del loop.
Verificar con un conteo de queries que el resultado sea una sola query para lineups.

CAMBIO 3 — Caché en portal público
En app/Http/Controllers/Torneos/PublicTournamentController.php:
- Cachear la respuesta de TournamentReportService con Cache::remember().
- Clave: "tournament.public.{slug}".
- TTL: 300 segundos.
En app/Http/Controllers/Admin/Torneos/MatchResultController.php:
- Al guardar o anular un resultado (store y destroy), limpiar el caché con
  Cache::forget("tournament.public.{slug}").

CAMBIO 4 — Queue worker para notificaciones
En app/Notifications/Torneos/MatchReminderNotification.php:
- Agregar implements ShouldQueue y use Queueable.
Verificar que QUEUE_CONNECTION=database esté en .env y que la tabla jobs exista.
Si no existe, crear con: php artisan queue:table && php artisan migrate.

VERIFICACIÓN:
php artisan test — los 620 tests deben pasar.
Reportá cada archivo modificado.
```

### Prompt C — Eliminación de cuenta (requerido por stores)

```
Implementá la función de eliminación de cuenta en FutGO (Laravel 11).
Es un requisito obligatorio de Google Play Store y Apple App Store desde 2023.

CONTEXTO:
- Los usuarios tienen historial en torneos (standings, match_events, player_stats)
- Borrar en cascada rompería la integridad histórica de los torneos
- La solución es anonimizar, no borrar
- users tabla tiene: name, email, avatar_url, document, is_active

IMPLEMENTACIÓN:

1. Migración: agregar columna `delete_requested_at` (nullable timestamp) a users.
2. Ruta DELETE /perfil/cuenta con middleware auth y throttle:3,60.
3. ProfileController::deleteAccount:
   - Validar password actual del usuario
   - En DB::transaction:
     a) Actualizar user: name='Usuario eliminado', email='deleted_{id}@futgo.invalid',
        avatar_url=null, document=null, is_active=false, delete_requested_at=now()
     b) Eliminar avatar físico del storage si existe
     c) Invalidar todas las sesiones del usuario
   - Logout
   - Redirect a '/' con mensaje de confirmación

4. Comando artisan futgo:purge-deleted-accounts:
   - Busca users donde delete_requested_at < now()-30días
   - Los elimina definitivamente de la BD
   - Loggea cuántos se eliminaron

5. Agregar el comando al scheduler en routes/console.php: ->daily()->at('04:30')

6. Vista en la sección de perfil/configuración: formulario de confirmación con
   input de password, advertencia clara de que la acción es irreversible,
   y listado de qué datos se eliminan vs qué datos quedan anonimizados.

7. Agregar enlace "Eliminar mi cuenta" en el menú de perfil (nav.blade.php,
   en el dropdown del avatar donde está "Salir").

