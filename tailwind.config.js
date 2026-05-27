/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './resources/**/*.blade.php',
        './resources/**/*.js',
        './resources/**/*.vue',
    ],
    theme: {
        // Reemplazo total de la paleta default — usar SOLO estos colores
        colors: {
            transparent: 'transparent',
            current: 'currentColor',
            white: '#ffffff',
            black: '#000000',

            pitch: {
                DEFAULT: '#0a3d2e',
                deep:    '#06281e',
                light:   '#14593f',
                mist:    '#e8efe9',
                50:  '#e8efe9',
                100: '#b8d2c4',
                300: '#4a8a6f',
                500: '#14593f',
                700: '#0a3d2e',
                900: '#06281e',
            },

            gol: {
                DEFAULT: '#f4d03f',
                deep:    '#d4a82a',
            },

            alerta: {
                DEFAULT: '#e74c3c',
                deep:    '#b8392b',
            },

            bone: {
                DEFAULT: '#f5f1e8',
                soft:    '#faf7ef',
            },

            ink: {
                DEFAULT: '#1a1a1a',
                soft:    '#4a4a48',
                mute:    '#8a8884',
            },

            line: {
                DEFAULT: '#e5dfd1',
                soft:    '#efeadc',
            },

            neutral: {
                50:  '#f5f1e8',
                200: '#e5dfd1',
                300: '#c8c4ba',
                500: '#8a8884',
                700: '#4a4a48',
                900: '#1a1a1a',
            },
        },

        extend: {
            fontFamily: {
                display: ['"Barlow Condensed"', '"Arial Narrow"', 'sans-serif'],
                body:    ['"DM Sans"', 'system-ui', 'sans-serif'],
                sans:    ['"DM Sans"', 'system-ui', 'sans-serif'],
                mono:    ['"JetBrains Mono"', 'ui-monospace', 'monospace'],
            },

            fontSize: {
                'display-xl': ['96px', { lineHeight: '0.90', letterSpacing: '-0.02em',  fontWeight: '800' }],
                'display-l':  ['56px', { lineHeight: '0.98', letterSpacing: '-0.015em', fontWeight: '700' }],
                'display-m':  ['32px', { lineHeight: '1.05', letterSpacing: '0.01em',   fontWeight: '600' }],
                'display-s':  ['22px', { lineHeight: '1.10', letterSpacing: '0.02em',   fontWeight: '700' }],
                'body-l':     ['20px', { lineHeight: '1.50' }],
                'body':       ['16px', { lineHeight: '1.60' }],
                'body-s':     ['14px', { lineHeight: '1.55' }],
                'mono-label': ['11px', { lineHeight: '1.40', letterSpacing: '0.14em',   fontWeight: '500' }],
            },

            borderRadius: {
                sm: '4px',
                md: '8px',
                lg: '14px',
                xl: '22px',
                pill: '9999px',
            },

            boxShadow: {
                'card':   '0 1px 2px rgba(10, 25, 18, .06)',
                'card-2': '0 4px 16px rgba(10, 25, 18, .08)',
                'modal':  '0 12px 40px rgba(10, 25, 18, .12)',
            },

            letterSpacing: {
                'tight-display': '-0.02em',
                'wide-cta':      '0.08em',
                'wide-label':    '0.14em',
                'wide-eyebrow':  '0.12em',
            },

            transitionDuration: {
                fast: '150ms',
            },

            animation: {
                'pulse-live': 'pulse-live 1.4s infinite',
            },
            keyframes: {
                'pulse-live': {
                    '0%, 100%': { opacity: '1' },
                    '50%':      { opacity: '0.3' },
                },
            },
        },
    },
    plugins: [
        require('@tailwindcss/forms'),
    ],
};
