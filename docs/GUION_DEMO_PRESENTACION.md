# Guion de video promocional — FutGO multi-ciudad

Material bruto para grabar clips cortos dirigidos a Medellín, Bogotá, Cali,
Bucaramanga y, con foco especial, municipios de la Sabana de Bogotá (Chía,
Cajicá, Zipaquirá, Facatativá, Funza, Madrid, Mosquera, Soacha, Tocancipá,
Sopó). Todas las pantallas y datos referidos ya están cargados por el seeder
demo (`php artisan migrate:fresh --seed`) — credenciales completas en
[`DEMO_CREDENCIALES.md`](DEMO_CREDENCIALES.md).

Dos bloques, ~4-5 minutos de material bruto cada uno (se recorta en edición a
60-90 segundos por beat).

---

## Bloque 1 — Para administradores de torneo (prioridad)

**Hilo conductor:** la Liga Escolar Sabana Sub-13 2026, sede Chía
(`liga-escolar-sabana-sub13-2026`) — pública, en curso, con patrocinadores.
Es el escenario más cercano al cliente objetivo (colegios y escuelas de fútbol
de la Sabana con torneos activos hoy en WhatsApp/Excel).

### Gancho inicial (10-15s)
> "Organizar un torneo escolar a mano —en WhatsApp, en Excel, calculando
> posiciones a mitad de semana— es un caos que le quita tiempo al profe de
> educación física y le resta seriedad frente a los papás y los patrocinadores."

Pantalla: captura de un Excel/WhatsApp desordenado (o simplemente el problema
narrado en cámara), corte a FutGO.

### Recorrido (60-90s)

1. **Crear torneo** — login con `coordinadora.sabana@futgo.co`, mostrar
   brevemente el formulario de creación de torneo (`/admin/torneos/crear`):
   formato grupos + eliminatoria, categoría, ciudad Chía. (Puede mostrarse el
   formulario vacío o re-narrar sobre el torneo ya creado — a elección en
   edición.)
2. **Fixture automático** — dashboard del torneo
   (`/admin/torneos/{id}` de Liga Escolar Sabana), mostrar el fixture ya
   generado por grupos.
3. **Cargar resultado en vivo** — abrir uno de los 2 partidos de grupo aún
   `scheduled` (fecha próxima) y cargar un resultado desde la planilla
   (goles, tarjetas, MVP). Convocatoria ya pre-cargada — mostrar el
   confirmar/declinar.
4. **Posiciones actualizadas solas** — volver a la tabla de posiciones del
   grupo y mostrar que se recalculó sin intervención manual.
5. **Exportar** — botón de exportación PDF/CSV de posiciones o goleadores.
6. **Portal público compartible** — abrir `/t/liga-escolar-sabana-sub13-2026`
   sin sesión iniciada (o en pestaña de incógnito): posiciones, próximos
   partidos, patrocinadores visibles, botón de WhatsApp nativo para compartir
   con los papás.

### Beat adicional (opcional, 20-30s) — variedad de estados
Para reforzar que FutGO cubre *todo* el ciclo de un torneo, un paso rápido
por otras dos ciudades con `admin@futgo.co`:
- **Liga Barrial Bogotá 2026** (`/t/liga-barrial-bogota-2026`) — torneo
  **finalizado**: posiciones cerradas, goleador, campeón (Chapinero FC,
  invicto), tarjeta compartible de campeón.
- **Torneo Empresarial Café 2026** (Bucaramanga, `open`) — inscripciones
  abiertas, cupos parcialmente llenos: el flujo "antes de arrancar" para un
  admin nuevo evaluando la plataforma.

### Cierre (10-15s)
> "Fixture automático, resultados en vivo, posiciones sin errores de cálculo,
> y un portal que podés mandar por WhatsApp el mismo día. Lo que antes tomaba
> horas de Excel, ahora es minutos — y se ve profesional frente a los papás y
> los patrocinadores."

---

## Bloque 2 — Para usuarios finales / jugadores

**Hilo conductor:** `jugador.estrella@futgo.co` (Andrés Suárez, Halcones FC) —
la cuenta con más historial cargado: hat-trick y MVP reciente, ranking,
credencial, amistosos y "jugué con vos".

### Gancho (10-15s)
> "El torneo termina el domingo. Pero el fútbol no."

Pantalla: cierre de un partido en la app (por ejemplo, el resultado 3-1 de
Halcones FC en cuartos de la Copa Élite Santander), corte al perfil del
jugador.

### Recorrido (60-90s)

1. **Mi Carrera** — login con `jugador.estrella@futgo.co`, abrir "Mi Carrera":
   acumulado histórico (goles, MVPs, logro desbloqueado), su hat-trick reciente
   en la Copa Élite Santander 2026.
2. **Credencial QR** — mostrar la credencial (`FG-W8RQ3W`), explicar que
   identifica al jugador sin exponer datos sensibles (útil para árbitros/
   organizadores validando en cancha — puede enlazarse con la cuenta
   `arbitro@futgo.co` validando esa misma credencial).
3. **Ranking** — `/torneos/ranking`, mostrar su posición en el ranking
   general de la plataforma.
4. **Buscar rival / oportunidad** — `/oportunidades`, mostrar la oportunidad
   ⚡ modo rápido de Colegio San Rafael Chía (BUSCAR_RIVAL urgente) como
   ejemplo de lo rápido que se arma un partido, y la oportunidad BUSCAR_EQUIPO
   abierta de `libre@futgo.co` como ejemplo del otro lado (jugador buscando
   equipo).
5. **Amistoso confirmado** — "Mis amistosos": el amistoso jugado Halcones FC
   vs Tigres del Norte (2-1) con la conversación post-partido ("gran partido,
   espero la revancha").
6. **"Jugué con vos"** — desde el perfil de Andrés Suárez, mostrar que sigue
   mutuamente al capitán de Tigres del Norte tras haber compartido cancha —
   acciones directas de "retar a un amistoso" / "invitar a mi equipo".
7. **Agenda de la semana** — `/agenda`: el próximo cuarto de final pendiente
   de la Copa Élite con convocatoria por responder.

### Beat adicional (opcional, 15-20s) — resolución de disputa
Con `admin@futgo.co`, `/admin/amistosos`: el amistoso en disputa **Poblado
United vs Belén FC** (cada capitán reportó un marcador distinto) y cómo el
admin fija el resultado oficial — refuerza que el sistema tiene reglas claras,
no depende de buena fe únicamente.

### Cierre (10-15s)
> "Tu reputación, tu historial y tu credencial te siguen aunque cambies de
> equipo o el torneo se acabe. En FutGO seguís jugando."

---

## Notas de rodaje — pantalla y credencial por beat

| Beat | Pantalla | Credencial |
|---|---|---|
| Crear torneo / fixture | `/admin/torneos/crear` → dashboard Liga Escolar Sabana | `coordinadora.sabana@futgo.co` |
| Resultado en vivo + posiciones | Planilla de resultado del partido pendiente de grupo | `coordinadora.sabana@futgo.co` |
| Exportar | Botón exportar PDF/CSV en dashboard del torneo | `coordinadora.sabana@futgo.co` |
| Portal público | `/t/liga-escolar-sabana-sub13-2026` (sin sesión) | — |
| Torneo finalizado (posiciones cerradas) | `/t/liga-barrial-bogota-2026` | `admin@futgo.co` o sin sesión |
| Torneo recién abierto | `/admin/torneos/{id}` de Torneo Empresarial Café | `admin@futgo.co` |
| Mi Carrera + credencial + ranking | `/mi-carrera`, `/torneos/ranking` | `jugador.estrella@futgo.co` |
| Oportunidades (⚡ y BUSCAR_EQUIPO) | `/oportunidades` | `jugador.estrella@futgo.co` o `libre@futgo.co` |
| Amistoso jugado + conversación | "Mis amistosos" | `jugador.estrella@futgo.co` o `capitan.halcones@futgo.co` |
| "Jugué con vos" | Perfil público de Andrés Suárez (`/j/FG-W8RQ3W`) | `jugador.estrella@futgo.co` |
| Agenda | `/agenda` | `jugador.estrella@futgo.co` |
| Validación de credencial QR | Validador de credenciales de un partido de la Copa Élite | `arbitro@futgo.co` |
| Amistoso en disputa | `/admin/amistosos` | `admin@futgo.co` |
