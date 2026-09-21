const colors = require('tailwindcss/colors');

// Cool slate-navy scale used for surfaces/backgrounds. Tuned darker and bluer than the
// stock Tailwind gray so cards and page background can visibly layer on top of each other.
const gray = {
    50: 'hsl(220, 35%, 97%)',
    100: 'hsl(220, 24%, 92%)',
    200: 'hsl(220, 18%, 83%)',
    300: 'hsl(220, 14%, 68%)',
    400: 'hsl(220, 12%, 54%)',
    500: 'hsl(222, 14%, 42%)',
    600: 'hsl(222, 18%, 30%)',
    700: 'hsl(222, 24%, 19%)',
    800: 'hsl(224, 28%, 13%)',
    900: 'hsl(226, 34%, 8%)',
};

// Vivid indigo "brand" accent used for CTAs, links, focus states, and glow effects.
const primary = {
    50: '#eef2ff',
    100: '#e0e7ff',
    200: '#c7d2fe',
    300: '#a5b4fc',
    400: '#818cf8',
    500: '#6366f1',
    600: '#4f46e5',
    700: '#4338ca',
    800: '#3730a3',
    900: '#312e81',
};

module.exports = {
    content: [
        './resources/scripts/**/*.{js,ts,tsx}',
    ],
    theme: {
        extend: {
            fontFamily: {
                header: ['"IBM Plex Sans"', '"Roboto"', 'system-ui', 'sans-serif'],
            },
            colors: {
                black: '#05070d',
                // "primary" and "neutral" are deprecated, prefer the use of "blue" and "gray"
                // in new code.
                primary: primary,
                brand: primary,
                gray: gray,
                neutral: gray,
                cyan: colors.cyan,
            },
            fontSize: {
                '2xs': '0.625rem',
            },
            transitionDuration: {
                250: '250ms',
            },
            borderColor: theme => ({
                default: theme('colors.neutral.400', 'currentColor'),
            }),
            boxShadow: {
                card: '0 1px 1px rgba(0, 0, 0, 0.15), 0 12px 28px -12px rgba(2, 6, 23, 0.65)',
                'card-hover': '0 1px 1px rgba(0, 0, 0, 0.15), 0 20px 40px -14px rgba(2, 6, 23, 0.75)',
                glow: '0 0 0 1px rgba(99, 102, 241, 0.4), 0 8px 30px -6px rgba(99, 102, 241, 0.45)',
                nav: '0 1px 0 rgba(255, 255, 255, 0.04), 0 8px 30px -8px rgba(2, 6, 23, 0.8)',
            },
            backgroundImage: {
                'gradient-brand': 'linear-gradient(135deg, #6366f1 0%, #8b5cf6 55%, #06b6d4 100%)',
                'gradient-brand-flat': 'linear-gradient(135deg, #6366f1 0%, #4f46e5 100%)',
                'gradient-surface': 'linear-gradient(180deg, hsl(224, 28%, 15%) 0%, hsl(224, 28%, 12%) 100%)',
            },
        },
    },
    plugins: [
        require('@tailwindcss/line-clamp'),
        require('@tailwindcss/forms')({
            strategy: 'class',
        }),
    ]
};
