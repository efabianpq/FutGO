# FutGO · Sistema de Marca & UI — Handoff técnico

> **El sistema operativo del fútbol amateur.**
> Identidad, design system (claro + oscuro), UI kit y landing page para FutGO — ecosistema B2B2C de gestión de torneos, equipos, estadísticas y reservas.
> Stack destino: **Laravel + Tailwind CSS + Alpine.js**.

---

## 0. Qué es esto

Los archivos en `reference/` son **referencias de diseño de alta fidelidad** (HTML/CSS reales), no código de producción listo para pegar. La tarea es **recrear estos diseños como componentes Blade reutilizables** dentro del proyecto Laravel, usando los tokens y las convenciones del codebase.

```
handoff/
├── README.md                      ← este archivo
├── tokens.css                     ← CSS variables (light + dark)
├── tailwind.config.snippet.js     ← merge en tu tailwind.config.js
├── fonts.html                     ← <link> de Google Fonts
└── reference/
    ├── futgo.css                  ← design system completo en CSS (la fuente de verdad visual)
    ├── futgo.js                   ← toggle de tema + helpers de demo
    ├── 01 Identidad.html          ← estrategia, logo, color, tipografía
    ├── 02 UI Kit.html             ← todos los componentes (claro & oscuro)
    └── 03 Landing.html            ← landing de conversión (2 variantes de hero)
```

`reference/futgo.css` es la **referencia visual canónica**: cada clase ahí define el aspecto final de un componente. Tradúcela a Tailwind/Blade conservando exactamente los valores (colores, radios, tamaños, estados).

---

## 1. Identidad

- **Misión:** profesionalizar el fútbol amateur dándole herramientas de nivel profesional.
- **Visión:** ser el estándar (sistema operativo) del fútbol base hispano.
- **Arquetipo:** El Retador / Constructor de comunidad. Tono moderno, dinámico, competitivo y profesional. **No** casino, **no** infantil.
- **Eslogan recomendado:** **"Donde crece el fútbol amateur"** (marca). Sub-claim funcional para B2B: **"Organiza. Compite. Conecta."**

### Logo
Símbolo = **pin de ubicación + balón** (el lugar donde se juega). Wordmark `FutGO` en **Archivo Expanded 800**, con **"GO" siempre en verde de marca**.
- Versiones: lockup horizontal (primario), vertical/inverso sobre verde, isotipo solo (favicon/app icon).
- Área segura: la altura de la "O" alrededor del lockup. Tamaño mínimo wordmark ~96px de ancho; bajo eso, usar isotipo.
- Prohibido: inclinar, deformar, recolorear el "GO", separar el isotipo del texto.
- El SVG del isotipo está inline en cada HTML de `reference/` — extráelo a un componente `<x-logo>` / `resources/svg/logo.svg`.

---

## 2. Design tokens

Ver `tokens.css`. Puntos clave:

- **Dark-first.** El tema por defecto es oscuro. El modo claro se activa con `data-theme="light"` en `<html>`.
- **No usar `darkMode: 'media'`** — el usuario elige; persistir en `localStorage`.
- Todos los colores de Tailwind apuntan a **CSS variables**, así un único atributo cambia todo el tema sin recompilar ni duplicar clases `dark:`.

| Rol | Token | Dark | Light |
|---|---|---|---|
| Marca | `green` | `#00e676` | `#00c853` |
| Texto en verde | `on-green` | `#052b1a` | `#ffffff` |
| Fondo | `bg` | `#0b0f14` | `#f4f7fa` |
| Card | `surface` | `#11161d` | `#ffffff` |
| Borde | `border` | `#232f3d` | `#e1e8f0` |
| Texto | `text` | `#e8ecf1` | `#0b0f14` |
| Live/error | `danger` | `#ff5a5f` | `#e23b41` |

### Tipografía
- **Archivo** (display/marketing) — usar el **eje Expanded (`font-stretch: 125%`)** en pesos 700–900. Tracking −0.02em.
- **Inter** (UI/cuerpo) — 400–700.
- **JetBrains Mono** (labels, IDs, datos) — tracking +0.12em, UPPERCASE en etiquetas.

Cargar fuentes con el snippet de `fonts.html`. Recomendado autohospedar en producción.

---

## 3. Componentes Blade recomendados

Recrear cada uno como componente Blade (`resources/views/components/`). Mapa sugerido (clase de referencia → componente):

| Componente | Ref. en `futgo.css` | Notas |
|---|---|---|
| `<x-btn variant size>` | `.btn`, `.btn-primary…` | 5 variantes (primary/secondary/outline/ghost/danger), 3 tamaños, `btn-icon`. `:hover` sube 1px. |
| `<x-badge variant>` | `.badge`, `.badge-live…` | `live` lleva punto pulsante (`animate-pulse-live`). |
| `<x-chip :active>` | `.chip` | Grupos de filtro; estado activo en `green-tint`. |
| `<x-field>` / `<x-input>` / `<x-select>` | `.field .input .select` | Estados focus (ring verde) y error (`danger`). Usa `@tailwindcss/forms`. |
| `<x-switch>` | `.switch` | Toggle; checked = verde. |
| `<x-segmented :options>` | `.segmented` | Control segmentado. |
| `<x-tournament-card :tournament>` | `.tcard` | Cover con patrón de puntos + gradiente; meta de 3 datos. |
| `<x-match-card :match>` | `.match` | Estados: en vivo (`badge-live` + score), próximo (`vs` + hora), final (`FT`). |
| `<x-player-card :player>` | `.pcard` | Header con rating + 3 stats. |
| `<x-stat :label :value :delta>` | `.stat` | KPI; `delta.up/.down`. Variante con mini-barras (`.bars`). |
| `<x-standings :rows :highlight>` | `.table` | Tabla de clasificación; fila propia con `.me`; columna de forma G/E/P. |
| `<x-sidebar>` / `<x-topbar>` / `<x-tabs>` / `<x-bottom-nav>` | `.sidebar .topbar .tabs .bottomnav` | Navegación. Activo = `green-tint`/borde verde. |
| `<x-empty-state>` | `.empty` | Ilustración + título + CTA. |
| `<x-skeleton>` / `<x-spinner>` | `.skeleton .spinner` | Carga. |

**Recomendación:** modela los datos con props tipadas y pasa colecciones reales del backend. Evita lógica de presentación en el controlador; mantén la decisión visual (estado en vivo vs final) en el componente.

---

## 4. Interacciones con Alpine.js

Los micro-comportamientos del prototipo (`futgo.js`) se traducen directo a Alpine. Ejemplos:

**Toggle de tema (persistente, dark-first):**
```html
<html x-data="theme" :data-theme="mode === 'light' ? 'light' : null">
...
<button @click="toggle()" x-text="mode === 'light' ? 'Claro' : 'Oscuro'"></button>

<script>
document.addEventListener('alpine:init', () => {
  Alpine.data('theme', () => ({
    mode: localStorage.getItem('futgo-theme') || 'dark',
    toggle() {
      this.mode = this.mode === 'light' ? 'dark' : 'light';
      localStorage.setItem('futgo-theme', this.mode);
    },
  }));
});
</script>
```
> Para evitar flash (FOUC), aplica el tema en un `<script>` inline en el `<head>` **antes** de pintar: lee `localStorage` y setea `data-theme` en `<html>` de inmediato.

**Tabs / segmented:**
```html
<div x-data="{ tab: 'resumen' }">
  <div class="tabs">
    <button :class="tab==='resumen' && 'active'" @click="tab='resumen'">Resumen</button>
    <button :class="tab==='goleo' && 'active'" @click="tab='goleo'">Goleo</button>
  </div>
  <div x-show="tab==='resumen'">…</div>
</div>
```

**Chips de filtro, switches, marcadores en vivo (polling):** usa `x-data` local; para "en vivo" combina con Laravel Echo / `wire:poll` (si usas Livewire) para refrescar score y minuto.

**Validación de inputs de marcador:** `x-on:input` → `$el.value = $el.value.replace(/[^0-9]/g,'').slice(0,2)`.

---

## 5. Estrategia de modo oscuro

1. **Dark es el default.** `tokens.css` define los valores oscuros en `:root`; los claros en `[data-theme="light"]`.
2. Tailwind: `darkMode: ['selector', '[data-theme="dark"]']` y **colores vía CSS vars** (ver snippet). Así **no necesitas prefijar `dark:`** en cada utilidad — el color cambia solo.
3. Persistir elección en `localStorage('futgo-theme')`. Aplicar en el `<head>` para evitar FOUC.
4. Respetar `prefers-color-scheme` solo como valor inicial si no hay preferencia guardada (opcional).

---

## 6. Accesibilidad

- **Contraste:** verde `#00e676` sobre oscuro `#0b0f14` cumple AA para texto grande y elementos UI; para texto pequeño sobre verde usa `on-green` (`#052b1a`), no blanco. En modo claro, `green` baja a `#00c853` y el texto sobre verde es blanco.
- **Foco visible:** todos los interactivos usan `:focus-visible` con `--ring` (anillo verde 3px). No eliminar outlines.
- **Targets táctiles:** botones ≥ 44px de alto; bottom-nav e íconos ≥ 44px de área.
- **Semántica:** usar `<button>`/`<a>` correctos, `<table>` real para clasificación (con `<th scope>`), `aria-label` en botones de solo ícono (ya marcado en el kit).
- **Badges "en vivo":** no comunicar el estado solo con color/animación — incluir texto ("En vivo", minuto).
- **Movimiento:** envolver animaciones (`pulse-live`, `shimmer`, `spin`) en `@media (prefers-reduced-motion: reduce)` para desactivarlas.
- **Jerarquía de encabezados:** un solo `<h1>` por vista; el display Expanded es decorativo pero debe seguir orden semántico.

---

## 7. Landing page

`reference/03 Landing.html` incluye **dos variantes de hero** (switcher flotante para comparar):
- **Hero A · Split:** titular + CTA a la izquierda, mock de producto a la derecha. Mejor para mostrar el producto.
- **Hero B · Centrado:** declaración grande centrada + banda de métricas + mock ancho de tabla. Más editorial/marca.

Secciones: Hero → Social proof → Features (6) → Visión del ecosistema (diagrama) → Testimonios → CTA final. Elige una variante de hero para producción (recomendación: **A** para audiencia de organizadores que necesitan "ver el producto"; **B** para campañas de marca).

---

## 8. Checklist de implementación

- [ ] Mergear `tailwind.config.snippet.js`; importar `tokens.css` en `app.css`.
- [ ] Cargar fuentes (`fonts.html`) o autohospedar.
- [ ] Script anti-FOUC de tema en `<head>`.
- [ ] Extraer isotipo SVG a `<x-logo>`.
- [ ] Construir componentes Blade de la tabla en §3.
- [ ] Conectar datos reales (torneos, partidos, jugadores, standings).
- [ ] Verificar contraste AA y `prefers-reduced-motion`.
- [ ] QA en claro y oscuro.
