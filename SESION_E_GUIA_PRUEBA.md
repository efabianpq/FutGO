# Sesión E — Guía de prueba en el navegador

**Portal público del torneo**, **contenido compartible (SVG)** y **exportación PDF/CSV** de resultados, posiciones y estadísticas.

App local: **http://futgo.test:8080** (alternativa: `php artisan serve --port=8001`).

> PATH de Laragon antes de `php artisan`:
> ```powershell
> $env:Path = "C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64;C:\laragon\bin\composer;C:\laragon\bin\mysql\mysql-8.0.30-winx64\bin;$env:Path"
> ```

---

## 0. Datos previos

La demo ya trae un **torneo público** con partidos jugados (`php artisan migrate --seed` o `php artisan db:seed --class=DemoTournamentSeeder --force`):
- **Copa FutGO Demo 2026** — `visibility = public`, slug **`copa-futgo-demo-2026`**, 8 equipos, fixture, 6 partidos finalizados, goleadores y MVP.
- Admin torneo: `admin.torneo@demo.futgo.com` / `Demo2026!`

Para tener además un **torneo privado** que probar en el Escenario 2:

```php
// php artisan tinker
use App\Models\Torneos\Tournament;
$t = Tournament::where('slug','copa-futgo-demo-2026')->first();
// Clonamos rápido uno privado a partir del público:
$priv = $t->replicate(['slug']);
$priv->name = 'Liga Privada Demo';
$priv->slug = 'liga-privada-demo';
$priv->visibility = 'private';
$priv->save();
echo $priv->slug;   // liga-privada-demo
```

---

## Escenario 1 — Portal público en ventana de incógnito (sin login)

1. Abrí una **ventana de incógnito** (sin sesión).
2. Entrá a **http://futgo.test:8080/t/copa-futgo-demo-2026**
3. Sin pedir login, se ve: cabecera del torneo (estado, categoría, ciudad), **tabla de posiciones por grupo**, **resultados**, **próximos partidos con fecha y cancha**, y **goleadores**.
4. Es responsive: probá en vista móvil (DevTools → Toggle device toolbar). Las tablas hacen scroll horizontal.

✅ El link es apto para pegar en WhatsApp: tiene **Open Graph tags** (título, descripción y, si el torneo tiene banner/logo, imagen de vista previa).

---

## Escenario 2 — Torneo privado NO accesible públicamente

1. En incógnito, entrá a **http://futgo.test:8080/t/liga-privada-demo**
2. Debe responder **404** (los torneos privados no existen para el público; ni siquiera se revela su existencia).

✅ El portal nunca expone email, teléfono ni documento de los jugadores (solo nombres y estadísticas deportivas).

---

## Escenario 3 — Descargar PDF y CSV del torneo

**Desde el portal público** (Escenario 1):
1. En la cabecera, botón **⬇ Exportar** → elegí **Resultados / Posiciones / Estadísticas** en **PDF** o **CSV**.
2. El PDF abre con branding FutGO; el CSV abre en Excel con acentos correctos (BOM UTF-8).

**Desde el panel admin** (login `admin.torneo@demo.futgo.com`):
1. Gestión Torneos → **Copa FutGO Demo 2026** → tarjeta **“Exportar y compartir”**.
2. Mismos tres datasets en PDF/CSV + link directo al **portal público**.

✅ La exportación admin valida que seas administrador del torneo; la pública solo funciona si el torneo es `public`.

---

## Escenario 4 — Generar un gráfico compartible de goleadores

1. En el portal (incógnito), botón **🖼️ Imágenes → Goleadores** (o el link **Compartir 🖼️** del bloque Goleadores).
2. Se abre la tarjeta **SVG 1080×1080** con branding FutGO y el top de goleadores.
3. Otras tarjetas: **Posiciones**, **MVP de la fecha** (si el torneo tiene MVP habilitado) y **resultado de un partido** (clic en cualquier resultado de la lista).
4. Para **descargar** la imagen, agregá `?descargar=1` a la URL del gráfico (se baja como `.svg`).
5. El botón **📲 Compartir** usa el share nativo del celular (Web Share API) y, en escritorio, abre **wa.me** con el link del torneo.

---

## Queries SQL de verificación

```sql
-- Torneos públicos vs privados
SELECT slug, name, visibility, status FROM tournaments ORDER BY id DESC;

-- Datos que alimentan el portal (deben coincidir con lo mostrado)
SELECT g.name grupo, s.position, t.name equipo, s.played, s.goal_difference, s.points
FROM standings s
JOIN tournament_groups g ON g.id = s.group_id
JOIN teams t ON t.id = s.team_id
JOIN tournament_phases p ON p.id = s.phase_id
JOIN tournaments tr ON tr.id = p.tournament_id
WHERE tr.slug = 'copa-futgo-demo-2026'
ORDER BY g.name, s.position;

-- Goleadores del portal
SELECT u.name, ps.goals, ps.assists
FROM player_stats ps
JOIN team_players tp ON tp.id = ps.team_player_id
LEFT JOIN users u ON u.id = tp.user_id
JOIN tournaments tr ON tr.id = ps.tournament_id
WHERE tr.slug = 'copa-futgo-demo-2026' AND ps.goals > 0
ORDER BY ps.goals DESC;
```

---

## Errores conocidos / limitaciones

1. **Técnica de imágenes: SVG renderizado desde Blade** (PHP puro). Elegida porque **no requiere GD ni imagick** (imagick no está disponible y Hostinger no lo garantiza) y produce gráficos nítidos a cualquier escala. La descarga es un archivo `.svg`.
   - *Limitación*: algunos clientes (la app de WhatsApp al adjuntar archivo) prefieren PNG/JPG. Para la **vista previa del link compartido** se usan **Open Graph tags** en el portal (con `og:image` = banner/logo del torneo si existe), que sí renderiza WhatsApp/Facebook. Convertir el SVG a PNG (vía GD donde esté disponible) queda como mejora futura.
2. **Privacidad**: el `TournamentReportService` carga del usuario **solo `id` y `name`**; ningún endpoint público incluye email/teléfono/documento (verificado por test).
3. **Rendimiento**: el portal usa eager loading; el número de consultas es **constante** sin importar la cantidad de equipos/partidos (test de conteo de queries < 25).
4. El portal muestra hasta 12 resultados y 12 próximos partidos, y top 10 goleadores (los gráficos, top 8) para mantener la página liviana.
5. Las tarjetas SVG embeben el branding por color; el logo/banner del torneo se usa en el portal y en `og:image`, no dentro del SVG (evita depender de descargar imágenes remotas al renderizar).
