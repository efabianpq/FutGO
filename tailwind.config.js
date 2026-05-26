/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './resources/**/*.blade.php',
        './resources/**/*.js',
        './resources/**/*.vue',
    ],
    theme: {
        extend: {
            fontFamily: {
                sans: ['Inter', 'ui-sans-serif', 'system-ui', 'sans-serif'],
            },
            colors: {
                pachon: {
                    green: '#0f8c3a',
                    'green-dark': '#0a6b2c',
                    gold: '#d4af37',
                    'gold-dark': '#b8941f',
                },
            },
        },
    },
    plugins: [],
};
