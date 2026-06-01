# Guía de Gestión de Contenido — FutGO
**Para técnicos de soporte · Cambios de texto, títulos y opciones**

---

## Antes de empezar

### Herramienta recomendada
Usá **Visual Studio Code** (gratuito) o **Notepad++**.  
Siempre guardá los archivos con codificación **UTF-8**.

### Dónde está el proyecto
```
C:\laragon\www\FutGO\
```

### Cómo aplicar los cambios (3 pasos)
1. Editá el archivo (instrucciones abajo).
2. Guardá el archivo (`Ctrl + S`).
3. Abrí la consola en la carpeta del proyecto y ejecutá:

```bash
php artisan view:clear
```

> ✅ **Listo.** Al recargar la página en el navegador el cambio ya está visible.  
> No es necesario tocar CSS, JavaScript ni reiniciar el servidor.

---

## Mapa rápido: ¿qué archivo controla qué?

| Sección / Pantalla | Archivo |
|---|---|
| Nombre de la app (título del navegador) | `.env` → variable `APP_NAME` |
| **Landing** — Título principal, subtítulo, botones | `resources/views/components/landing/hero.blade.php` |
| **Landing** — Banda de números (torneos, jugadores…) | `resources/views/components/landing/hero.blade.php` |
| **Landing** — Franja "Confían en FutGO" (logos) | `resources/views/components/landing/social-proof.blade.php` |
| **Landing** — 6 tarjetas de funcionalidades | `resources/views/components/landing/features.blade.php` |
| **Landing** — Sección Ecosistema (texto + nodos) | `resources/views/components/landing/ecosystem.blade.php` |
| **Landing** — 3 testimonios | `resources/views/components/landing/testimonials.blade.php` |
| **Landing** — CTA final (título + botones) | `resources/views/components/landing/cta.blade.php` |
| Menú de navegación (landing) | `resources/views/layouts/landing.blade.php` |
| Menú de navegación (app autenticada) | `resources/views/components/nav.blade.php` |
| Pie de página | `resources/views/layouts/landing.blade.php` |
| Página "¿Cómo funciona?" | `resources/views/como-funciona.blade.php` |

---

## Cambios frecuentes — instrucciones paso a paso

---

### 1. Cambiar el nombre de la aplicación

**Archivo:** `.env` (raíz del proyecto)

Buscá la línea:
```
APP_NAME=FutGO
```
Cambiá `FutGO` por el nombre nuevo. Si tiene espacios, rodealo con comillas:
```
APP_NAME="FutGO Pro"
```

Después ejecutá en consola:
```bash
php artisan config:clear
php artisan view:clear
```

---

### 2. Cambiar el título principal de la landing (H1)

**Archivo:** `resources/views/components/landing/hero.blade.php`

Buscá este bloque (alrededor de la línea 22):
```html
<h1 ...>
    El fútbol amateur<br>
    tiene un nuevo <em ...>hogar.</em>
</h1>
```

- Para cambiar el texto **antes** del salto de línea: editá `El fútbol amateur`
- Para cambiar la **palabra en verde**: editá `hogar.` (dentro de `<em>`)
- Para poner el texto en **una sola línea**: eliminá el `<br>`

**Ejemplo:**
```html
<h1 ...>
    Gestioná tu liga<br>
    como un <em ...>campeón.</em>
</h1>
```

---

### 3. Cambiar el subtítulo de la landing

**Archivo:** `resources/views/components/landing/hero.blade.php`

Buscá el bloque `{{-- Subtítulo --}}` (alrededor de la línea 27):
```html
<p class="text-[20px] text-muted ...">
    Una sola plataforma para gestionar torneos, equipos, estadísticas
    y canchas. FutGO se está convirtiendo en el sistema operativo
    del fútbol base.
</p>
```
Reemplazá el texto entre `<p ...>` y `</p>`.  
Podés poner el texto en varias líneas — se une automáticamente.

---

### 4. Cambiar los botones de la landing (textos y destinos)

**Archivo:** `resources/views/components/landing/hero.blade.php`

Buscá el bloque `{{-- CTAs --}}`:
```html
<a href="{{ route('register') }}"     class="btn btn-primary btn-lg">Empezar gratis</a>
<a href="{{ route('how-it-works') }}" class="btn btn-outline btn-lg">Hablar con ventas</a>
```

- Para cambiar el **texto del botón**: editá lo que está entre `>` y `</a>`.
- Para cambiar a dónde **lleva el botón**: cambiá el nombre de ruta dentro de `route('...')`.

Rutas disponibles más comunes:
| Nombre de ruta | Página |
|---|---|
| `register` | Crear cuenta |
| `login` | Iniciar sesión |
| `how-it-works` | ¿Cómo funciona? |
| `inicio` | Panel de inicio (autenticado) |

---

### 5. Cambiar los números de la banda de métricas

**Archivo:** `resources/views/components/landing/hero.blade.php`

Buscá el bloque `{{-- Banda de métricas --}}` (alrededor de la línea 49):
```php
@foreach([
    ['2,400+', 'torneos'],
    ['38K',    'jugadores'],
    ['540',    'canchas conectadas'],
    ['11',     'países'],
] as [$n, $l])
```

Cada par `['número', 'etiqueta']` es una celda.  
- Primer elemento: el **número grande** (texto libre, puede incluir `+`, `K`, `%`).
- Segundo elemento: la **etiqueta** debajo del número.

**Ejemplo — cambiar el primer dato:**
```php
['3,100+', 'torneos activos'],
```

---

### 6. Cambiar los logos de "Confían en FutGO"

**Archivo:** `resources/views/components/landing/social-proof.blade.php`

Buscá:
```php
@foreach(['Liga Pachón', 'UrbanFutbol', 'CanchaPro', 'Deportiva MX', 'Gol&amp;Gol'] as $logo)
```

Editá, agregá o eliminá los nombres dentro de los corchetes `[...]`.  
Cada nombre va entre comillas simples y separado por coma:
```php
@foreach(['Liga Norte', 'Copa Sur', 'CanchaPro', 'Deportiva MX'] as $logo)
```

> ⚠️ El símbolo `&` se escribe como `&amp;` dentro del código para que se muestre correctamente.

---

### 7. Cambiar las 6 tarjetas de funcionalidades

**Archivo:** `resources/views/components/landing/features.blade.php`

Al inicio del archivo hay un bloque `@php` con el array de features:
```php
$features = [
    [
        'title' => 'Torneos automáticos',
        'desc'  => 'Crea ligas, copas o grupos+eliminatoria...',
        'icon'  => '...',   // ← NO modificar
    ],
    ...
];
```

Para cada tarjeta:
- `'title'` → **título** de la tarjeta (texto en negrita grande)
- `'desc'`  → **descripción** (párrafo debajo del título)
- `'icon'`  → código SVG del ícono — **no modificar**

**Ejemplo — cambiar la primera tarjeta:**
```php
[
    'title' => 'Ligas y copas',
    'desc'  => 'Armá ligas de temporada o copas de eliminación directa con fixture automático.',
    'icon'  => '<path d="M8 21V11M16 21V7M12 21V3"/>',  // ← sin tocar
],
```

---

### 8. Cambiar el texto de la sección Ecosistema

**Archivo:** `resources/views/components/landing/ecosystem.blade.php`

Hay tres partes editables:

**a) Título:**
```html
<h2 ...>
    Un ecosistema,<br>no una app suelta
</h2>
```

**b) Párrafo explicativo:**
```html
<p class="text-[17px] text-muted ...">
    FutGO conecta a todos los actores...
</p>
```

**c) Etiquetas de los badges (Organizadores, Jugadores, Patrocinadores):**
```html
<span class="badge badge-green">Organizadores</span>
<span class="text-[14px] text-muted">gestionan torneos y canchas</span>
```
Cada par badge + descripción puede editarse libremente.

**d) Etiquetas de los nodos del diagrama** (Hub, App, Red, B2B):
```html
<span class="block font-mono ... text-green mb-0.5">Hub</span>
Organizador
```
Podés cambiar tanto la etiqueta en verde (`Hub`) como el nombre debajo (`Organizador`).

---

### 9. Cambiar los testimonios

**Archivo:** `resources/views/components/landing/testimonials.blade.php`

Al inicio del archivo:
```php
$testimonials = [
    [
        'quote'    => '"Pasé de tres grupos de WhatsApp..."',
        'name'     => 'Javier Ramos',
        'role'     => 'Liga Pachón · CDMX',
        'initials' => 'JR',   // ← 2 letras para el avatar
        'green'    => true,   // ← true = avatar verde, false = avatar normal
    ],
    ...
];
```

Para cada testimonio:
- `'quote'`    → texto entre comillas del testimonio (incluí las comillas `"..."`)
- `'name'`     → nombre de la persona
- `'role'`     → cargo o liga que representa
- `'initials'` → **exactamente 2 letras** para el avatar circular
- `'green'`    → `true` para el primer testimonio destacado, `false` para los demás

---

### 10. Cambiar el CTA final (última sección)

**Archivo:** `resources/views/components/landing/cta.blade.php`

```html
{{-- Eyebrow --}}
Donde crece el fútbol amateur

{{-- Título --}}
Tu próxima temporada<br>empieza en FutGO.

{{-- Párrafo --}}
Creá tu primer torneo gratis. Sin tarjeta, sin instalar nada.
Listo para tu próxima jornada.

{{-- Botones --}}
<a ...>Empezar gratis</a>
<a ...>Ver una demo</a>
```
Editá los textos directamente entre las etiquetas HTML.

---

### 11. Cambiar los títulos del menú de navegación (landing)

**Archivo:** `resources/views/layouts/landing.blade.php`

Buscá el bloque de `<nav class="hidden md:flex ...">`:
```html
<a href="#features">Producto</a>
<a href="#eco">Ecosistema</a>
<a href="#testimonios">Testimonios</a>
```

- Para cambiar el **texto visible** del enlace: editá entre `>` y `</a>`.
- Para cambiar el **ancla** (sección de destino): editá el `#nombre` del `href`.  
  > Si cambiás el ancla acá también tenés que cambiar el `id="..."` de la sección correspondiente.

---

### 12. Cambiar el eslogan del pie de página

**Archivo:** `resources/views/layouts/landing.blade.php`

Buscá:
```html
<span class="font-mono text-[12px] text-subtle">© {{ date('Y') }} FutGO · Donde crece el fútbol amateur</span>
```
Editá el texto después de `FutGO ·`.  
El `{{ date('Y') }}` es el año automático — no lo modifiques.

---

### 13. Cambiar el título de una página (pestaña del navegador)

En cada vista `.blade.php` buscá la línea:
```php
@section('title', 'Texto que aparece en la pestaña')
```
Cambiá el texto entre comillas.

---

## Reglas de oro — qué NO tocar

| ❌ No modificar | Motivo |
|---|---|
| `class="..."` en etiquetas HTML | Controla el diseño visual; un error rompe el aspecto |
| `{{ route('...') }}` | Ruta del sistema; si escribís mal, el botón dejará de funcionar |
| `@php`, `@foreach`, `@if`, `@endif`, `@else`, `@guest` | Lógica PHP; un error tira una página en blanco |
| `'icon' => '...'` en features.blade.php | Código SVG del ícono; complicado de recuperar |
| Cualquier archivo en `app/`, `config/`, `database/`, `routes/` | Lógica de la aplicación; solo devs |
| Archivos `.css` y `.js` | Estilos y scripts compilados; requieren rebuild |
| `tailwind.config.js` | Configuración de estilos |
| `.env` (excepto `APP_NAME`) | Variables de entorno críticas |

---

## Glosario rápido

| Término | Qué es |
|---|---|
| `.blade.php` | Archivo de vista (plantilla HTML del servidor) |
| `{{ variable }}` | Valor dinámico inyectado por PHP — no editar salvo que la guía lo indique |
| `@section(...)` | Bloque de contenido de la página |
| `@foreach(...)` | Ciclo que repite un bloque (p.ej., las 6 features) |
| `@guest / @else / @endguest` | Muestra contenido distinto a visitantes y usuarios con sesión |
| `route('nombre')` | Enlace interno del sistema — no cambiar el nombre entre comillas |
| `<br>` | Salto de línea dentro de un título |
| `&amp;` | El símbolo `&` codificado para HTML |

---

## Comandos de consola de referencia

```bash
# Limpiar caché de vistas (después de editar .blade.php)
php artisan view:clear

# Limpiar caché de configuración (después de editar .env)
php artisan config:clear

# Limpiar todo (si algo no se actualiza)
php artisan optimize:clear
```

Abrí la consola directamente en la carpeta del proyecto:
`C:\laragon\www\FutGO\` → clic derecho → **Abrir en terminal**.

---

*Última actualización: {{ fecha }}*  
*Proyecto: FutGO v2 · Stack: Laravel 11 + Tailwind + Alpine.js*
