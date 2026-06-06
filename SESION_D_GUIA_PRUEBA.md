# Sesión D — Guía de prueba en el navegador

**Credencial QR antifraude**: identificador FUTGO único por jugador, credencial digital con QR y validación por árbitros (escaneo o ingreso manual) con auditoría.

App local: **http://futgo.test:8080** (alternativa: `php artisan serve --port=8001`).

> PATH de Laragon antes de `php artisan`:
> ```powershell
> $env:Path = "C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64;C:\laragon\bin\composer;C:\laragon\bin\mysql\mysql-8.0.30-winx64\bin;$env:Path"
> ```

---

## 0. Datos previos

La demo ya trae todo lo necesario (`php artisan migrate --seed` o `php artisan db:seed --class=DemoTournamentSeeder --force`):
- **Copa FutGO Demo 2026** en curso, 8 equipos, fixture y partidos.
- Admin torneo (árbitro): `admin.torneo@demo.futgo.com` / `Demo2026!`
- Capitán Leones (jugador): `ldn.capitan@demo.futgo.com` / `Demo2026!`
- Jugador Leones: `ldn.j1@demo.futgo.com` / `Demo2026!`

La migración `000028` ya pobló un **identificador FUTGO** (`FG-XXXXXX`) en TODOS los usuarios existentes. Para ver el de un jugador concreto:

```php
// php artisan tinker
use App\Models\User;
$j = User::where('email','ldn.capitan@demo.futgo.com')->first();
echo $j->futgo_id;            // p.ej. FG-7KD3PQ — copialo para el Escenario 4

// Un jugador NO inscrito en la Copa Demo (para el Escenario 3):
$out = User::factory()->create(['is_active'=>true,'modules'=>'torneos','name'=>'Infiltrado Pérez']);
echo $out->futgo_id;
```

---

## Escenario 1 — Ver la credencial digital con su QR

1. Login como **jugador** (`ldn.capitan@demo.futgo.com`).
2. Menú **Mi Credencial** (o **Mi Carrera → 🪪 Mi credencial**) → `/torneos/credencial`.
3. Se ve la tarjeta: **foto** (o iniciales), **nombre**, **identificador FUTGO** y el **QR** (SVG nítido).
4. Debajo, **Mis equipos activos** (Leones · Copa FutGO Demo 2026).
5. Nota de privacidad al pie: el QR **no** contiene documento ni datos personales.

✅ El QR codifica una URL del tipo `…/torneos/validar?fg=FG-XXXXXX&sig=…` — solo el identificador público + una firma.

---

## Escenario 2 — Validar un jugador habilitado (resultado: HABILITADO)

1. Login como **admin/árbitro** (`admin.torneo@demo.futgo.com`).
2. Menú **🛡️ Validar** → `/torneos/validar`.
3. Ingresá el **identificador FUTGO** del capitán de Leones (del paso 0) y elegí **Copa FutGO Demo 2026** en el selector de torneo.
4. **Validar** → banner verde **✓ HABILITADO**, con foto, nombre, identificador y el equipo en ese torneo.

✅ Queda registrada la auditoría (ver Queries SQL abajo).

---

## Escenario 3 — Validar un jugador no inscrito (resultado: NO HABILITADO)

1. Como **árbitro**, en **Validar**, ingresá el identificador del **Infiltrado Pérez** (creado en el paso 0).
2. Elegí **Copa FutGO Demo 2026** → **Validar**.
3. Banner ámbar **✗ NO HABILITADO**: el jugador existe en FUTGO pero **no está inscrito** en ese torneo.

> Variante "no encontrado": ingresá un identificador inventado (p.ej. `FG-ZZZZZZ`) → banner gris **? NO ENCONTRADO**.

---

## Escenario 4 — Validación manual por identificador (sin cámara)

Simula conectividad/cámara deficiente en cancha:

1. Como **árbitro** en **Validar**, **escribí a mano** el identificador (sin escanear nada), con o sin torneo.
2. **Validar** → identifica al jugador igual que por QR. Si elegiste torneo, dice HABILITADO/NO HABILITADO; si no, muestra los equipos activos del jugador.

✅ La validación **no depende de la cámara**: el ingreso manual del identificador funciona siempre. La auditoría lo registra con `method = manual` (el escaneo del QR queda como `method = qr`).

---

## Escenario 5 — Documento de identidad (refuerzo, opcional)

1. Como cualquier usuario → **Perfil** → campo **Documento de identidad** → guardá.
2. El documento se almacena en `users.document` y se muestra el **Identificador FUTGO** en el perfil.
3. ✅ El documento **nunca** viaja en el QR ni en URLs (verificá en el Escenario 1 que el QR solo lleva `fg` + `sig`).

---

## Queries SQL de verificación

```sql
-- Identificadores FUTGO poblados y únicos
SELECT COUNT(*) total, COUNT(DISTINCT futgo_id) unicos FROM users;   -- total == unicos

-- Auditoría de validaciones (quién validó, a quién, resultado, método y cuándo)
SELECT cv.id, cv.futgo_id, cv.result, cv.method,
       v.name AS jugador, a.name AS arbitro, cv.tournament_id, cv.created_at
FROM credential_validations cv
LEFT JOIN users v ON v.id = cv.validated_user_id
LEFT JOIN users a ON a.id = cv.validated_by_user_id
ORDER BY cv.id DESC;
```

---

## Errores conocidos / limitaciones

1. **Solo jugadores registrados** tienen credencial: el `futgo_id` vive en `users`. Los jugadores cargados "por verificar" (sin cuenta, `team_players.user_id` nulo) no tienen credencial hasta reclamar/crear su cuenta (reclamo de perfil sigue pendiente de UI).
2. **Librería QR**: `bacon/bacon-qr-code` v3 renderizando **SVG en PHP puro** — no requiere las extensiones GD ni imagick (clave para Hostinger). El QR se incrusta inline como `<svg>`.
3. **Firma del QR**: HMAC-SHA256 (truncado) derivado del `APP_KEY`. Un QR con firma inválida/ausente igual resuelve al jugador, pero la pantalla del árbitro muestra el aviso **⚠ Firma del QR inválida** para que verifique a mano. El ingreso manual no requiere firma (se valida contra el backend).
4. La validación registra **una fila por validación** (cada escaneo/ingreso), por diseño de auditoría.
5. **Habilitación**: se considera habilitado si el jugador tiene una inscripción `active` en un equipo del torneo elegido y el torneo no está `finished`/`cancelled`. Sin torneo seleccionado, se reporta habilitado si participa activo en algún torneo vigente.
