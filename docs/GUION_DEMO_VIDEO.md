# 🎬 Guion demostrativo FutGO — video completo

> Guion para video demostrativo de toda la herramienta, con los accesos del **nav v3**
> (ver `CLAUDE.md` §14). Dos segmentos secuenciales: **(1) administradores de torneos**
> y **(2) capitanes y jugadores**. Datos anclados al **seeder de demostración**
> (ver `docs/INFORME_SEEDER_DEMO.md`).

**Antes de grabar:** corré `php artisan migrate:fresh --seed` (carga el mundo demo).
Contraseña de todas las cuentas: `password`. URL: `http://futgo.test:8080`.
Grabá con una ciudad de referencia (Bucaramanga) para que las "sugeridas" y el Feed tengan contenido.

## Mapa de accesos del nav v3 (tenelo a mano mientras grabás)

- **Inicio** 🏠 → dashboard
- **Jugar** ▾ → Oportunidades · Amistosos · Modo rápido ⚡ · Agenda
- **Competir** ▾ → Mis Torneos · Buscar Torneo · Ranking de la plataforma
- **Comunidad** ▾ → Canchas · Buscar jugadores y clubes
- Header derecho: 🔍 Buscar · 🔔 Feed · 💬 Mensajes · **avatar** (Mi Carrera · Mis Equipos · Credencial · Configurar perfil · Reclamar perfil · Salir)
- Gestión de torneos (admin): **nav de administración** → `/admin/torneos`

## Cuentas usadas

| Email | Rol | Segmento |
|---|---|---|
| `organizador@futgo.co` | Torneo admin + capitán | 1 (principal) |
| `admin@futgo.co` | Admin global | 1 (cierre) |
| `arbitro@futgo.co` | Validador credencial QR | 1 |
| `jugador.estrella@futgo.co` | Jugador (carrera rica) | 2 |
| `capitan.halcones@futgo.co` | Capitán Halcones FC | 2 |
| `libre@futgo.co` | Jugador sin equipo | 2 |
| `fredy.agudelo@futgo.co` | Reclamo de perfil | 2 |

---

## 🟢 Apertura (15–20 s)

> *Voz en off:* "Organizar y jugar fútbol amateur genera un caos de grupos de WhatsApp,
> planillas sueltas y memoria. **FutGO** ordena todo: una sola plataforma para administrar
> torneos, construir tu carrera y conectar con la comunidad. Veamos cómo."

Plano del logo → corte al **portal público** `/torneos` (sin login): tarjetas de torneos
con barra de progreso. Transición al Segmento 1.

---

# 🏆 SEGMENTO 1 — Para administradores de torneos

**Cuenta principal:** `organizador@futgo.co` · **Cierre:** `admin@futgo.co` y `arbitro@futgo.co`
**Mensaje:** *"FutGO te quita el trabajo manual de gestionar un torneo."*

### Escena 1 — El dashboard de Inicio (25 s)
Login `organizador@futgo.co` → cae en **Inicio** 🏠.
- Mostrar el saludo, los **Recordatorios** (cuartos de Copa Élite pendientes / convocatorias por responder) y **Tu semana**.

> "Al entrar, FutGO ya te dice qué requiere tu atención hoy: partidos por cargar, convocatorias, lo que vence pronto. No tenés que buscar nada."

### Escena 2 — El ciclo de vida del torneo (40 s)
Ir a **`/admin/torneos`** (nav de administración). Recorrer los 5 torneos que cubren **todos los estados**:
- **Club Ejecutivos** → `draft` (privado, no sale en el portal).
- **Empresarial Café** → `open`: inscripciones abiertas, **aprobar el equipo pendiente**, mostrar la invitación por email.
- **Copa Élite** y **Relámpago** → `in_progress`.
- **Liga Recreativa 2025** → `finished` (campeón Halcones FC).

> "Un torneo en FutGO recorre un ciclo claro: borrador, inscripciones, en juego, finalizado. Acá controlás los cinco a la vez."

### Escena 3 — Fixture automático (25 s)
En **Empresarial Café** (open), mostrar la **generación de fixture**: elegir formato
(grupos+eliminación / todos contra todos / eliminación directa) → FutGO arma los cruces solo.

> "Definís el formato y FutGO genera el fixture completo. Nada de armar cruces a mano."

### Escena 4 — Cargar resultados y la planilla (45 s)
Abrir **Copa Élite** (in_progress) → un cuarto de final pendiente:
- Cargar marcador, **goles, asistencias, tarjetas, MVP** por jugador.
- Mostrar cómo la **tabla de posiciones se recalcula sola** y, en la Liga finalizada, el caso del **empate absoluto resuelto por sorteo determinista auditado**.
- Mostrar **convocatorias** (confirmados / pendientes / declinados) y el **recordatorio automático** de partido.

> "Cargás el resultado una vez y FutGO actualiza posiciones, estadísticas y desempates automáticamente, con criterios auditables. Las convocatorias y los recordatorios salen solos."

### Escena 5 — Reputación automática + portal público (30 s)
Al cerrar un torneo se disparan **ranking, fair play y logros**. Cortar al **portal público** `/t/{slug}`:
- Posiciones, goleadores, eliminatoria, campeón, patrocinadores.
- Botón **Compartir por WhatsApp** y **tarjeta PNG**.

> "Cuando el torneo termina, la reputación de jugadores y equipos se calcula sola. Y todo queda en un portal público que cualquiera puede ver y compartir."

### Escena 6 — Identidad y control (árbitro) (20 s)
Login rápido `arbitro@futgo.co` → **Validar credencial** (`/torneos/validar`): escanear un
`futgo_id` válido (habilitado) y uno inventado (no encontrado).

> "Cada jugador tiene una credencial QR. El árbitro valida identidad en segundos — se acabaron los suplantados."

### Escena 7 — Cierre admin global (20 s)
Login `admin@futgo.co`: pasada rápida por **Moderación** (`/admin/moderacion`),
**Disputas de amistosos** (`/admin/amistosos`) y **Reclamos escalados** (`/admin/torneos/reclamos`).

> "Y para la organización, un panel global: moderación, disputas y reclamos, todo bajo control."

**Transición:** *"Eso es del lado de quien organiza. Ahora veámoslo desde quien juega."*

---

# ⚽ SEGMENTO 2 — Para capitanes y jugadores

**Cuentas:** `jugador.estrella@futgo.co` · `capitan.halcones@futgo.co` · `libre@futgo.co` · `fredy.agudelo@futgo.co`
**Mensaje:** *"FutGO es tu carrera y tu comunidad en el fútbol amateur."*

### Escena 8 — Tu hoja de vida deportiva (35 s)
Login `jugador.estrella@futgo.co` → **avatar ▾ → Mi Carrera**.
- Stats: PJ, **19 goles, 3 MVP, 3 torneos**, logros, historial por temporada, sección **"Jugué con"**.
- Abrir el modal **Credencial QR**.

> "Cada partido que jugás queda registrado. Esta es tu hoja de vida real: goles, MVP, logros, con quién jugaste — y tu credencial oficial. Tu fútbol amateur por fin tiene memoria."

### Escena 9 — Inicio + Agenda del jugador (25 s)
Volver a **Inicio** 🏠: recordatorios (convocatoria por confirmar **inline**), **Tu semana**,
**Sugeridas para vos** (oportunidades de tu ciudad), **Novedades**.
Luego **Jugar ▾ → Agenda**: todo lo programado en orden cronológico.

> "Tu pantalla de inicio te muestra qué se viene y qué tenés que responder. Confirmás una convocatoria sin salir de ahí. Y la Agenda reúne todo lo que tenés por jugar."

### Escena 10 — Ficha pública e identidad (20 s)
Abrir la **ficha pública** `/j/{futgo_id}` del jugador estrella (sin login, en otra pestaña).

> "Y tenés una ficha pública compartible: tu identidad como jugador, lista para mostrar a un equipo que te quiere fichar."

### Escena 11 — El capitán gestiona su club (30 s)
Login `capitan.halcones@futgo.co` → **avatar ▾ → Mis Equipos** → gestión de **Halcones FC**:
- Plantilla permanente, jugadores `por_verificar`, escudo, capitán.

> "El capitán arma una sola vez su plantilla permanente. La inscribe a cualquier torneo con un clic, sin recargar nombres."

### Escena 12 — Conectar: Oportunidades (40 s)
**Jugar ▾ → Oportunidades**:
- Mostrar los 4 tipos (BUSCAR_RIVAL, BUSCAR_JUGADOR, BUSCAR_REFUERZO, BUSCAR_EQUIPO) y el filtro por ciudad/nivel.
- Como capitán, abrir una propia (BUSCAR_JUGADOR en negociación) y **aceptar/contraproponer** una respuesta.
- Mostrar que aceptar **BUSCAR_RIVAL genera un amistoso confirmado + una conversación** automáticamente.

> "¿Te falta rival, un jugador o un refuerzo? Lo publicás y la comunidad responde. Cuando aceptás, FutGO ya te arma el amistoso y abre el chat — todo conectado."

### Escena 13 — Modo rápido ⚡ (15 s)
**Jugar ▾ → Modo rápido**: formulario simplificado "necesito rival para mañana".

> "¿Urgente? Modo rápido: rival para hoy o mañana, en dos campos."

### Escena 14 — Amistosos con confianza (30 s)
**Jugar ▾ → Amistosos**: el amistoso jugado **Halcones 2–1 Tigres** con **doble confirmación**
de resultado. Mencionar disputa → admin, y el **score de confiabilidad** (no-shows penalizan, pausan).

> "Los dos equipos confirman el resultado. Si no coinciden, va a disputa. Y quien no se presenta pierde confiabilidad — así la comunidad se autorregula."

### Escena 15 — Comunidad: canchas y búsqueda (25 s)
**Comunidad ▾ → Canchas**: catálogo, perfil de cancha con disponibilidad.
Header **🔍 Buscar**: tipear un nombre → resultados de jugadores, clubes, torneos y canchas en un solo lugar.

> "Un catálogo de canchas hecho por la comunidad, y un buscador global para encontrar a cualquiera: jugadores, clubes, torneos o canchas."

### Escena 16 — Mensajes y Feed (25 s)
Header **💬 Mensajes**: hilo con **primer mensaje estructurado del sistema** + mensajes libres + compartir contacto.
Header **🔔 Feed**: novedades de los clubes/jugadores/torneos que seguís y de tu ciudad.

> "Cada acuerdo abre un chat — nunca hablás con un desconocido sin contexto. Y el Feed te mantiene al día con lo que sigue tu comunidad."

### Escena 17 — Reclamo de perfil (20 s)
Login `fredy.agudelo@futgo.co`: aparece el **banner "Reclamar mi perfil"** → `/reclamos` → enviar reclamo.

> "¿Te anotaron a mano en un equipo antes de tener cuenta? Reclamás ese perfil y heredás todo tu historial. Nada se pierde."

### Escena 18 — Cierre con Ranking (15 s)
**Competir ▾ → Ranking de la plataforma**: los mejores de la comunidad.

> "Y todo suma a un ranking de la plataforma. En FutGO, el fútbol amateur deja huella."

---

## 🔚 Cierre (15 s)

> *Voz en off:* "FutGO: para quien organiza, gestión de torneos sin caos. Para quien juega,
> una carrera y una comunidad de verdad. **Donde crece el fútbol amateur.**"

Plano del dashboard de Inicio → logo.

---

**Duración estimada:** Segmento 1 ≈ 3:30 · Segmento 2 ≈ 4:30 · total ≈ 8 min
(recortable a 5 saltando escenas 3, 10 y 15).
