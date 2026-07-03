# Credenciales del demo — video promocional multi-ciudad

Cuentas fijas para grabar en vivo sin improvisar. **Contraseña única para todas:
`password`**. Se generan con `php artisan migrate:fresh --seed` (local o
`DEMO_DATA=true`) desde `database/seeders/Demo/`.

Guion de grabación completo en [`GUION_DEMO_PRESENTACION.md`](GUION_DEMO_PRESENTACION.md).

---

## 1. Admin de plataforma

| | |
|---|---|
| **Email** | `admin@futgo.co` |
| **Nombre** | Administrador FutGO |
| **Rol** | `admin` (maestro — ve y administra todo) |

**Qué mostrar:** panel `/admin` general, moderación (`/admin/moderacion`),
recalcular ranking manual, y como respaldo si algún flujo de organizador
necesita verse "desde arriba" (todos los torneos de las 6 ciudades a la vez).

---

## 2. Organizadora — Liga Escolar Sabana (torneo prioritario para admins)

| | |
|---|---|
| **Email** | `coordinadora.sabana@futgo.co` |
| **Nombre** | Diana Ramírez |
| **Ciudad** | Chía |
| **Rol** | `user` (creadora y admin de su propio torneo — sin rol especial) |
| **Torneo** | **Liga Escolar Sabana Sub-13 2026** — `liga-escolar-sabana-sub13-2026` |

Torneo: sede Chía, `groups_and_knockout`, **`in_progress`** ("vivo": 4 de 6
partidos de grupo jugados, 2 programados a futuro con convocatorias activas),
`visibility=public`, 2 patrocinadores locales. 6 colegios/escuelas de la Sabana:
Chía, Cajicá, Zipaquirá, Tocancipá, Sopó y Funza. Es la edición Sub-13 de una
liga multi-categoría (Sub-11/13/15) — mencionado en la descripción del torneo.

**Qué mostrar:** dashboard de torneo (`/admin/torneos/{id}`) → cargar resultado
del próximo partido en vivo → ver cómo se recalculan posiciones solas →
exportar PDF/CSV → portal público `/t/liga-escolar-sabana-sub13-2026`
compartible por WhatsApp con patrocinadores visibles.

---

## 3. Capitán con historial fuerte

| | |
|---|---|
| **Email** | `capitan.halcones@futgo.co` |
| **Nombre** | Carlos Reyes |
| **Club** | Halcones FC (Bucaramanga) |
| **futgo_id** | `FG-E9WKY3` |

Halcones FC juega la **Copa Élite Santander 2026** (`copa-elite-santander-2026`,
Bucaramanga, `knockout_only`, `in_progress`) — 3 de 4 cuartos de final jugados,
Halcones avanzó ganando 3-1 con hat-trick y MVP del jugador estrella (ver #4).
Además tiene **2 amistosos jugados** (vs Tigres del Norte 2-1, vs Chapinero FC
1-1) con conversación post-partido.

**Qué mostrar:** "Mis Equipos" → plantilla de Halcones FC → bracket de la Copa
Élite (cuarto pendiente con convocatoria activa) → "Mis amistosos" con el
historial jugado.

---

## 4. Jugador estrella — Mi Carrera

| | |
|---|---|
| **Email** | `jugador.estrella@futgo.co` |
| **Nombre** | Andrés Suárez |
| **Club** | Halcones FC (delantero, dorsal 9) |
| **futgo_id** | `FG-W8RQ3W` |

Mi Carrera muestra: hat-trick (3 goles) y MVP en los cuartos de final de la
Copa Élite Santander, 1 logro desbloqueado, puesto visible en el ranking de la
plataforma, fair play 100, credencial QR activa, y 2 amistosos jugados con
Halcones (con seguimiento mutuo al capitán de Tigres del Norte — "jugué con
vos"). Calificación de confiabilidad positiva registrada.

**Qué mostrar:** "Mi Carrera" (acumulado + logros) → credencial QR → posición
en `/torneos/ranking` → "jugué con vos" con el capitán de Tigres del Norte →
agenda con el próximo partido de la Copa Élite.

---

## 5. Jugador libre con oportunidad abierta

| | |
|---|---|
| **Email** | `libre@futgo.co` |
| **Nombre** | Brayan Lerma |
| **Ciudad** | Bucaramanga |

Tiene publicada una oportunidad **BUSCAR_EQUIPO** abierta (id `#5`, nivel
intermedio, sin respuestas todavía) en `/oportunidades`. También sigue a
Halcones FC, Los Cóndores y Tigres del Norte.

**Qué mostrar:** `/oportunidades` → su publicación BUSCAR_EQUIPO → explorar
oportunidades de otras ciudades (incluida la ⚡ modo rápido de Chía) → Feed con
novedades de los clubes que sigue.

---

## 6. Árbitro / validador de credenciales QR

| | |
|---|---|
| **Email** | `arbitro@futgo.co` |
| **Nombre** | Andrés Rojas |
| **Ciudad** | Bucaramanga |

5 validaciones ya registradas en la Copa Élite Santander (2 habilitado QR, 1
no_habilitado, 1 no_encontrado, 1 habilitado manual) — útil para mostrar el
historial además del escaneo en vivo.

**Qué mostrar:** escanear/ingresar manualmente el `futgo_id` de cualquier
jugador (p. ej. `FG-W8RQ3W` de Andrés Suárez) en el validador de credenciales
de un partido de la Copa Élite.

---

## Mapa rápido de torneos por ciudad

| Torneo | Slug | Ciudad | Formato | Estado |
|---|---|---|---|---|
| Liga Escolar Sabana Sub-13 2026 | `liga-escolar-sabana-sub13-2026` | Chía (Sabana) | grupos + eliminatoria | **`in_progress`** — vivo, público, con patrocinadores |
| Liga Barrial Bogotá 2026 | `liga-barrial-bogota-2026` | Bogotá | todos contra todos | **`finished`** — campeón Chapinero FC, invicto |
| Liga Medellín 2026 | `liga-medellin-2026` | Medellín | grupos + eliminatoria | **`in_progress`** — semifinales jugadas, final programada |
| Copa Élite Santander 2026 | `copa-elite-santander-2026` | Bucaramanga | eliminación directa | **`in_progress`** — 3/4 cuartos jugados |
| Torneo Empresarial Café 2026 | `torneo-empresarial-cafe-2026` | Bucaramanga | grupos + eliminatoria | **`open`** — inscripciones abiertas, cupos parciales |
| Torneo Privado Club Ejecutivos | `torneo-privado-club-ejecutivos` | Bucaramanga | grupos + eliminatoria | `draft`, privado (sin equipos) |

Amistoso en disputa para mostrar la resolución admin: **Poblado United vs
Belén FC** (`/admin/amistosos`). Oportunidad en modo rápido ⚡:
**Colegio San Rafael Chía** busca rival urgente en Chía (`/oportunidades`).
