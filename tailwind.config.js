const colors = require('tailwindcss/colors');

// iOS dark palette. 50-600 are the opaque "label" greys used for text and icons. 700-900 are translucent "materials":
// surfaces let the blurred wallpaper behind them glow through, which is what gives the frosted-glass look.
const gray = {
    50: '#F5F5F7',
    100: '#EBEBF0',
    200: '#D1D1D6',
    300: '#AEAEB2',
    400: '#8E8E93',
    500: '#636366',
    600: '#48484A',
    700: 'rgba(118, 118, 128, 0.24)',
    800: 'rgba(28, 28, 32, 0.55)',
    900: 'rgba(10, 10, 14, 0.62)',
};

// iOS system blue, used for CTAs, links, focus states and glows.
const primary = {
    50: '#E5F1FF',
    100: '#CCE4FF',
    200: '#99C9FF',
    300: '#66ADFF',
    400: '#3D9BFF',
    500: '#0A84FF',
    600: '#0071E3',
    700: '#005BBF',
    800: '#00479A',
    900: '#003374',
};

const systemFont = [
    '-apple-system',
    'BlinkMacSystemFont',
    '"SF Pro Display"',
    '"SF Pro Text"',
    '"Segoe UI Variable Display"',
    '"Segoe UI"',
    'Roboto',
    '"Helvetica Neue"',
    'Arial',
    'sans-serif',
];

module.exports = {
    content: [
        './resources/scripts/**/*.{js,ts,tsx}',
    ],
    theme: {
        extend: {
            fontFamily: {
                sans: systemFont,
                header: systemFont,
            },
            colors: {
                black: '#000000',
                // "primary" and "neutral" are deprecated, prefer the use of "blue" and "gray"
                // in new code.
                primary: primary,
                brand: primary,
                blue: primary,
                gray: gray,
                neutral: gray,
                cyan: colors.cyan,
            },
            fontSize: {
                '2xs': '0.625rem',
            },
            // Rounder corners everywhere, like iOS.
            borderRadius: {
                DEFAULT: '0.5rem',
                md: '0.625rem',
                lg: '0.75rem',
                xl: '1rem',
                '2xl': '1.375rem',
                '3xl': '1.75rem',
            },
            transitionDuration: {
                250: '250ms',
            },
            borderColor: theme => ({
                default: 'rgba(255, 255, 255, 0.12)',
            }),
            boxShadow: {
                card: 'inset 0 1px 0 rgba(255, 255, 255, 0.06), 0 10px 30px -12px rgba(0, 0, 0, 0.55)',
                'card-hover': 'inset 0 1px 0 rgba(255, 255, 255, 0.08), 0 18px 40px -14px rgba(0, 0, 0, 0.65)',
                glow: '0 0 0 1px rgba(10, 132, 255, 0.45), 0 8px 30px -6px rgba(10, 132, 255, 0.5)',
                nav: 'inset 0 -1px 0 rgba(255, 255, 255, 0.06), 0 8px 30px -10px rgba(0, 0, 0, 0.6)',
            },
            backgroundImage: {
                'gradient-brand': 'linear-gradient(135deg, #0A84FF 0%, #5E5CE6 55%, #64D2FF 100%)',
                'gradient-brand-flat': 'linear-gradient(135deg, #3D9BFF 0%, #0A84FF 100%)',
                'gradient-surface': 'linear-gradient(180deg, rgba(44, 44, 50, 0.6) 0%, rgba(28, 28, 32, 0.55) 100%)',
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
