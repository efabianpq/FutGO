# 📱 Guion FutGO — versión corta 90 s (redes)

> Corte rápido para Reels / TikTok / Shorts. **Vertical 9:16**, ritmo alto, cortes secos.
> Pensado para verse **con audio apagado**: cada beat lleva **texto en pantalla** además
> de la voz en off. Datos del seeder demo (`docs/INFORME_SEEDER_DEMO.md`); accesos del
> nav v3 (`CLAUDE.md` §14). Versión larga: `docs/GUION_DEMO_VIDEO.md`.

**Pre-grabación:** `php artisan migrate:fresh --seed` · contraseña `password` · ciudad de
referencia Bucaramanga. Grabá las pantallas en mobile (o ventana angosta) para que el nav
se vea en modo móvil/limpio.

**Música:** beat enérgico, corte fuerte en el segundo ~45 (cambio de bloque admin → jugador).

---

## Guion por beats (timecodes aproximados)

| t | Pantalla / b-roll | Texto en pantalla | Voz en off |
|---|---|---|---|
| **0:00–0:04** | Caos: capturas de chats de WhatsApp, planilla en papel, Excel. Glitch → logo FutGO. | **"¿Organizar tu torneo así?"** | "Se acabó el caos del fútbol amateur." |
| **0:04–0:08** | Portal público `/torneos`: tarjetas con barra de progreso. | **FutGO** | "FutGO lo ordena todo." |
| **0:08–0:12** | *(separador)* Texto a pantalla completa sobre cancha. | **1 · ORGANIZÁS** | — |
| **0:12–0:20** | `/admin/torneos`: los 5 torneos (draft→open→en juego→finalizado). Elegir formato → **fixture se arma solo**. | **Fixture automático** | "Creás el torneo, elegís el formato y el fixture se arma solo." |
| **0:20–0:30** | Cargar un resultado: goles, tarjetas, MVP → **la tabla de posiciones se recalcula sola**. | **Resultados → tabla en vivo** | "Cargás el marcador una vez y las posiciones, estadísticas y desempates se actualizan automáticamente." |
| **0:30–0:38** | Portal público: campeón, goleadores. Botón **WhatsApp** + tarjeta **PNG**. | **Compartible** | "Y todo queda en un portal público, listo para compartir." |
| **0:38–0:44** | Árbitro escanea **credencial QR** → ✅ habilitado. | **Identidad verificada** | "Con credencial QR: cero suplantados." |
| **0:44–0:48** | *(separador, corte de música)* Texto a pantalla completa. | **2 · JUGÁS** | — |
| **0:48–0:56** | **Mi Carrera**: contador subiendo a 19 goles, 3 MVP, logros, "Jugué con". | **Tu hoja de vida** | "Cada partido suma a tu carrera: goles, MVP, logros, historial." |
| **0:56–1:02** | **Inicio** 🏠: recordatorio de convocatoria → tap **Confirmar** inline. | **Tu semana, en orden** | "Tu inicio te dice qué se viene y confirmás al toque." |
| **1:02–1:10** | **Jugar → Oportunidades**: publicar BUSCAR_RIVAL → aceptar → **amistoso + chat** aparecen solos. | **Rival en 2 toques** | "¿Falta rival, jugador o refuerzo? Lo publicás, la comunidad responde, y se arma el partido con chat incluido." |
| **1:10–1:16** | **Modo rápido ⚡** + badge "Urgente". | **⚡ Rival para mañana** | "¿Urgente? Modo rápido." |
| **1:16–1:22** | Amistoso **Halcones 2–1 Tigres**: doble confirmación ✔✔ + score de confiabilidad. | **Confianza real** | "Los dos confirman el resultado. Quien no se presenta, pierde reputación." |
| **1:22–1:26** | Header **🔍 Buscar** + **🔔 Feed** + **💬 Mensajes** con badges. | **Tu comunidad** | "Buscá a cualquiera, seguí lo tuyo, chateá con contexto." |
| **1:26–1:30** | Dashboard Inicio → logo FutGO + CTA. | **FutGO · Donde crece el fútbol amateur** + `futgo.test` / @usuario | "FutGO. Donde crece el fútbol amateur." |

---

## Notas de edición

- **Hook (0–4 s):** es lo único que decide si lo ven. Que el "antes vs después" sea brutal.
- **Dos bloques claros:** ORGANIZÁS (0:08–0:44) y JUGÁS (0:44–1:26), separados por el corte de música.
- **Texto grande y corto** (máx. 4 palabras por placa); subtítulos quemados en toda la voz en off.
- Acelerá los formularios con **timelapse/jump-cut**: nunca mostrar tipeo completo.
- Cerrá siempre con **CTA visible** (usuario/URL) los últimos 3–4 s.
- Si necesitás 60 s: sacá los beats 0:38–0:44 (credencial) y 1:16–1:22 (amistoso).
