# Guion de video promocional — FutGO multi-ciudad

Material bruto para grabar clips cortos dirigidos a Medellín, Bogotá, Cali,
Bucaramanga y, con foco especial, municipios de la Sabana de Bogotá (Chía,
Cajicá, Zipaquirá, Facatativá, Funza, Madrid, Mosquera, Soacha, Tocancipá,
Sopó). Todas las pantallas y datos referidos ya están cargados por el seeder
demo (`php artisan migrate:fresh --seed`) — credenciales completas en
[`DEMO_CREDENCIALES.md`](DEMO_CREDENCIALES.md).

Dos bloques, ~4-5 minutos de material bruto cada uno (se recorta en edición a
60-90 segundos por beat).

## Instrucciones para quien graba (leer antes de empezar)

Este guion está escrito para una persona que **no conoce la aplicación** y
va a grabar leyendo en cámara o en voz en off. Cada paso trae dos partes:

- **Qué hacer en pantalla** — la acción concreta a realizar (dónde hacer
  clic, qué credencial usar, qué esperar que aparezca).
- **Qué decir** (bloque de cita `>`) — el texto para leer tal cual, en
  español neutro, tono cercano pero profesional. Podés ajustar palabras
  sueltas para que suene natural en tu voz, pero mantené el sentido y los
  números/nombres propios exactos (son datos reales del seeder demo).

Reglas generales:
1. Grabá cada paso numerado como un clip separado (no todo de corrido) —
   así en edición se puede recortar y reordenar sin problema.
2. Esperá 1-2 segundos en silencio al inicio y al final de cada clip
   (para tener margen de corte).
3. Si algo en pantalla no carga o se ve distinto a lo descrito, avisá antes
   de re-grabar — no hace falta improvisar, puede ser un dato del seeder
   que cambió.
4. Todas las credenciales (usuario/contraseña) están en
   [`DEMO_CREDENCIALES.md`](DEMO_CREDENCIALES.md).
5. Antes de grabar cada bloque, cerrar sesión y volver a entrar con la
   credencial indicada en ese paso (varios pasos cambian de usuario).

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

1. **Crear torneo**
   - *Qué hacer:* iniciar sesión con `coordinadora.sabana@futgo.co`, entrar
     al formulario de creación de torneo (`/admin/torneos/crear`) y mostrar
     brevemente los campos: formato grupos + eliminatoria, categoría, ciudad
     Chía. (Podés mostrar el formulario vacío o re-narrar sobre el torneo ya
     creado — lo que sea más natural al grabar.)
   - *Qué decir:*
     > "Crear un torneo en FutGO toma un par de minutos: le pones nombre,
     > elegís el formato —grupos, eliminatoria directa o los dos combinados—,
     > la categoría y la ciudad. Nada de plantillas de Excel para armar
     > desde cero."

2. **Fixture automático**
   - *Qué hacer:* abrir el dashboard del torneo
     (`/admin/torneos/{id}` de Liga Escolar Sabana Sub-13) y mostrar el
     fixture ya generado por grupos.
   - *Qué decir:*
     > "Con los equipos inscritos, la plataforma arma el fixture sola: quién
     > juega contra quién, en qué grupo, sin que el organizador tenga que
     > cuadrar cruces a mano."

3. **Cargar resultado en vivo**
   - *Qué hacer:* abrir uno de los 2 partidos de grupo aún en estado
     `scheduled` (fecha próxima) y cargar un resultado desde la planilla
     (goles, tarjetas, MVP). La convocatoria ya está pre-cargada — mostrar
     el confirmar/declinar de un jugador.
   - *Qué decir:*
     > "El día del partido, cargás el resultado desde el celular o el
     > computador: goles, tarjetas, quién fue la figura del partido. Los
     > jugadores convocados confirman o rechazan su asistencia directamente
     > en la app."

4. **Posiciones actualizadas solas**
   - *Qué hacer:* volver a la tabla de posiciones del grupo y mostrar que
     se recalculó sin intervención manual apenas se guardó el resultado.
   - *Qué decir:*
     > "Apenas se guarda el resultado, la tabla de posiciones se actualiza
     > sola —puntos, diferencia de gol, criterios de desempate— sin que
     > nadie tenga que recalcular nada en una hoja de cálculo."

5. **Exportar**
   - *Qué hacer:* mostrar el botón de exportación PDF/CSV de posiciones o
     goleadores en el dashboard del torneo.
   - *Qué decir:*
     > "Y si necesitás compartir esa información por fuera de la app, la
     > exportás en PDF o Excel con un clic: posiciones, goleadores, todo
     > listo para imprimir o enviar."

6. **Portal público compartible**
   - *Qué hacer:* abrir `/t/liga-escolar-sabana-sub13-2026` sin sesión
     iniciada (o en pestaña de incógnito): mostrar posiciones, próximos
     partidos, patrocinadores visibles y el botón de WhatsApp nativo.
   - *Qué decir:*
     > "Y todo esto tiene una vitrina pública: un link que le mandás por
     > WhatsApp a los papás, sin que necesiten cuenta ni contraseña, donde
     > ven posiciones, próximos partidos y hasta los patrocinadores del
     > torneo. Se ve profesional desde el primer día."

### Beat adicional (opcional, 20-30s) — variedad de estados
Para reforzar que FutGO cubre *todo* el ciclo de un torneo, un paso rápido
por otras dos ciudades con `admin@futgo.co`.

- *Qué hacer:* abrir `/t/liga-barrial-bogota-2026` — torneo **finalizado**:
  mostrar posiciones cerradas, goleador, campeón (Chapinero FC, invicto) y
  la tarjeta compartible de campeón.
- *Qué decir:*
  > "Cuando el torneo termina, queda todo el registro: posiciones finales,
  > goleador, campeón, y hasta una tarjeta lista para compartir en redes."

- *Qué hacer:* abrir el dashboard de Torneo Empresarial Café 2026
  (Bucaramanga, estado `open`) — inscripciones abiertas, cupos parcialmente
  llenos.
- *Qué decir:*
  > "Y si el torneo todavía no arrancó, así se ve mientras se están
  > inscribiendo los equipos: cupos disponibles, todo controlado antes del
  > primer partido."

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

1. **Mi Carrera**
   - *Qué hacer:* iniciar sesión con `jugador.estrella@futgo.co`, abrir
     "Mi Carrera": mostrar el acumulado histórico (goles, MVPs, logro
     desbloqueado) y el hat-trick reciente en la Copa Élite Santander 2026.
   - *Qué decir:*
     > "Este es el perfil de un jugador: todo su historial en un solo
     > lugar. Goles, premios a mejor jugador, logros desbloqueados — y no
     > se pierde aunque cambie de equipo o de torneo."

2. **Credencial QR**
   - *Qué hacer:* mostrar la credencial del jugador (`FG-W8RQ3W`).
   - *Qué decir:*
     > "Cada jugador tiene una credencial digital con código QR. La usan los
     > árbitros o los organizadores para validar su identidad en la cancha,
     > sin exponer datos personales como el documento o el teléfono."
   - *(Opcional)* enlazar con la cuenta `arbitro@futgo.co` validando esa
     misma credencial.
     > "Del otro lado, el árbitro escanea el código y confirma en segundos
     > que ese jugador es quien dice ser."

3. **Ranking**
   - *Qué hacer:* abrir `/torneos/ranking` y mostrar su posición en el
     ranking general de la plataforma.
   - *Qué decir:*
     > "Y hay un ranking general de la plataforma: goles, asistencias, fair
     > play, todo suma para ubicarte frente al resto de los jugadores."

4. **Buscar rival / oportunidad**
   - *Qué hacer:* abrir `/oportunidades` y mostrar la oportunidad ⚡ modo
     rápido de Colegio San Rafael Chía (BUSCAR_RIVAL urgente), y luego la
     oportunidad BUSCAR_EQUIPO abierta de `libre@futgo.co`.
   - *Qué decir:*
     > "Fuera del torneo, acá es donde se arman los partidos amistosos.
     > Un equipo publica que necesita rival para el fin de semana —esta
     > está marcada como urgente— y en minutos alguien responde. Y también
     > funciona al revés: un jugador sin equipo publica que está buscando
     > uno."

5. **Amistoso confirmado**
   - *Qué hacer:* abrir "Mis amistosos" y mostrar el amistoso jugado
     Halcones FC vs Tigres del Norte (2-1), con la conversación post-partido
     ("gran partido, espero la revancha").
   - *Qué decir:*
     > "Una vez se juega el amistoso, el resultado queda registrado y los
     > capitanes pueden seguir hablando desde el chat de la app — acá,
     > coordinando la revancha."

6. **"Jugué con vos"**
   - *Qué hacer:* desde el perfil de Andrés Suárez, mostrar que sigue
     mutuamente al capitán de Tigres del Norte tras haber compartido
     cancha, y las acciones directas "retar a un amistoso" / "invitar a mi
     equipo".
   - *Qué decir:*
     > "La app reconoce con quién jugaste, aunque haya sido en equipos
     > distintos, y te deja seguirlo, retarlo a un revancha o invitarlo a tu
     > equipo con un clic."

7. **Agenda de la semana**
   - *Qué hacer:* abrir `/agenda` y mostrar el próximo cuarto de final
     pendiente de la Copa Élite con convocatoria por responder.
   - *Qué decir:*
     > "Y toda tu semana deportiva en un solo calendario: partidos de
     > torneo, amistosos confirmados, convocatorias por responder. No hay
     > que andar revisando grupos de WhatsApp para saber qué sigue."

### Beat adicional (opcional, 15-20s) — resolución de disputa
- *Qué hacer:* con `admin@futgo.co`, abrir `/admin/amistosos` y mostrar el
  amistoso en disputa **Poblado United vs Belén FC** (cada capitán reportó
  un marcador distinto) y cómo el admin fija el resultado oficial.
- *Qué decir:*
  > "¿Y si los dos capitanes reportan un marcador distinto? Queda marcado
  > como 'en disputa' hasta que un administrador revisa y fija el resultado
  > oficial. El sistema no depende solo de la buena fe."

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
