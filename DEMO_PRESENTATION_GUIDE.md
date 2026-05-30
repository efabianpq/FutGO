# DEMO_PRESENTATION_GUIDE — FutGO v2
## Recorrido de demostración para interesados · 15–20 minutos

---

## Usuarios disponibles (seeder `DemoTournamentSeeder`)

| Rol | Email | Contraseña | Para mostrar |
|-----|-------|------------|--------------|
| Admin global | `admin@soypachonmundial.com` | `Admin2026!` | Panel admin completo |
| Admin torneo | `admin.torneo@demo.futgo.com` | `Demo2026!` | Gestión de torneo end-to-end |
| Capitán (Leones) | `ldn.capitan@demo.futgo.com` | `Demo2026!` | Panel Capitán, inscripción, equipo |
| Jugador (Leones) | `ldn.j1@demo.futgo.com` | `Demo2026!` | Mi Actividad, estadísticas |

**Torneo demo listo:** *Copa FutGO Demo 2026* — 2 grupos × 4 equipos, 6 partidos jugados con standings y estadísticas calculadas, eliminatorias generadas (semifinales pobladas, final y tercer puesto esperando).

---

## Recorrido sugerido

### BLOQUE 1 — Creación y configuración del torneo (2 min)
**Usuario:** `admin.torneo@demo.futgo.com`
**Pantalla:** `/admin/torneos/crear`

1. Mostrar el formulario de creación: nombre, deporte, formato (`groups_and_knockout`), grupos, puntos por victoria/empate/derrota, criterios de desempate, sede, categoría, fechas.
2. **Mensaje de valor:** *"El organizador configura en un solo lugar todas las reglas del torneo — desde el sistema de puntos hasta el orden de desempate — que luego el sistema aplica automáticamente."*

---

### BLOQUE 2 — Mis Torneos (entrada del módulo) (1 min)
**Usuario:** `admin.torneo@demo.futgo.com`
**Pantalla:** `/torneos`

1. Mostrar la tarjeta de *Copa FutGO Demo 2026*: estado, equipos aprobados, fase activa, próximo partido.
2. Señalar los accesos directos por rol: **Gestión · Equipos · Fixture · Standings · Estadísticas · Cronograma**.
3. **Mensaje de valor:** *"Cada usuario ve los accesos que realmente puede usar según su rol — sin menús irrelevantes."*

---

### BLOQUE 3 — Gestión de equipos (2 min)
**Usuario:** `admin.torneo@demo.futgo.com`
**Pantalla:** `/admin/torneos/{id}/equipos`

1. Mostrar la lista de 8 equipos con sus estados (todos aprobados).
2. Hacer clic en un equipo: ver capitán, roster de 18 jugadores.
3. **(Opcional en vivo)** Cambiar a `ldn.capitan@demo.futgo.com` → `/capitan` → mostrar el **Panel Capitán**: plantilla, alertas de seguimiento, próximos partidos del equipo.
4. **Mensaje de valor:** *"El capitán tiene su propio centro de control: ve sus jugadores, aprueba solicitudes de fichaje y sigue el rendimiento del equipo."*

---

### BLOQUE 4 — Cronograma y fixture (1 min)
**Usuario:** `admin.torneo@demo.futgo.com`
**Pantalla:** `/torneos/{id}/cronograma`

1. Mostrar el cronograma con filtros (por fase, por estado, por equipo).
2. Señalar los 6 partidos jugados y los 6 programados.
3. Hacer clic en un equipo del cronograma → vista de partido a partido del equipo.
4. **Mensaje de valor:** *"Jugadores y capitanes consultan el fixture de su equipo sin necesidad de ser admin."*

---

### BLOQUE 5 — Planilla del partido (documento maestro) (4 min)
**Usuario:** `admin.torneo@demo.futgo.com`
**Pantalla:** `/admin/torneos/{id}/partidos` → clic en **Planilla** de un partido programado

1. **Mostrar la autogeneración**: la convocatoria se pre-carga con todos los jugadores activos marcados como titulares. El admin solo destilda ausentes.
2. **Recorrer las secciones:**
   - Datos del partido (contexto: torneo, fase, sede, fecha)
   - Cuerpo arbitral y **Observaciones arbitrales**
   - **Marcador final** + marcador por periodos (1er tiempo, prórroga, penales)
   - **Convocatoria por equipo**: asistencia, titular/suplente, minuto entrada/salida, capitán marcado con ©
   - **Eventos por jugador**: goles, asistencias, amarillas, rojas con minuto
   - **Cuerpo técnico + faltas acumulativas + tiempos muertos + firma del capitán**
3. Guardar el resultado.
4. **Mostrar el efecto automático:** standings actualizados → estadísticas recalculadas.
5. Clic en **Descargar PDF** → abrir la planilla oficial A4.
6. **Mensaje de valor:** *"La planilla es el documento maestro: una sola acción llena el acta, actualiza la tabla de posiciones y las estadísticas de todos los jugadores. El PDF replica la planilla oficial física para uso fuera de línea."*

---

### BLOQUE 6 — Tabla de posiciones (1 min)
**Usuario:** `admin.torneo@demo.futgo.com`
**Pantalla:** `/admin/torneos/{id}/standings`

1. Mostrar la tabla con PJ/PG/PE/PP/GF/GC/DG/PTS.
2. Resaltar los clasificados en verde.
3. Mostrar el panel de configuración del sistema de puntos (3-1-0) y el orden de desempate.
4. **Mensaje de valor:** *"Los desempates (diferencia de goles, goles a favor, cara a cara) se aplican automáticamente según la configuración del torneo."*

---

### BLOQUE 7 — Estadísticas de jugadores (1 min)
**Usuario:** cualquiera con acceso al torneo
**Pantalla:** `/torneos/{id}/estadisticas`

1. Mostrar el ranking de goleadores filtrable por equipo.
2. Hacer clic en un jugador → perfil individual: resumen (PJ, goles, asistencias, tarjetas, minutos, victorias) + historial partido a partido.
3. **Mensaje de valor:** *"Las estadísticas se calculan automáticamente desde la planilla — sin carga manual doble."*

---

### BLOQUE 8 — Hub del torneo (1 min)
**Usuario:** `ldn.capitan@demo.futgo.com` (rol capitán)
**Pantalla:** `/torneos/{slug}` (hub público del torneo)

1. Mostrar: próximos partidos, últimos resultados, tabla de posiciones resumida, top goleadores, equipos participantes y **Bases del torneo** (formato, sede, inscripción, premio, reglamento).
2. **Mensaje de valor:** *"Jugadores y capitanes tienen una vista de solo lectura del torneo — sin acceso a la gestión."*

---

### BLOQUE 9 — Portal del Jugador (1 min)
**Usuario:** `ldn.j1@demo.futgo.com`
**Pantalla:** `/mi-actividad`

1. Mostrar: mis torneos activos/finalizados, próximos partidos, últimos resultados, **mis estadísticas** (goles/asist/amarillas/rojas/minutos agregados) y **mis sanciones** (suspensiones vigentes + historial disciplinario).
2. **Mensaje de valor:** *"El jugador tiene visibilidad total de su historial sin necesidad de pedirle información al organizador."*

---

### BLOQUE 10 — Progresión automática de eliminatoria (2 min) ⭐ Momento WOW
**Usuario:** `admin.torneo@demo.futgo.com`
**Pantalla:** `/admin/torneos/{id}/partidos` → fase Semifinal

1. Abrir la planilla de la **Semifinal 1** → cargar resultado (ej. 2-1).
2. Abrir la planilla de la **Semifinal 2** → cargar resultado (ej. 1-0).
3. Ir al listado de partidos: mostrar que **la Final se pobló automáticamente** con los dos ganadores.
4. Mostrar que el **Tercer Puesto** también se generó con los perdedores.
5. *(Opcional)* Cargar el resultado de la Final → mostrar que el torneo pasa a `Finalizado`.
6. **Mensaje de valor:** *"No hay configuración manual del bracket: el sistema asigna los ganadores y genera el partido de tercer puesto en tiempo real. Si hay empate, los penales definen el ganador."*

---

### BLOQUE 11 — Cierre de fase de grupos (2 min) [alternativo al bloque 10 si la demo parte desde cero]
**Usuario:** `admin.torneo@demo.futgo.com`
**Pantalla:** Dashboard del torneo → "Cerrar fase y generar eliminatoria"

1. Mostrar el resumen: partidos finalizados/pendientes y clasificados proyectados por grupo.
2. Ejecutar el cierre.
3. Mostrar las semifinales con los cruces generados (A1 vs B2 / B1 vs A2).
4. **Mensaje de valor:** *"El cruce se respeta automáticamente: primero de un grupo contra segundo del otro."*

---

## Duración total: ~18 minutos

| Bloque | Tema | Tiempo |
|--------|------|--------|
| 1 | Creación del torneo | 2 min |
| 2 | Mis Torneos | 1 min |
| 3 | Equipos + Panel Capitán | 2 min |
| 4 | Cronograma | 1 min |
| 5 | Planilla del partido (WOW) | 4 min |
| 6 | Standings | 1 min |
| 7 | Estadísticas | 1 min |
| 8 | Hub del torneo | 1 min |
| 9 | Portal del Jugador | 1 min |
| 10 | Progresión eliminatoria (WOW) | 2 min |
| Buffer / preguntas | | 2 min |

---

## Tips para la presentación

- **Arrancar con el torneo ya sembrado** (`php artisan db:seed --class=DemoTournamentSeeder`) para tener standings y estadísticas visibles desde el primer momento.
- **El momento WOW del bloque 10** (bracket automático) es el cierre ideal: cargar dos resultados de semifinal y que la final aparezca sola sorprende a la audiencia.
- Si hay preguntas sobre **inscripción de jugadores**, mostrar el flujo en `ldn.capitan → Panel Capitán → buscar por email`.
- La **exportación PDF** del acta tiene el mayor impacto visual si se proyecta en pantalla: parece la planilla física oficial.
- URL local: `http://futgo.test:8080` · alternativa: `php artisan serve --port=8001`.
