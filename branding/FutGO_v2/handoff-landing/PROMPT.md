# Prompt para Claude Code

Copiá y pegá esto en Claude Code (con la carpeta `handoff-landing/` dentro de tu repo):

---

Quiero que implementes la **homepage / landing page de FutGO** en mi proyecto Laravel + Blade + Tailwind + Alpine, usando el paquete de diseño de la carpeta `handoff-landing/`.

**Leé primero, en este orden:**
1. `handoff-landing/README.md` — estructura de la página, tokens, componentes, restricciones y accesibilidad.
2. `handoff-landing/reference/FutGO Landing.html` — **esta es la landing exacta que quiero replicar.**
3. `handoff-landing/reference/futgo.css` — la fuente de verdad visual (colores, tipografía, componentes).

**Tarea:**
- Recreá la landing como vista Blade (la ruta `/` o `welcome.blade.php` según mi estructura), respetando exactamente colores, tipografía, espaciados, mockups y copy del HTML de referencia.
- Traducí los estilos a Tailwind: portá los tokens cálidos del README (§3) a `tailwind.config.js` y cargá las fuentes Archivo + Inter + JetBrains Mono.
- Estructurá las 8 secciones como **componentes Blade reutilizables** (nav, hero, secciones, feature-rows, mockups, CTA final, footer) — ver el mapa en el README §4.
- **Respetá las restricciones de contenido (README §2), son críticas:** sin cifras de usuarios/torneos, sin testimonios, sin logos reales. CTA único "Crear cuenta gratis". El CTA final incluye un campo de ciudad opcional que debe postear a mi ruta de registro/lead.
- El **QR** de la credencial generalo con una librería real (ej. `endroid/qr-code`), no uses el SVG estático de la maqueta.
- **No** copies `image-slot.js` a producción: reemplazá los slots por imágenes reales servidas desde `public/` o un `<input type="file">` donde corresponda. Dejá los `<img>` apuntando a placeholders locales que yo reemplazaré por fotos reales de fútbol amateur.
- Implementá el menú móvil y el submit del form con Alpine.js.
- Cuidá accesibilidad (README §6): un solo `<h1>`, contraste AA, `:focus-visible`, labels.

**Importante:** no copies el HTML literal a Blade — traducilo a Tailwind + componentes siguiendo las convenciones de mi codebase. No modifiques nada fuera de lo necesario para la landing. Cuando termines, decime qué archivos creaste/modificaste y cómo registrar la ruta.

---

## Consejos

- **Empezá por la landing sola.** Cuando quede, le pedís el resto (dashboard, UI kit) con los otros paquetes de handoff.
- **Confirmá que la carpeta `handoff-landing/` esté en el repo** antes de correr el prompt (o ajustá las rutas).
- **Las fotos las ponés vos**: la maqueta usa slots arrastrables solo para diseño; en producción van imágenes reales tuyas de cancha/comunidad.
