# Handoff: Landing Page de producción · FutGO

> Landing orientada a conversión (registro gratuito). Tono barrio/cancha, **sin prueba social** (aún no hay usuarios/torneos reales). Stack destino: **Laravel + Blade + Tailwind CSS + Alpine.js**.

---

## 0. Qué recrear

El archivo `reference/FutGO Landing.html` es la **referencia visual de alta fidelidad** de la homepage. La tarea es recrearla como vista Blade en el proyecto, usando los tokens del design system (`reference/futgo.css`) traducidos a Tailwind. No copiar el HTML literal: convertirlo a componentes Blade + utilidades Tailwind siguiendo las convenciones del codebase.

```
handoff-landing/
├── README.md                       ← este archivo (léelo primero)
├── PROMPT.md                       ← el prompt exacto para pegar en Claude Code
└── reference/
    ├── FutGO Landing.html          ← LA landing a replicar
    ├── futgo.css                   ← design system / fuente de verdad visual
    └── image-slot.js               ← solo referencia; en producción usar <input type=file> real
```

---

## 1. Estructura de la página (en orden)

1. **Nav sticky** — logo FutGO + enlaces de ancla (Organizadores / Jugadores / Comunidad) + botón `Crear cuenta gratis`.
2. **Hero** — mensaje central + CTA único + foto real (organizador/jugador) con dos tarjetas flotantes (resultado + reputación).
3. **Organizadores** — "Crea y gestiona tu torneo en minutos" + mockup de tabla de posiciones. 3 features: fixture automático, resultados/tabla en vivo, tarjetas para compartir.
4. **Jugadores** — "Tu carrera deportiva, digitalizada" + mockup de credencial con QR. 3 features: credencial QR, goles/MVP/historial, reputación/fair play.
5. **Comunidad** — "Encontrá rivales, armá amistosos, descubrí canchas cerca" — 3 tarjetas marcadas *Próximamente* (aspiracional pero honesto).
6. **Confianza/simplicidad** (sección oscura) — registro libre, sin cuotas ocultas, cualquiera puede organizar.
7. **CTA final** — "Únete gratis" + campo **ciudad opcional** (para segmentar comunicación futura).
8. **Footer** oscuro.

---

## 2. Restricciones de contenido (CRÍTICAS)

- **NO** mostrar cifras de usuarios/torneos/partidos ("+1000 usuarios", etc.).
- **NO** testimonios ni logos de clubes reales.
- El gancho es **la propuesta de valor y la facilidad de uso**, no la prueba social.
- **CTA único**: "Crear cuenta gratis" / "Únete gratis". Nada de "hablar con ventas" ni segundo CTA que compita.
- Registro inmediato, sin aprobaciones — recalcarlo en el copy.

---

## 3. Design tokens (modo claro cálido "barrio")

Los tokens base del sistema están en `reference/futgo.css`. **Esta landing usa el modo claro con una capa cálida** (definida en el `<style>` de la landing). Valores a portar a Tailwind:

| Rol | Valor |
|---|---|
| Fondo (crema cálido) | `#f7f4ec` |
| Superficie | `#ffffff` |
| Superficie alt | `#f3efe4` |
| Borde | `#e4ddca` |
| Texto | `#1c1a14` |
| Texto muted | `#5f5a4d` |
| **Verde marca** (grassier) | `#12a150` |
| Verde fuerte (hover) | `#0e7f40` |
| Verde tint | `#e4f5e8` |
| Tiza (chalk lines) | `#cfc6ad` |
| Sección oscura | `#101812` |

**Tipografía:** Archivo (display, eje Expanded ~110–118%, pesos 700–900), Inter (UI/cuerpo), JetBrains Mono (eyebrows/labels). Cargar desde Google Fonts (ver `<head>` del HTML).

---

## 4. Componentes Blade sugeridos

| Componente | Notas |
|---|---|
| `<x-landing.nav>` | Sticky, blur, CTA. En móvil colapsar enlaces a menú. |
| `<x-landing.hero>` | Grid 2 col → 1 col en móvil. Foto = `<x-image-upload>` o `<img>` real. |
| `<x-landing.section>` | Wrapper con eyebrow + h2 + lead; variantes `tint` y `dark`. |
| `<x-landing.feature-row>` | Ícono + título + descripción (lista de 3). |
| `<x-mockup.standings>` | Tabla de posiciones de ejemplo (datos ficticios de barrio, NO reales). |
| `<x-mockup.result-card>` | Tarjeta de resultado compartible (gradiente verde). |
| `<x-mockup.credential>` | Credencial de jugador con QR. Generar QR real con una librería (p. ej. `endroid/qr-code` en Laravel o `bacon/bacon-qr-code`), no el SVG hardcodeado. |
| `<x-landing.cta-final>` | Form con campo ciudad opcional → POST a la ruta de registro/lead. |

**Sobre las imágenes:** `image-slot.js` es solo para la maqueta (permite arrastrar fotos en el navegador). En producción, reemplazar por imágenes reales servidas desde `public/` o por un `<input type="file">` normal donde corresponda. NO incluir `image-slot.js` en producción.

---

## 5. Interacciones con Alpine.js

- **Nav móvil:** `x-data="{ open:false }"` para el menú hamburguesa.
- **Scroll suave** a las anclas: nativo con `scroll-behavior:smooth` (ya en el CSS).
- **Form de CTA:** validar ciudad opcional; enviar con `@submit.prevent` + `fetch`/Livewire a la ruta de registro. El botón lleva a `/register`.
- **Reveal on scroll** (opcional): `x-intersect` para animar la aparición de secciones.

---

## 6. Accesibilidad

- Un solo `<h1>` (el del hero); el resto `<h2>`/`<h4>` en orden.
- Contraste: verde `#12a150` sobre crema/blanco cumple AA para texto grande y UI; texto sobre verde = blanco.
- `:focus-visible` con anillo verde en todos los interactivos.
- Botones/links del CTA con área ≥ 44px.
- El campo ciudad con `<label>` asociado (visualmente oculto si hace falta).
- QR de credencial con `alt`/`aria-label` describiendo su función.

---

## 7. Checklist

- [ ] Portar tokens cálidos (§3) a `tailwind.config.js`.
- [ ] Cargar fuentes (Archivo/Inter/JetBrains Mono).
- [ ] Recrear las 8 secciones como componentes Blade.
- [ ] Datos de mockups = ficticios de barrio (NO reales, NO cifras de tracción).
- [ ] QR real vía librería, no SVG estático.
- [ ] Reemplazar image-slots por imágenes reales / `<input file>`.
- [ ] Form final → ruta de registro con campo ciudad opcional.
- [ ] Verificar restricciones de §2 (sin prueba social).
- [ ] QA responsive (900px es el breakpoint principal) y contraste AA.
