const colors = require('tailwindcss/colors');

// The greys of the theme, from the text (50) to the page (900): near black surfaces, thin borders, clear text.
const gray = {
    50: '#EDEDED',
    100: '#E2E2E2',
    200: '#C7C7C7',
    300: '#A1A1A1',
    400: '#8F8F8F',
    500: '#707070',
    600: '#2E2E2E',
    700: '#1F1F1F',
    // Cards and insets let the background of the Appearance page show through a little; on the plain background they
    // look solid.
    800: 'rgb(17 17 17 / 0.86)',
    900: 'rgb(10 10 10 / 0.9)',
};

// The accent colour (blue unless the person picked another one), used for links, focus rings, selections and charts.
// Each shade is a CSS variable holding "r g b", set by public/js/appearance.js (defaults in GlobalStylesheet).
const accent = (shade) => ({ opacityValue }) =>
    opacityValue === undefined
        ? `rgb(var(--pd-accent-${shade}))`
        : `rgb(var(--pd-accent-${shade}) / ${opacityValue})`;

const primary = [50, 100, 200, 300, 400, 500, 600, 700, 800, 900].reduce(
    (shades, shade) => ({ ...shades, [shade]: accent(shade) }),
    {}
);

// Geist and Geist Mono, served by the panel (see GlobalStylesheet).
const systemFont = ['Geist', '-apple-system', 'BlinkMacSystemFont', '"Segoe UI"', 'Roboto', '"Helvetica Neue"', 'Arial', 'sans-serif'];
const monoFont = ['"Geist Mono"', 'ui-monospace', 'SFMono-Regular', 'Menlo', 'Consolas', 'monospace'];

module.exports = {
    content: [
        './resources/scripts/**/*.{js,ts,tsx}',
    ],
    theme: {
        extend: {
            fontFamily: {
                sans: systemFont,
                header: systemFont,
                mono: monoFont,
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
            // Small, sharp corners.
            borderRadius: {
                DEFAULT: '0.375rem',
                md: '0.375rem',
                lg: '0.5rem',
                xl: '0.625rem',
                '2xl': '0.75rem',
                '3xl': '1rem',
            },
            transitionDuration: {
                250: '250ms',
            },
            borderColor: theme => ({
                default: '#1F1F1F',
            }),
            // Flat: depth comes from the borders. Only what floats over the page has a shadow.
            boxShadow: {
                card: 'inset 0 1px 0 rgba(255, 255, 255, 0.04)',
                'card-hover': 'inset 0 1px 0 rgba(255, 255, 255, 0.06)',
                glow: '0 0 #0000',
                nav: '0 0 #0000',
                pop: '0 16px 40px -12px rgba(0, 0, 0, 0.8)',
            },
            backgroundImage: {
                'gradient-brand': 'linear-gradient(0deg, rgb(var(--pd-accent-500)), rgb(var(--pd-accent-500)))',
                'gradient-brand-flat': 'linear-gradient(0deg, rgb(var(--pd-accent-500)), rgb(var(--pd-accent-500)))',
                'gradient-surface': 'linear-gradient(0deg, #111111, #111111)',
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
