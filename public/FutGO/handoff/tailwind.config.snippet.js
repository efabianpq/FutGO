/**
 * FutGO · Tailwind config snippet  (Tailwind v3)
 * ------------------------------------------------------------
 * Pegar/mergear en tailwind.config.js del proyecto Laravel.
 * Estrategia de modo oscuro: por ATRIBUTO, no por media query.
 * Colores apuntan a las CSS vars de tokens.css → un solo lugar
 * controla light/dark.
 */

/** @type {import('tailwindcss').Config} */
module.exports = {
  darkMode: ['selector', '[data-theme="dark"]'], // controlamos el tema vía data-theme en <html>
  content: [
    './resources/**/*.blade.php',
    './resources/**/*.js',
    './resources/views/**/*.php',
  ],
  theme: {
    extend: {
      colors: {
        // todos referencian CSS vars → cambian solos con data-theme
        bg:            'var(--color-bg)',
        surface:       'var(--color-surface)',
        'surface-2':   'var(--color-surface-2)',
        'surface-3':   'var(--color-surface-3)',
        border:        'var(--color-border)',
        'border-soft': 'var(--color-border-soft)',

        text:          'var(--color-text)',
        muted:         'var(--color-text-muted)',
        subtle:        'var(--color-text-subtle)',

        green: {
          DEFAULT: 'var(--color-green)',
          strong:  'var(--color-green-strong)',
          700:     'var(--color-green-700)',
          tint:    'var(--color-green-tint)',
        },
        'on-green': 'var(--color-on-green)',
        navy:       'var(--color-navy)',

        success:    'var(--color-success)',
        'success-bg':'var(--color-success-bg)',
        warning:    'var(--color-warning)',
        'warning-bg':'var(--color-warning-bg)',
        danger:     'var(--color-danger)',
        'danger-bg':'var(--color-danger-bg)',
        info:       'var(--color-info)',
        'info-bg':  'var(--color-info-bg)',
      },

      fontFamily: {
        display: ['Archivo', 'system-ui', 'sans-serif'],
        sans:    ['Inter', 'system-ui', 'sans-serif'],
        mono:    ['"JetBrains Mono"', 'ui-monospace', 'monospace'],
      },

      // Archivo se usa en su eje Expanded. Activa con la utilidad font-stretch
      // o crea un componente .display (ver abajo).
      fontSize: {
        'display-xl': ['clamp(52px,8vw,116px)', { lineHeight: '0.86', letterSpacing: '-0.035em', fontWeight: '900' }],
        'display-l':  ['clamp(40px,6vw,84px)',  { lineHeight: '0.90', letterSpacing: '-0.03em',  fontWeight: '900' }],
        'display-m':  ['40px', { lineHeight: '1.0',  letterSpacing: '-0.015em', fontWeight: '800' }],
        'display-s':  ['28px', { lineHeight: '1.05', letterSpacing: '-0.01em',  fontWeight: '700' }],
      },

      borderRadius: {
        xs: '6px', sm: '8px', md: '12px', lg: '16px', xl: '24px', '2xl': '32px', pill: '9999px',
      },

      boxShadow: {
        ring:  '0 0 0 3px rgba(0,230,118,.28)',
        glow:  '0 0 28px rgba(0,230,118,.22)',
        card:  '0 8px 24px rgba(0,0,0,.45)',
        float: '0 20px 60px rgba(0,0,0,.55)',
      },

      keyframes: {
        'pulse-live': { '0%,100%': { opacity: '1' }, '50%': { opacity: '0.25' } },
        shimmer:      { '0%': { backgroundPosition: '100% 0' }, '100%': { backgroundPosition: '-100% 0' } },
      },
      animation: {
        'pulse-live': 'pulse-live 1.3s infinite',
        shimmer:      'shimmer 1.4s ease infinite',
      },
    },
  },
  plugins: [
    require('@tailwindcss/forms'),
  ],
};

/*
  Utilidad recomendada para el display Expanded (añadir en app.css con @layer):

  @layer components {
    .font-display-x { font-family: theme('fontFamily.display'); font-stretch: 125%; }
  }

  Uso en Blade:
    <h1 class="font-display font-display-x text-display-l text-text">Matchday</h1>
    <button class="bg-green text-on-green rounded-sm h-11 px-5 font-semibold shadow-glow">Crear torneo</button>
    <span class="bg-green-tint text-green rounded-pill px-2.5 h-6 text-[11px] font-mono uppercase tracking-wider">Inscrito</span>
*/
