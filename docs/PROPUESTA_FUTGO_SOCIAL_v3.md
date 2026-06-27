# FutGO Social — Documento de visión (v3, definitivo)

**Estado:** documento de visión y producto, sin implementar. Base para planear migraciones y sprints.
**Premisa:** no se reemplaza nada del módulo Torneos ni Polla (congelado). FutGO deja de ser "un gestor de torneos con funciones sociales" y pasa a ser **una plataforma donde el torneo es una de varias experiencias** — junto con el amistoso espontáneo, el reclutamiento, el feed social y (a futuro) el marketplace de servicios deportivos.

Este documento reemplaza la v2 (orientada solo a matchmaking) tras una revisión conjunta que identificó la necesidad de un concepto unificador y una capa de interacción permanente entre actores.

---

## 1. Concepto central: Oportunidades

Todo lo que antes describíamos como "reto", "convocatoria", "refuerzo" o "jugador libre" es, en el fondo, **la misma idea: alguien publica que necesita algo, y otro responde**. Modelarlo como cuatro dominios separados multiplica tablas y pantallas sin necesidad. Se modela como **una sola entidad**:

```
Opportunity
├─ type: BUSCAR_RIVAL | BUSCAR_JUGADOR | BUSCAR_REFUERZO | BUSCAR_ARBITRO
│         BUSCAR_CANCHA | BUSCAR_PATROCINADOR | BUSCAR_ENTRENADOR | BUSCAR_TORNEO | ...
├─ creator (user_id y/o club_id, según el tipo)
├─ city / zone, level (categoría), window (fecha/rango horario)
├─ status: abierta | en_negociacion | cerrada | vencida | cancelada
└─ payload (JSON tipado según `type`: posiciones buscadas, cupos, cancha propuesta, etc.)

OpportunityResponse
├─ opportunity_id
├─ responder (user_id o club_id)
├─ message (texto corto estructurado)
└─ status: pendiente | aceptada | rechazada | contrapropuesta
```

**Al aceptarse, una Oportunidad genera un objeto concreto según su tipo** — no todo termina igual:

| Tipo de oportunidad | Al aceptarse, crea |
|---|---|
| `BUSCAR_RIVAL` | `FriendlyMatch` (partido amistoso confirmado) |
| `BUSCAR_JUGADOR` (plantilla permanente) | nueva fila en `club_players` |
| `BUSCAR_REFUERZO` (puntual) | asignación a un partido concreto, sin tocar la plantilla permanente |
| `BUSCAR_ARBITRO` / `BUSCAR_CANCHA` / `BUSCAR_PATROCINADOR` / `BUSCAR_ENTRENADOR` | contacto habilitado (dominio **Marketplace**, fase posterior — ver §6) |

Esto reduce el sistema a **2 tablas centrales** en vez de 4-6, y deja la puerta abierta a agregar nuevos tipos de oportunidad sin nuevas migraciones (solo nuevo valor de `type` + su payload).

---

## 2. Los seis dominios de FutGO Social

| Dominio | Qué resuelve | Estado de madurez en FutGO hoy |
|---|---|---|
| **1. Identidad** | Quién es cada usuario/club, su reputación, su carrera | Ya existe (Sesiones B, D, F) — se extiende |
| **2. Competencia** | Torneos, amistosos, retos, agenda | Torneos ya existe — amistosos/retos son nuevos |
| **3. Conexiones** | Oportunidades, matching, seguimiento, mensajería | Nuevo |
| **4. Comunidad** | Feed, publicaciones, reacciones, notificaciones sociales | Nuevo, fase posterior |
| **5. Marketplace** | Árbitros, entrenadores, canchas, patrocinadores, servicios | Nuevo, horizonte largo |
| **6. Inteligencia** | Recomendaciones, compatibilidad, analítica, asistente | Nuevo, crece con el dato disponible |

---

## 3. Dominio 1 — Identidad (extiende lo existente)

### 3.1 Ya construido (reutilizar, no rehacer)
- `User`, `Club`/`club_players`, credencial QR (`futgo_id`), perfil de carrera (`player_career_stats`), ranking (`futgo_rankings`), fair play (`fair_play_scores`), logros (`achievements`).

### 3.2 Nuevo: separar reputación en cuatro señales independientes
Observación clave del usuario, adoptada completa: **fair play y confiabilidad no son lo mismo**. Un equipo con Fair Play 70 / Confiabilidad 98 es más valioso para retar que uno con Fair Play 100 / Confiabilidad 40 (probablemente no se presenta).

| Señal | Qué mide | Fuente de datos |
|---|---|---|
| **Rendimiento** | Resultados deportivos (goles, victorias) | Ya existe vía `player_stats`/`standings` |
| **Ranking** | Posición relativa global/ciudad/categoría | Ya existe (`futgo_rankings`) |
| **Fair Play** | Disciplina dentro del partido (tarjetas) | Ya existe (`fair_play_scores`) |
| **Confiabilidad** (nuevo) | Asistencia, puntualidad, no-shows, cancelaciones tardías, velocidad de respuesta a invitaciones | Nuevo: `reliability_events` |

`reliability_events`: usuario o club, tipo (`no_show`, `cancelacion_tardia`, `respuesta_rapida`, `calificacion_positiva`, `calificacion_negativa`), referencia a la oportunidad/partido, fecha. Se agrega un score de confiabilidad (0-100) con la misma lógica de cache ya usada para fair play (`FairPlayService` es el patrón a clonar).

**Regla de penalización concreta** (no dejar la reputación como número cosmético): 2 no-shows en 30 días → la disponibilidad del usuario/club se **pausa automáticamente** y requiere reactivación manual.

### 3.3 Nuevo: sistema de niveles — elevado a requisito de Fase 1
No es una mejora opcional: sin nivel declarado, el matching de "Buscar rival" puede emparejar un equipo recreativo contra el campeón de la ciudad en el primer uso real, arruinando la experiencia de entrada. Niveles: `recreativo`, `intermedio`, `competitivo`, `elite_amateur`. El algoritmo de oportunidades debe usar el nivel como filtro/peso, no como dato decorativo.

### 3.4 Nuevo: perfil público con URL propia, por fases
El patrón ya existe (portal de torneo `/t/{slug}` sin auth). Extenderlo:
- **Fase 1:** ficha pública de jugador y de club (ya parcialmente existe para club; falta jugador).
- **Fase posterior:** ficha pública de cancha, árbitro, entrenador, patrocinador — solo cuando esas entidades existan como registros reales (no antes, para no publicar páginas vacías).

### 3.5 Nuevo: métricas sociales del perfil
Una vez existe el historial de amistosos (§4) y las stats de carrera (ya existentes), mostrar en el perfil: partidos totales, torneos, amistosos, equipos en los que jugó, convocatorias aceptadas, % de asistencia. Es casi gratis — solo una vista agregada sobre datos que ya existirán.

---

## 4. Dominio 2 — Competencia (extiende Torneos con Amistosos)

### 4.1 Casos de uso (sin cambios respecto a la v2, ya validados)
1. Marcar disponibilidad de club para amistoso (zona, fechas, nivel).
2. Buscar equipos disponibles (filtro ciudad/categoría/nivel/día).
3. Retar (`Opportunity` tipo `BUSCAR_RIVAL`) → aceptar/rechazar/contrapropuesta.
4. Al aceptar → `FriendlyMatch` confirmado (entidad propia, sin `tournament_id`).
5. Cancelar amistoso confirmado (con motivo) — genera `reliability_events` si es tardío.
6. **Doble confirmación de resultado**: si los capitanes cargan marcadores distintos, queda `en_disputa` visible solo para ambos, sin afectar reputación hasta resolverse — evita el clásico "cada uno dice que ganó".

### 4.2 Historial de amistosos integrado a Mi Carrera
"Mi Carrera" pasa a mostrar: Torneos · Amistosos · Convocatorias · Refuerzos — todo suma a la misma experiencia deportiva, en vez de quedar aislado.

### 4.3 Agenda deportiva (vista, no dominio nuevo de datos)
Una vista que agrega por fecha lo que **ya existe o existirá**: partidos de torneo, amistosos confirmados, convocatorias pendientes de respuesta. Barata de construir porque no inventa datos nuevos, solo los junta.

**Nota de alcance:** "Entrenamientos" (sesiones recurrentes programadas) y "Pago de inscripción" (cobros) **no se incluyen en la agenda de Fase 1** — son dominios de datos nuevos por derecho propio (programación recurrente, pasarela de pago) que merecen su propio diseño, no colarse silenciosamente como una fila más de la agenda.

---

## 5. Dominio 3 — Conexiones (el núcleo nuevo de esta versión)

### 5.1 Oportunidades (ver §1) — incluye los 4 casos de uso originales
- `BUSCAR_RIVAL`, `BUSCAR_JUGADOR` (plantilla permanente), `BUSCAR_REFUERZO` (puntual), y el jugador libre se modela como un caso particular: un **jugador publica una `Opportunity` tipo `BUSCAR_EQUIPO`**, simétrica a `BUSCAR_JUGADOR` — mismo mecanismo, dirección inversa. Esto evita una entidad `free_agent_profiles` separada: un perfil "disponible para que me convoquen" es solo una oportunidad propia, persistente, que cualquier capitán puede descubrir e invitar directamente sin esperar postulación.

### 5.2 Seguir (unifica "seguir" y "favoritos" del feedback original)
Una sola tabla polimórfica `follows` (seguidor → entidad seguida: club, jugador, torneo; cancha/organizador cuando existan como entidades). "Favoritos" no es un concepto aparte — es la misma acción aplicada a un club. Alimenta el Feed (§6) con notificaciones de actividad de lo que sigo.

### 5.3 "Jugué con vos" — derivado, no solicitado
En vez de un sistema de conexiones explícito tipo LinkedIn (solicitar → aceptar), que agrega complejidad de grafo social y moderación sin garantía de adopción en un contexto amateur, **se deriva automáticamente del historial de partidos**: si dos usuarios compartieron cancha (mismo `friendly_match` o `tournament_match`, en cualquier equipo), el sistema ya puede mostrar "jugaste 3 veces con Juan" sin que nadie tenga que pedir ni aceptar nada. Sobre ese dato derivado se habilitan acciones directas: retar, invitar, recomendar — sin necesidad de un estado de "conexión" intermedio.

**Decisión explícita:** el modelo de conexión formal (solicitud/aceptación mutua, estilo red profesional) se **defiere** a una fase posterior, solo si el dato derivado demuestra no ser suficiente en uso real.

### 5.4 Mensajería — diseñar el dominio ahora, construir la UI después
Se acepta la recomendación tal cual: diseñar el esquema desde ya para no migrar en caliente más adelante, pero lanzar el MVP solo con mensajes estructurados (los mismos que ya llevan las `OpportunityResponse`).

```
Conversation (opcionalmente vinculada a una Opportunity/FriendlyMatch)
ConversationParticipant (user_id o club_id, conversation_id)
Message (conversation_id, sender, body, created_at)
MessageAttachment (futuro: foto, no en MVP)
```

**Regla de privacidad explícita:** el contacto directo (teléfono/WhatsApp) **no se comparte automáticamente** al aceptar una oportunidad. La coordinación queda dentro de la plataforma (mensaje estructurado primero, mensajería libre cuando esté disponible); el intercambio de contacto es una acción explícita y posterior a la aceptación mutua.

---

## 6. Dominio 4 — Comunidad (fase posterior, separado del Feed)

Distinción importante que faltaba en la v2: **el Feed es el mecanismo de entrega; la Comunidad es el contenido que se entrega.**

### 6.1 Feed (Fase 1 — barato porque no inventa datos)
El Feed arranca con **eventos que el sistema ya genera o generará en Fase 1**, sin pedirle a nadie que "publique" nada:
- Nueva oportunidad relevante (por ciudad/nivel/lo que sigo).
- Resultado de partido (torneo o amistoso).
- MVP de un partido, logro desbloqueado.
- Oportunidad aceptada de alguien que sigo.

### 6.2 Comunidad — contenido generado por usuarios (Fase posterior, no MVP)
Publicaciones libres, fotos, videos, encuestas, comentarios, reacciones. **Se separa intencionalmente del Feed de Fase 1** porque es el ítem de mayor riesgo de moderación de todo este documento: contenido generado por usuarios implica spam, abuso, contenido inapropiado y un costo de soporte continuo que no existe hoy. No se construye hasta que:
1. El Feed de eventos del sistema demuestre que genera el hábito de entrar diariamente (la motivación original de este punto), y
2. Exista un mecanismo mínimo de reporte/moderación (ver §8) ya en producción.

---

## 7. Dominio 5 — Marketplace (horizonte largo, no MVP)

Visión válida y con potencial de monetización real, pero es **efectivamente otro negocio** dentro de FutGO: árbitros, fotógrafos, entrenadores, fisioterapeutas, uniformes, patrocinios, transporte, streaming — implica pagos, posibles comisiones, contratos y reviews de servicio. Se mantiene en el documento como **norte estratégico**, pero no entra en ninguna fase cercana. Cuando llegue su momento, los tipos `BUSCAR_ARBITRO`, `BUSCAR_CANCHA`, `BUSCAR_PATROCINADOR`, `BUSCAR_ENTRENADOR` de la entidad `Opportunity` (§1) ya estarán disponibles como punto de entrada — no hay que rediseñar el modelo de datos para empezar, solo construir el flujo de pago/contrato alrededor.

---

## 8. Dominio 6 — Inteligencia (crece con el dato disponible, no con expectativas)

### 8.1 Fase 1 — reglas simples, sin pretender ser "IA"
- **Equipos compatibles**: sugerencias por ciudad + nivel + actividad reciente, con reglas explícitas (no modelo de ML). Es exactamente lo que un asistente "inteligente" necesitaría como base de datos antes de poder razonar sobre nada.
- **Cancelación tardía detectada automáticamente**: comparar timestamp del cambio de estado vs. timestamp del partido, sin depender de que el rival la reporte manualmente.

### 8.2 Fase posterior — asistente conversacional
La idea de un asistente tipo "necesito un rival para el sábado → te propongo estos 3" es genuinamente diferenciadora, **pero solo tiene sentido una vez exista volumen de datos de uso real** (oportunidades, aceptaciones, resultados, confiabilidad). Construirlo antes sería una capa de marketing sin inteligencia real detrás — el riesgo es prometer algo que el dato no puede sostener todavía. Se documenta como visión de producto, explícitamente fuera de las fases tempranas.

---

## 9. Riesgos y decisiones que no se pueden posponer

- **Moderación de contenido y reportes**: aunque la Comunidad (§6.2) se posponga, las oportunidades y mensajes estructurados ya son texto libre desde la Fase 1. Se necesita, desde el día uno: longitud máxima, filtro básico de palabras prohibidas, y un botón de "reportar" con revisión manual de un admin. No es un sistema complejo, pero **no puede faltar desde el lanzamiento**.
- **Exposición de datos de contacto**: ver regla explícita en §5.4 — nunca automática, siempre posterior a aceptación mutua.
- **Identidad real en `BUSCAR_EQUIPO`/jugador libre**: a diferencia del contexto cerrado de un torneo con admin supervisando, acá hay contacto directo entre desconocidos. Se exige **usuario registrado** (no se permite participar como jugador "por verificar" sin cuenta) para publicar o responder oportunidades de este tipo.
- **Geolocalización**: dato sensible opcional, nunca obligatorio; la ciudad declarada manualmente es siempre la alternativa disponible.
- **Niveles de equipo mal declarados**: el sistema no puede verificar que un equipo "recreativo" autodeclarado realmente lo sea — mitigar con el historial de resultados (si un equipo "recreativo" le gana sistemáticamente a equipos "competitivos", el sistema puede sugerir recategorización, no forzarla).

---

## 10. Plan de fases (across los 6 dominios)

### Fase 1 — Matching básico + niveles + Feed de sistema
*Objetivo: validar que el problema de "conseguir rival/jugador" tiene demanda real, con el menor costo de moderación posible.*
- Entidad `Opportunity` + `OpportunityResponse` (tipos: `BUSCAR_RIVAL`, `BUSCAR_JUGADOR`, `BUSCAR_REFUERZO`, `BUSCAR_EQUIPO`).
- Niveles de club/jugador (`recreativo`/`intermedio`/`competitivo`/`elite_amateur`) como filtro obligatorio del matching.
- `FriendlyMatch` con doble confirmación de resultado.
- `reliability_events` + regla de pausa automática por no-shows.
- Seguir (`follows`) sobre club/jugador/torneo.
- Feed de eventos del sistema (sin contenido generado por usuario todavía).
- Historial de amistosos integrado a Mi Carrera + métricas sociales del perfil.
- Mensajes estructurados (sin mensajería libre).
- Reporte de contenido/usuario abusivo (mínimo viable).

### Fase 2 — Confianza visible + descubrimiento
- Score de confiabilidad visible en perfil (separado de fair play).
- Ficha pública de jugador (extiende el patrón de portal ya existente).
- "Jugué con vos" derivado del historial (sin solicitud/aceptación).
- Agenda deportiva (vista agregada).
- Mensajería libre dentro de conversaciones ya creadas por una oportunidad/partido.

### Fase 3 — Inteligencia por reglas + canchas
- Recomendación de equipos compatibles (reglas, no ML).
- Entidad `venues` (canchas) compartida, usada en la coordinación de amistosos.
- "Modo rápido" (oportunidad de vigencia corta, 24-48h, para necesidades de último momento).

### Horizonte largo (sin fecha, depende de tracción)
- Comunidad: publicaciones, fotos, videos, comentarios, reacciones — requiere moderación madura primero.
- Marketplace: árbitros, entrenadores, fisios, patrocinios, transporte, streaming — requiere pagos/contratos.
- Asistente conversacional de IA — requiere volumen de datos de uso real.
- Sistema de conexiones formal (solicitud/aceptación) — solo si "jugué con vos" derivado resulta insuficiente.
- Entrenamientos recurrentes y pago de inscripción dentro de la agenda.

---

## 11. Próximo paso concreto

1. Confirmar que la Fase 1 tal como queda delimitada arriba es el alcance correcto para el primer sprint (no los 6 dominios completos).
2. Diseñar el modelo de datos final de `Opportunity`/`OpportunityResponse`/`FriendlyMatch`/`reliability_events`/`follows` en migraciones concretas.
3. Definir las 4-5 pantallas mínimas de Fase 1: "Buscar oportunidad" (con filtro de nivel/ciudad), "Mis oportunidades", "Feed", "Mi Carrera" (extendida con amistosos), reutilizando los mismos patrones Blade/Alpine ya usados en Torneos.
