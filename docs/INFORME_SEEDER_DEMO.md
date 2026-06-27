# Informe — Seeder de Demostración FutGO

> Mundo de demostración completo y coherente: una comunidad de fútbol amateur
> colombiano (epicentro Bucaramanga, con Medellín, Bogotá, Cali y Barranquilla)
> donde conviven torneos organizados, amistosos espontáneos, jugadores buscando
> equipo, equipos buscando rival y una comunidad activa con historial real.

## Cómo correrlo

```bash
php artisan migrate:fresh --seed
```

El `DatabaseSeeder` ejecuta el módulo Polla (congelado) + el catálogo de logros y,
si `APP_ENV=local` o `DEMO_DATA=true`, el mundo demo completo (`Demo\DemoSeeder`).
Al finalizar imprime un **resumen numérico** y un **autodiagnóstico de cobertura**
que lista cualquier vacío detectado (debe decir *cobertura COMPLETA*).

URL local: http://futgo.test:8080

---

## 1. Cuentas de acceso (contraseña: `password` para todas)

| Email | Rol | Para qué sirve en la demo |
|---|---|---|
| `admin@futgo.co` | Admin global | Ve todo: torneos, moderación, disputas de amistosos, reclamos escalados. Sigue los torneos activos. |
| `organizador@futgo.co` | Torneo admin + capitán | Creó los 5 torneos y capitanea **Los Cóndores**. Gestiona resultados, convocatorias, inscripciones. |
| `capitan.halcones@futgo.co` | Capitán | Capitán del campeón **Halcones FC**. Publica oportunidades, gestiona plantilla y amistosos. |
| `jugador.estrella@futgo.co` | Jugador | Carrera más rica (Andrés Suárez): 19 goles, 3 MVP, 3 torneos, logros, historial. |
| `libre@futgo.co` | Jugador libre | Brayan Lerma, sin equipo. Tiene una oportunidad **BUSCAR_EQUIPO** activa y sigue clubes. |
| `arbitro@futgo.co` | Validador | Andrés Rojas. Aparece como quien validó credenciales (QR) en la Copa Élite. |
| `fredy.agudelo@futgo.co` | Jugador | Su documento coincide con un jugador `por_verificar` → ve el banner de **reclamo de perfil**. |
| `marlon.bacca@futgo.co` | Jugador libre | Tiene una oportunidad **BUSCAR_EQUIPO** ya **vencida**. |

Capitanes adicionales (todos `password`): `hernan.cardenas@futgo.co` (Deportivo Café, torneo_admin),
`edinson.quintero@futgo.co` (Atlético Guane), `duvan.restrepo@futgo.co` (Tigres del Norte),
`oscar.patino@futgo.co` (Independiente Sur), `wilmer.mina@futgo.co` (Los Guaduales FC),
`yefferson.mosquera@futgo.co` (Palmira United), `teofilo.barrios@futgo.co` (Caribe FC),
`gustavo.henao@futgo.co` (Academia Oro, torneo_admin).

> ~69 usuarios en total. El resto de jugadores registrados usan emails
> `nombre.apellido@futgo.co`. Cada club tiene además jugadores `por_verificar`
> (sin cuenta), anotados solo con nombre + documento.

---

## 2. Equipos permanentes (clubs)

| Club | Ciudad | Nivel | Capitán |
|---|---|---|---|
| Halcones FC | Bucaramanga | competitivo | Carlos Reyes |
| Los Cóndores | Bucaramanga | intermedio | Mauricio Ortiz (organizador) |
| Deportivo Café | Bucaramanga | recreativo | Hernán Cárdenas |
| Atlético Guane | Bucaramanga | intermedio | Édinson Quintero |
| Tigres del Norte | Medellín | competitivo | Duván Restrepo |
| Independiente Sur | Bogotá | intermedio | Óscar Patiño |
| Los Guaduales FC | Cali | recreativo | Wílmer Mina |
| Palmira United | Cali | intermedio | Yefferson Mosquera |
| Caribe FC | Barranquilla | competitivo | Teófilo Barrios |
| Academia Oro | Medellín | elite_amateur | Gustavo Henao |

Cada club tiene plantilla permanente (~14 jugadores) con titulares registrados +
jugadores `por_verificar`. Halcones FC (el campeón / showcase) lleva más cuentas
registradas.

---

## 3. Qué cubre el seeder

### Módulo Torneos — 5 torneos en todos los estados

| # | Torneo | Estado | Formato | Detalle |
|---|---|---|---|---|
| 1 | **Liga Recreativa Bucaramanga 2025** | `finished` | Grupos + eliminación | 8 equipos, 2 grupos de 4, semis + final + 3er puesto. **Campeón: Halcones FC**. Goles, asistencias, tarjetas (1 roja), MVP, convocatorias variadas, baja por lesión, 2 patrocinadores, recordatorios enviados y un **empate absoluto → sorteo determinista** (`standing_draws`). |
| 2 | **Copa Élite Santander 2026** | `in_progress` | Eliminación directa | 8 equipos, cuartos de final: 2 jugados, 2 pendientes (Halcones y Atlético Guane) con **convocatorias activas** y **recordatorio programado**. Patrocinador. Base de las **validaciones de credencial**. |
| 3 | **Torneo Relámpago Nocturno** | `in_progress` | Todos contra todos | 5 equipos (10 partidos), 4 jugados. **Lidera Tigres del Norte** (9 pts); empate en 2º/3º. Cancha sintética nocturna, viernes. |
| 4 | **Torneo Empresarial Café 2026** | `open` | Grupos + eliminación | Inscripciones abiertas (cupo $180.000). 5 inscritos: **4 aprobados + 1 pendiente** + 1 **invitación por email** a un equipo externo. |
| 5 | **Torneo Privado Club Ejecutivos** | `draft` | — | Borrador **privado** (no aparece en el portal público). Sin equipos. |

### Módulo Torneos — reputación y datos transversales

- **Acumulado de carrera** (`player_career_stats`) para todos los registrados.
- **Fair play** (`fair_play_scores`) jugadores y equipos.
- **Logros** (`user_achievements`): 60 otorgados a 57 jugadores distintos.
- **Ranking** (`futgo_rankings`): jugadores y equipos, scope global + por ciudad.
- **Validaciones de credencial (QR)**: 5 registros (2 habilitado, 1 no_habilitado,
  1 no_encontrado, 1 manual).
- Convocatorias, alineaciones, eventos de partido, movimientos de plantilla,
  patrocinadores, invitaciones y recordatorios.

### Módulo FutGO Social

- **Oportunidades** (7): los 4 tipos (BUSCAR_RIVAL, BUSCAR_JUGADOR, BUSCAR_REFUERZO,
  BUSCAR_EQUIPO) y todos los estados (abierta, en negociación, cerrada, vencida) con
  respuestas en sus 4 estados (pendiente, aceptada, rechazada, contrapropuesta).
- **Amistosos** (2): uno **jugado** con doble confirmación (Halcones 2-1 Tigres) y
  uno **confirmado/pendiente** (Los Guaduales vs Palmira, nacido de una oportunidad).
- **Conversaciones** (3) con primer mensaje **estructurado del sistema** + mensajes
  **libres** humanos (coordinación de amistoso, post-partido y reclutamiento).
- **Seguimientos** (15): a clubes, torneos y jugadores (incluido un follow mutuo).
- **Confiabilidad**: eventos (no_show, cancelación tardía, calificaciones ±) y
  **scores recalculados** con el servicio real.
- **Feed** (72 eventos): generados por los servicios reales (oportunidades, amistosos,
  logros) + el titular del campeón.
- **Canchas** (5 venues) compartidas, una vinculada a una oportunidad y a un amistoso.

> Todo el bloque social se genera **con los servicios reales** (OpportunityService,
> FriendlyMatchService, ConversationService, FollowService, FeedService,
> ReliabilityService), no replicando su lógica.

---

## 4. Flujo de prueba por módulo y funcionalidad

### A. Portal público (sin login)
1. `/torneos` → buscador de torneos públicos: aparecen Liga Recreativa, Copa Élite,
   Relámpago y Empresarial. **No** aparece el Torneo Privado (draft/privado).
2. Abrí **Liga Recreativa Bucaramanga 2025**: tabla de posiciones, goleadores,
   eliminatoria completa, campeón Halcones FC, patrocinadores, botón de compartir.
3. `/j/{futgo_id}` (ficha pública de un jugador) y `/c/{slug}` (cancha): navegables sin login.

### B. Login como jugador estrella (`jugador.estrella@futgo.co`)
1. **Mi Carrera**: PJ, goles (19), MVP (3), torneos (3), logros, historial de
   temporadas, sección "Jugué con" y métricas sociales.
2. **Credencial QR**: el jugador tiene `futgo_id` → credencial descargable.
3. **Agenda** (`/agenda`): partidos próximos de sus equipos + convocatorias inline.

### C. Login como capitán (`capitan.halcones@futgo.co`)
1. **Gestión del club**: plantilla permanente, jugadores `por_verificar`, escudo.
2. **Amistosos** (`/amistosos`): el amistoso jugado vs Tigres (resultado confirmado)
   y la conversación post-partido.
3. **Oportunidades**: publicó BUSCAR_JUGADOR (en negociación, con contrapropuesta) y
   BUSCAR_REFUERZO (abierta) → revisar respuestas, aceptar/rechazar/contraproponer.
4. **Mensajes** (`/mensajes`): hilos con el primer mensaje estructurado + libres.

### D. Login como organizador / torneo admin (`organizador@futgo.co`)
1. `/admin/torneos`: gestiona los 5 torneos.
2. **Copa Élite (in_progress)**: cargar resultado de los cuartos pendientes;
   revisar **convocatorias** (confirmados/pendientes/declinados).
3. **Empresarial Café (open)**: aprobar el equipo pendiente; ver la invitación por email.
4. **Club Ejecutivos (draft)**: editar/activar el borrador privado.

### E. Login como árbitro (`arbitro@futgo.co`)
1. **Validar credencial** (`/torneos/validar`): escanear/ingresar un `futgo_id`.
   Probar uno válido (habilitado) y uno inventado (no_encontrado). Historial en la Copa Élite.

### F. Login como jugador libre (`libre@futgo.co`)
1. **Oportunidades** (`/oportunidades`): ve su BUSCAR_EQUIPO activa; explora por ciudad/nivel.
2. **Feed** (`/feed`): eventos relevantes por sus follows (Halcones, Los Cóndores) y ciudad/nivel.
3. Responder a una oportunidad BUSCAR_JUGADOR.

### G. Reclamo de perfil (`fredy.agudelo@futgo.co`)
1. Al iniciar sesión aparece el banner **"Reclamar mi perfil"** (su documento coincide
   con un jugador `por_verificar` de Deportivo Café).
2. `/reclamos` → enviar el reclamo → lo aprueba el capitán de Deportivo Café (o un admin
   si está escalado) y hereda el historial.

### H. Admin global (`admin@futgo.co`)
1. **Moderación** (`/admin/moderacion`): reportes de contenido (si los hubiera).
2. **Disputas de amistosos** (`/admin/amistosos`): bandeja de disputas y cancelaciones.
3. **Reclamos escalados** (`/admin/torneos/reclamos`).
4. **Feed/Seguimientos**: sigue los torneos activos.

---

## 5. Estructura del seeder

```
database/seeders/
├── DatabaseSeeder.php            # Polla + base + (local/DEMO_DATA) → Demo\DemoSeeder
├── AchievementSeeder.php         # catálogo de logros (data-driven)
└── Demo/
    ├── DemoData.php              # fuente de verdad: clubs, cuentas, pools de nombres
    ├── DemoSeeder.php            # orquestador + resumen + diagnoseGaps()
    ├── DemoUsersAndClubsSeeder.php
    ├── DemoTorneo1Seeder.php     # Liga Recreativa 2025 — FINISHED
    ├── DemoTorneo2Seeder.php     # Copa Élite 2026 — IN_PROGRESS (eliminatoria)
    ├── DemoTorneo3Seeder.php     # Relámpago Nocturno — IN_PROGRESS (todos contra todos)
    ├── DemoTorneo4Seeder.php     # Empresarial Café — OPEN
    ├── DemoTorneo5Seeder.php     # Club Ejecutivos — DRAFT
    ├── DemoReputacionSeeder.php  # reputación (servicios reales) + credenciales
    ├── DemoSocialSeeder.php      # oportunidades, amistosos, chat, follows, feed, confiabilidad
    └── Concerns/SeedsMatches.php # helpers de "jugar un partido"
```

### Notas y decisiones de implementación

- **Servicios reales**: el fixture, standings, stats, fair play, ranking, logros,
  oportunidades, amistosos, conversaciones, follows, feed y confiabilidad se generan
  con sus servicios de dominio (no se replica su lógica).
- **Tamaños de torneo** ajustados al motor de fixtures (requiere exactamente
  `max_teams` aprobados y aritmética de grupos válida) y a los 10 clubes del mundo
  base. Entre los 5 torneos se cubren los 3 formatos (grupos+eliminación, todos
  contra todos, eliminación directa) y todos los estados.
- **Jugador estrella**: ~19 goles, 3 MVP y 3 torneos. El objetivo de "+30 partidos"
  no se alcanza porque solo hay un torneo finalizado (el motor calcula PJ desde
  partidos reales); aun así es, por amplio margen, el perfil más rico.
- **Seeders antiguos** (`DemoSeeder.php` de Polla, `DemoTournamentSeeder.php`) quedan
  en el repositorio pero ya no se invocan desde `DatabaseSeeder`; el nuevo mundo demo
  los reemplaza funcionalmente. Se dejaron intactos para no perder utilidades de Polla.
- **Idempotencia**: pensado para `migrate:fresh --seed` (BD limpia). Las cuentas
  documentadas usan `updateOrCreate` por email.
