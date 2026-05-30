/**
 * FutGO · Tailwind config
 * ------------------------------------------------------------
 * Dark-first. El tema se controla con el atributo data-theme en <html>
 * (NO media query). Todos los colores apuntan a CSS vars de
 * resources/css/app.css (tokens), así un solo lugar cambia light/dark.
 *
 * Los tokens "legacy" (pitch/gol/bone/ink/line/neutral) se conservan como
 * ALIAS hacia las nuevas variables para que las vistas heredadas adopten el
 * brand FutGO sin reescribirse. Ver overrides puntuales en app.css.
 */

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: ['selector', '[data-theme="dark"]'],
    content: [
        './resources/**/*.blade.php',
        './resources/**/*.js',
        './resources/**/*.vue',
    ],
    theme: {
        colors: {
            transparent: 'transparent',
            current: 'currentColor',
            white: '#ffffff',
            black: '#000000',

            /* ─── Sistema FutGO (temático vía CSS vars, con soporte de opacidad) ── */
            bg:            'rgb(var(--c-bg) / <alpha-value>)',
            surface:       'rgb(var(--c-surface) / <alpha-value>)',
            'surface-2':   'rgb(var(--c-surface-2) / <alpha-value>)',
            'surface-3':   'rgb(var(--c-surface-3) / <alpha-value>)',
            border:        'rgb(var(--c-border) / <alpha-value>)',
            'border-soft': 'rgb(var(--c-border-soft) / <alpha-value>)',

            text:   'rgb(var(--c-text) / <alpha-value>)',
            muted:  'rgb(var(--c-text-muted) / <alpha-value>)',
            subtle: 'rgb(var(--c-text-subtle) / <alpha-value>)',

            green: {
                DEFAULT: 'rgb(var(--c-green) / <alpha-value>)',
                strong:  'rgb(var(--c-green-strong) / <alpha-value>)',
                700:     'rgb(var(--c-green-700) / <alpha-value>)',
                tint:    'rgb(var(--c-green-tint) / <alpha-value>)',
            },
            'on-green': 'rgb(var(--c-on-green) / <alpha-value>)',
            navy:       'rgb(var(--c-navy) / <alpha-value>)',

            success:      'rgb(var(--c-success) / <alpha-value>)',
            'success-bg': 'rgb(var(--c-success-bg) / <alpha-value>)',
            warning:      'rgb(var(--c-warning) / <alpha-value>)',
            'warning-bg': 'rgb(var(--c-warning-bg) / <alpha-value>)',
            danger:       'rgb(var(--c-danger) / <alpha-value>)',
            'danger-bg':  'rgb(var(--c-danger-bg) / <alpha-value>)',
            info:         'rgb(var(--c-info) / <alpha-value>)',
            'info-bg':    'rgb(var(--c-info-bg) / <alpha-value>)',

            /* ─── Alias legacy → tokens FutGO ───────────────────── */
            // pitch: antes verde oscuro de marca. Se usa sobre todo como
            // texto (titulares) → mapeado a --c-text (temático). Los usos
            // como fondo (bg-pitch*) se reescriben en app.css a superficies.
            pitch: {
                DEFAULT: 'rgb(var(--c-text) / <alpha-value>)',
                deep:    'rgb(var(--c-text) / <alpha-value>)',
                light:   'rgb(var(--c-green-700) / <alpha-value>)',
                mist:    'rgb(var(--c-surface-2) / <alpha-value>)',
                50:  'rgb(var(--c-green-tint) / <alpha-value>)',
                100: 'rgb(var(--c-green-tint) / <alpha-value>)',
                300: 'rgb(var(--c-green) / <alpha-value>)',
                500: 'rgb(var(--c-green-700) / <alpha-value>)',
                700: 'rgb(var(--c-text) / <alpha-value>)',
                900: 'rgb(var(--c-text) / <alpha-value>)',
            },

            // gol: antes acento amarillo (CTA). Ahora el acento es el verde FutGO.
            gol: {
                DEFAULT: 'rgb(var(--c-green) / <alpha-value>)',
                deep:    'rgb(var(--c-green-strong) / <alpha-value>)',
            },

            alerta: {
                DEFAULT: 'rgb(var(--c-danger) / <alpha-value>)',
                deep:    'rgb(var(--c-danger) / <alpha-value>)',
            },

            // bone: antes fondo crema. Ahora el fondo temático.
            bone: {
                DEFAULT: 'rgb(var(--c-bg) / <alpha-value>)',
                soft:    'rgb(var(--c-surface-2) / <alpha-value>)',
            },

            // ink: antes texto casi negro. Ahora el texto temático.
            ink: {
                DEFAULT: 'rgb(var(--c-text) / <alpha-value>)',
                soft:    'rgb(var(--c-text-muted) / <alpha-value>)',
                mute:    'rgb(var(--c-text-subtle) / <alpha-value>)',
            },

            // line: antes bordes claros. Ahora los bordes temáticos.
            line: {
                DEFAULT: 'rgb(var(--c-border) / <alpha-value>)',
                soft:    'rgb(var(--c-border-soft) / <alpha-value>)',
            },

            neutral: {
                50:  'rgb(var(--c-surface-2) / <alpha-value>)',
                200: 'rgb(var(--c-border) / <alpha-value>)',
                300: 'rgb(var(--c-border) / <alpha-value>)',
                500: 'rgb(var(--c-text-subtle) / <alpha-value>)',
                700: 'rgb(var(--c-text-muted) / <alpha-value>)',
                900: 'rgb(var(--c-text) / <alpha-value>)',
            },
        },

        extend: {
            fontFamily: {
                display: ['Archivo', 'system-ui', 'sans-serif'],
                body:    ['Inter', 'system-ui', 'sans-serif'],
                sans:    ['Inter', 'system-ui', 'sans-serif'],
                mono:    ['"JetBrains Mono"', 'ui-monospace', 'monospace'],
            },

            fontSize: {
                'display-xl': ['clamp(52px,8vw,116px)', { lineHeight: '0.86', letterSpacing: '-0.035em', fontWeight: '900' }],
                'display-l':  ['clamp(40px,6vw,84px)',  { lineHeight: '0.90', letterSpacing: '-0.03em',  fontWeight: '900' }],
                'display-m':  ['40px', { lineHeight: '1.0',  letterSpacing: '-0.015em', fontWeight: '800' }],
                'display-s':  ['28px', { lineHeight: '1.05', letterSpacing: '-0.01em',  fontWeight: '700' }],
                'body-l':     ['20px', { lineHeight: '1.50' }],
                'body':       ['16px', { lineHeight: '1.60' }],
                'body-s':     ['14px', { lineHeight: '1.55' }],
                'mono-label': ['11px', { lineHeight: '1.40', letterSpacing: '0.12em', fontWeight: '500' }],
            },

            borderRadius: {
                xs: '6px',
                sm: '8px',
                md: '12px',
                lg: '16px',
                xl: '24px',
                '2xl': '32px',
                pill: '9999px',
            },

            boxShadow: {
                card:   '0 1px 2px rgba(0,0,0,.4)',
                'card-2':'0 8px 24px rgba(0,0,0,.45)',
                modal:  '0 20px 60px rgba(0,0,0,.55)',
                ring:   '0 0 0 3px rgba(0,230,118,.28)',
                glow:   '0 0 28px rgba(0,230,118,.22)',
                float:  '0 20px 60px rgba(0,0,0,.55)',
            },

            letterSpacing: {
                'tight-display': '-0.02em',
                'wide-cta':      '0.08em',
                'wide-label':    '0.12em',
                'wide-eyebrow':  '0.12em',
            },

            transitionDuration: {
                fast: '180ms',
            },

            keyframes: {
                'pulse-live': { '0%, 100%': { opacity: '1' }, '50%': { opacity: '0.25' } },
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
