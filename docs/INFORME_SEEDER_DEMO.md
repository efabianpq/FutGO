# Informe — Seeder de Demostración FutGO

> Mundo de demostración completo y coherente: una comunidad de fútbol amateur
> colombiano **multi-ciudad** — Bucaramanga, Medellín, Bogotá (por localidad),
> Cali, Barranquilla y, con foco especial, la **Sabana de Bogotá** (Chía,
> Cajicá, Zipaquirá, Tocancipá, Sopó, Funza) a través de una liga escolar —
> donde conviven torneos organizados, amistosos espontáneos, jugadores
> buscando equipo, equipos buscando rival y una comunidad activa con historial
> real. Pensado también como material de grabación para video promocional:
> ver [`DEMO_CREDENCIALES.md`](DEMO_CREDENCIALES.md) y
> [`GUION_DEMO_PRESENTACION.md`](GUION_DEMO_PRESENTACION.md).

## Cómo correrlo

```bash
php artisan migrate:fresh --seed
```

El `DatabaseSeeder` ejecuta el catálogo de logros, documentos legales y
artículos de soporte y, si `APP_ENV=local` o `DEMO_DATA=true`, el mundo demo
completo (`Demo\DemoSeeder`). Al finalizar imprime un **resumen numérico** y un
**autodiagnóstico de cobertura** que lista cualquier vacío detectado (debe
decir *cobertura COMPLETA*).

URL local: http://futgo.test:8080

---

## 1. Cuentas de acceso (contraseña: `password` para todas)

| Email | Rol | Para qué sirve en la demo |
|---|---|---|
| `admin@futgo.co` | Admin global | Ve todo: torneos, moderación, disputas de amistosos, reclamos escalados. Sigue un torneo por ciudad objetivo. |
| `coordinadora.sabana@futgo.co` | Organizadora | Diana Ramírez, coordinadora de la **Liga Escolar Sabana Sub-13 2026** (Chía) — torneo prioritario para el video de administradores. No es capitana de ningún club. |
| `organizador@futgo.co` | Torneo admin + capitán | Creó los otros 5 torneos y capitanea **Los Cóndores** (Bucaramanga). |
| `capitan.halcones@futgo.co` | Capitán | Carlos Reyes, capitán de **Halcones FC**: historial fuerte (Copa Élite Santander en curso + 2 amistosos jugados). |
| `jugador.estrella@futgo.co` | Jugador | Andrés Suárez (Halcones FC): hat-trick + MVP en cuartos de la Copa Élite, logro desbloqueado, ranking visible, credencial QR, 2 amistosos jugados. |
| `libre@futgo.co` | Jugador libre | Brayan Lerma, sin equipo. Tiene una oportunidad **BUSCAR_EQUIPO** activa y sigue clubes. |
| `arbitro@futgo.co` | Validador | Andrés Rojas. Validó credenciales (QR) en la Copa Élite Santander. |
| `fredy.agudelo@futgo.co` | Jugador | Su documento coincide con un jugador `por_verificar` → ve el banner de **reclamo de perfil**. |
| `marlon.bacca@futgo.co` | Jugador libre | Tiene una oportunidad **BUSCAR_EQUIPO** ya **vencida**. |

Capitanes adicionales (todos `password`), agrupados por torneo en el que
juegan — ver §2 para el listado completo de 26 clubes.

> 165 usuarios en total. El resto de jugadores registrados usan emails
> `nombre.apellido@futgo.co`. Cada club tiene además jugadores `por_verificar`
> (sin cuenta), anotados solo con nombre + documento — refleja bien la
> plantilla real de un equipo escolar/amateur donde no todos tienen cuenta.

---

## 2. Equipos permanentes (26 clubs)

### Bucaramanga (mundo base)
| Club | Nivel | Capitán |
|---|---|---|
| Halcones FC | competitivo | Carlos Reyes |
| Los Cóndores | intermedio | Mauricio Ortiz (organizador) |
| Deportivo Café | recreativo | Hernán Cárdenas |
| Atlético Guane | intermedio | Édinson Quintero |

### Medellín (Liga Medellín 2026)
| Club | Nivel | Capitán |
|---|---|---|
| Tigres del Norte | competitivo | Duván Restrepo |
| Academia Oro | elite_amateur | Gustavo Henao |
| Belén FC | intermedio | Camilo Zapata |
| Laureles Atlético | intermedio | Sebastián Montoya |
| Poblado United | competitivo | Nicolás Vélez |
| Itagüí FC | intermedio | Andrés Zuluaga |
| Envigado Popular | recreativo | Jorge Ramírez |
| Bello FC | recreativo | Cristian Higuita |

### Bogotá por localidad (Liga Barrial Bogotá 2026)
| Club | Nivel | Capitán |
|---|---|---|
| Chapinero FC | competitivo | Felipe Cruz |
| Independiente Sur | intermedio | Óscar Patiño |
| Suba FC | intermedio | Diego Fonseca |
| Bosa Atlético | recreativo | Harold Niño |
| Kennedy United | intermedio | Yeison Cubillos |

### Sabana de Bogotá (Liga Escolar Sabana Sub-13 2026)
| Club | Ciudad | Capitán (coordinador/a) |
|---|---|---|
| Colegio San Rafael Chía | Chía | Álvaro Beltrán |
| Gimnasio Campestre Cajicá | Cajicá | Leonardo Suárez |
| Liceo La Sabana Zipaquirá | Zipaquirá | Pablo Cárdenas |
| Instituto Tocancipá | Tocancipá | Raúl Bermúdez |
| Escuela de Fútbol Sopó | Sopó | Javier Ospina |
| Real Funza FC | Funza | Orlando Caicedo |

### Cali / Barranquilla
| Club | Nivel | Capitán |
|---|---|---|
| Los Guaduales FC | recreativo | Wílmer Mina |
| Palmira United | intermedio | Yefferson Mosquera |
| Caribe FC | competitivo | Teófilo Barrios |

Cada club tiene plantilla permanente (~14 jugadores) con titulares registrados
+ jugadores `por_verificar`. Halcones FC lleva más cuentas registradas (es el
club "showcase" del histórico de la plataforma).

---

## 3. Qué cubre el seeder

### Módulo Torneos — 6 torneos, todos los estados, 6 ciudades

| # | Torneo | Ciudad | Estado | Formato | Detalle |
|---|---|---|---|---|---|
| 1 | **Liga Medellín 2026** | Medellín | `in_progress` | Grupos + eliminación | 8 equipos, 2 grupos de 4. Grupos cerrados, **semifinales jugadas**, final y tercer puesto ya cruzados pero **programados a futuro** (sin jugar) — bracket en vivo. Convocatorias, 1 baja por lesión, 2 patrocinadores, recordatorios. |
| 2 | **Copa Élite Santander 2026** | Bucaramanga | `in_progress` | Eliminación directa | 8 equipos, cuartos de final: **3 jugados** (Halcones FC avanza con hat-trick y MVP del jugador estrella), **1 pendiente** (Atlético Guane vs Independiente Sur) con convocatorias activas y recordatorio. Base de las validaciones de credencial. |
| 3 | **Liga Barrial Bogotá 2026** | Bogotá | `finished` | Todos contra todos | 5 equipos por localidad (10 partidos, todos jugados). **Campeón invicto: Chapinero FC**. Suba FC y Kennedy United terminan exactamente empatados → **sorteo determinista** (`standing_draws`). Goleador, MVP, tarjeta roja, 2 patrocinadores, posiciones cerradas. |
| 4 | **Torneo Empresarial Café 2026** | Bucaramanga | `open` | Grupos + eliminación | Inscripciones abiertas (cupo $180.000). 5 inscritos: 4 aprobados + 1 pendiente + 1 invitación por email a un equipo externo. |
| 5 | **Torneo Privado Club Ejecutivos** | Bucaramanga | `draft` | — | Borrador privado (no aparece en el portal público). Sin equipos. |
| 6 | **Liga Escolar Sabana Sub-13 2026** | Chía | `in_progress` | Grupos + eliminación | 6 colegios/escuelas de la Sabana en 2 grupos de 3. **4/6 partidos de grupo jugados, 2 programados a futuro** con convocatorias activas — el torneo "vivo" del video para administradores. `visibility=public`, 2 patrocinadores. Edición Sub-13 de una liga multi-categoría (Sub-11/13/15). |

### Módulo Torneos — reputación y datos transversales

- **Acumulado de carrera** (`player_career_stats`) para todos los registrados.
- **Fair play** (`fair_play_scores`) jugadores y equipos.
- **Logros** (`user_achievements`): otorgados a 141 jugadores distintos.
- **Ranking** (`futgo_rankings`): jugadores y equipos, scope global + por ciudad.
- **Validaciones de credencial (QR)**: 5 registros (2 habilitado, 1 no_habilitado,
  1 no_encontrado, 1 manual) en la Copa Élite Santander.
- Convocatorias, alineaciones, eventos de partido, movimientos de plantilla,
  patrocinadores, invitaciones y recordatorios.

### Módulo FutGO Social

- **Oportunidades** (8): los 4 tipos (BUSCAR_RIVAL, BUSCAR_JUGADOR,
  BUSCAR_REFUERZO, BUSCAR_EQUIPO), todos los estados (abierta, en negociación,
  cerrada, vencida), respuestas en sus 4 estados, y **1 en modo rápido ⚡**
  (Colegio San Rafael Chía busca rival urgente). Distribuidas por ciudad:
  Bucaramanga, Cali, Bogotá, Barranquilla y Chía.
- **Amistosos** (6): **4 jugados** (Halcones-Tigres, Chapinero-Suba, San
  Rafael Chía-Gimnasio Cajicá, Halcones-Chapinero), **1 confirmado/pendiente**
  (Los Guaduales vs Palmira, nacido de una oportunidad) y **1 en disputa**
  (Poblado United vs Belén FC — cada capitán reportó un marcador distinto).
- **Conversaciones** (3) con primer mensaje **estructurado del sistema** +
  mensajes **libres** humanos (coordinación de amistoso, post-partido y
  reclutamiento).
- **Seguimientos** (34): a clubes, torneos y jugadores de todas las ciudades
  (incluidos follows mutuos y la organizadora de la Sabana siguiendo sus 6
  colegios).
- **Confiabilidad**: eventos (no_show, cancelación tardía, calificaciones ±) y
  **scores recalculados** con el servicio real — no todos en 100 parejo.
- **Feed** (166 eventos): generados por los servicios reales (oportunidades,
  amistosos, logros) + el titular del campeón de Bogotá.
- **Canchas** (8 venues): Bucaramanga ×3, Cali, Medellín, Bogotá y **Sabana
  ×2** (Chía, Cajicá) — varias vinculadas a oportunidades/amistosos.

> Todo el bloque social se genera **con los servicios reales**
> (OpportunityService, FriendlyMatchService, ConversationService,
> FollowService, FeedService, ReliabilityService), no replicando su lógica.

---

## 4. Flujo de prueba por módulo y funcionalidad

### A. Portal público (sin login)
1. `/torneos` → buscador de torneos públicos: aparecen los 5 públicos
   (Medellín, Copa Élite, Bogotá, Empresarial, Sabana). **No** aparece el
   Torneo Privado (draft/privado).
2. `/t/liga-escolar-sabana-sub13-2026`: torneo en vivo, patrocinadores,
   próximos partidos.
3. `/t/liga-barrial-bogota-2026`: torneo finalizado, posiciones cerradas,
   goleador, campeón Chapinero FC, tarjeta compartible.
4. `/j/{futgo_id}` (ficha pública de jugador) y `/c/{slug}` (cancha):
   navegables sin login.

### B. Login como jugador estrella (`jugador.estrella@futgo.co`)
1. **Mi Carrera**: hat-trick y MVP en la Copa Élite, logro desbloqueado,
   fair play 100, historial de temporadas.
2. **Credencial QR**: `futgo_id` `FG-W8RQ3W` → credencial descargable.
3. **Ranking** (`/torneos/ranking`): posición visible en el ranking global.
4. **Agenda** (`/agenda`): próximo cuarto de final pendiente + convocatoria.

### C. Login como capitán (`capitan.halcones@futgo.co`)
1. **Gestión del club**: plantilla permanente, jugadores `por_verificar`, escudo.
2. **Amistosos** (`/amistosos`): 2 amistosos jugados (vs Tigres, vs Chapinero)
   con conversación post-partido.
3. **Oportunidades**: publicó BUSCAR_JUGADOR (en negociación, con
   contrapropuesta) y BUSCAR_REFUERZO (abierta) → revisar respuestas.
4. **Mensajes** (`/mensajes`): hilos con el primer mensaje estructurado + libres.

### D. Login como organizadora de la Sabana (`coordinadora.sabana@futgo.co`)
1. `/admin/torneos/{id}` de la Liga Escolar Sabana: fixture, cargar resultado
   de un partido de grupo pendiente, ver posiciones recalculadas solas.
2. Exportar posiciones/goleadores (PDF/CSV).
3. Compartir el portal público por WhatsApp.

### E. Login como organizador (`organizador@futgo.co`)
1. `/admin/torneos`: gestiona los otros 5 torneos.
2. **Liga Medellín (in_progress)**: revisar bracket con semifinales jugadas y
   la final programada; convocatoria activa para la final.
3. **Empresarial Café (open)**: aprobar el equipo pendiente; ver la invitación
   por email.
4. **Club Ejecutivos (draft)**: editar/activar el borrador privado.

### F. Login como árbitro (`arbitro@futgo.co`)
1. **Validar credencial** (`/torneos/validar`): probar un `futgo_id` válido
   (p. ej. `FG-W8RQ3W`) y uno inventado (no_encontrado). Historial en la Copa Élite.

### G. Login como jugador libre (`libre@futgo.co`)
1. **Oportunidades** (`/oportunidades`): ve su BUSCAR_EQUIPO activa; explora
   por ciudad/nivel, incluida la oportunidad ⚡ modo rápido de Chía.
2. **Feed** (`/feed`): eventos relevantes por sus follows y ciudad/nivel.
3. Responder a una oportunidad BUSCAR_JUGADOR.

### H. Reclamo de perfil (`fredy.agudelo@futgo.co`)
1. Al iniciar sesión aparece el banner **"Reclamar mi perfil"** (su documento
   coincide con un jugador `por_verificar` de Deportivo Café).
2. `/reclamos` → enviar el reclamo → lo aprueba el capitán de Deportivo Café
   (o un admin si está escalado) y hereda el historial.

### I. Admin global (`admin@futgo.co`)
1. **Moderación** (`/admin/moderacion`): reportes de contenido (si los hubiera).
2. **Disputas de amistosos** (`/admin/amistosos`): el amistoso en disputa
   Poblado United vs Belén FC, y bandeja de cancelaciones.
3. **Reclamos escalados** (`/admin/torneos/reclamos`).
4. **Feed/Seguimientos**: sigue un torneo por cada ciudad objetivo.

---

## 5. Estructura del seeder

```
database/seeders/
├── DatabaseSeeder.php            # base + (local/DEMO_DATA) → Demo\DemoSeeder
├── AchievementSeeder.php         # catálogo de logros (data-driven)
└── Demo/
    ├── DemoData.php              # fuente de verdad: clubs, cuentas, pools de nombres
    ├── DemoSeeder.php            # orquestador + resumen + diagnoseGaps()
    ├── DemoUsersAndClubsSeeder.php
    ├── DemoTorneo1Seeder.php     # Liga Medellín 2026 — IN_PROGRESS (eliminatoria activa)
    ├── DemoTorneo2Seeder.php     # Copa Élite Santander 2026 — IN_PROGRESS (eliminatoria)
    ├── DemoTorneo3Seeder.php     # Liga Barrial Bogotá 2026 — FINISHED
    ├── DemoTorneo4Seeder.php     # Empresarial Café — OPEN
    ├── DemoTorneo5Seeder.php     # Club Ejecutivos — DRAFT
    ├── DemoTorneo6Seeder.php     # Liga Escolar Sabana Sub-13 2026 — IN_PROGRESS ("vivo")
    ├── DemoReputacionSeeder.php  # reputación (servicios reales) + credenciales
    ├── DemoSocialSeeder.php      # oportunidades, amistosos, chat, follows, feed, confiabilidad
    └── Concerns/SeedsMatches.php # helpers de "jugar un partido"
```

### Notas y decisiones de implementación

- **Servicios reales**: el fixture, standings, stats, fair play, ranking, logros,
  oportunidades, amistosos, conversaciones, follows, feed y confiabilidad se generan
  con sus servicios de dominio (no se replica su lógica).
- **Ciudades y clubes "fully local"**: cada torneo de ciudad (Medellín, Bogotá,
  Sabana) usa clubes creados específicamente de esa ciudad/municipio en vez de
  reetiquetar clubes existentes — pensado para que un video grabado en esa
  ciudad muestre nombres de equipo creíbles y locales.
- **Copa Élite Santander no se tocó** al reorganizar por ciudad: su slug y sus
  cruces de cuartos son la base fija de `DemoReputacionSeeder` (validaciones de
  credencial: Halcones FC "activo", Caribe FC "eliminado"). Su QF3
  (Halcones vs Los Cóndores) se jugó (antes quedaba pendiente) para que el
  jugador estrella tuviera un partido de torneo real del cual sacar
  estadísticas — de lo contrario Mi Carrera quedaba vacía al sacar a Halcones
  de la Liga Medellín.
- **Categoría escolar**: el enum `tournaments.category` no tiene un valor
  `sub13` (solo `sub15`, `sub17`, `sub20`...); la Liga Escolar Sabana usa
  `category=sub15` y deja "Sub-13" como texto en el nombre/descripción del
  torneo (que sí es libre).
- **Idempotencia**: pensado para `migrate:fresh --seed` (BD limpia). Las
  cuentas documentadas usan `updateOrCreate` por email.
