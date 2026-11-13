import tw from 'twin.macro';
import { createGlobalStyle } from 'styled-components/macro';

export default createGlobalStyle`
    /*
     * Geist and Geist Mono, served by the panel itself (nothing is asked from Google or Vercel). The theme is a plain,
     * sharp dashboard: near-black surfaces, thin borders, one light accent, no glass and no glow.
     */
    @font-face {
        font-family: 'Geist';
        font-style: normal;
        font-weight: 100 900;
        font-display: swap;
        src: url('/fonts/geist/geist-latin-wght-normal.woff2') format('woff2');
        unicode-range: U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+0304, U+0308, U+0329,
            U+2000-206F, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD;
    }

    @font-face {
        font-family: 'Geist';
        font-style: normal;
        font-weight: 100 900;
        font-display: swap;
        src: url('/fonts/geist/geist-latin-ext-wght-normal.woff2') format('woff2');
        unicode-range: U+0100-02BA, U+02BD-02C5, U+02C7-02CC, U+02CE-02D7, U+02DD-02FF, U+0304, U+0308, U+0329,
            U+1D00-1DBF, U+1E00-1E9F, U+1EF2-1EFF, U+2020, U+20A0-20AB, U+20AD-20C0, U+2113, U+2C60-2C7F, U+A720-A7FF;
    }

    @font-face {
        font-family: 'Geist Mono';
        font-style: normal;
        font-weight: 100 900;
        font-display: swap;
        src: url('/fonts/geist/geist-mono-latin-wght-normal.woff2') format('woff2');
        unicode-range: U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+0304, U+0308, U+0329,
            U+2000-206F, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD;
    }

    @font-face {
        font-family: 'Geist Mono';
        font-style: normal;
        font-weight: 100 900;
        font-display: swap;
        src: url('/fonts/geist/geist-mono-latin-ext-wght-normal.woff2') format('woff2');
        unicode-range: U+0100-02BA, U+02BD-02C5, U+02C7-02CC, U+02CE-02D7, U+02DD-02FF, U+0304, U+0308, U+0329,
            U+1D00-1DBF, U+1E00-1E9F, U+1EF2-1EFF, U+2020, U+20A0-20AB, U+20AD-20C0, U+2113, U+2C60-2C7F, U+A720-A7FF;
    }

    /* The accent colour, blue by default. /js/appearance.js replaces it with the one the person picked. */
    :root {
        --pd-accent-50: 230 243 255;
        --pd-accent-100: 206 230 255;
        --pd-accent-200: 157 206 255;
        --pd-accent-300: 108 181 255;
        --pd-accent-400: 59 157 255;
        --pd-accent-500: 10 132 255;
        --pd-accent-600: 9 116 224;
        --pd-accent-700: 7 98 189;
        --pd-accent-800: 6 77 148;
        --pd-accent-900: 4 55 107;
        --pd-a: 10, 132, 255;

        /* The surfaces of the theme. */
        --pd-bg: #0a0a0a;
        --pd-surface: #111111;
        --pd-raised: #161616;
        --pd-border: #1f1f1f;
        --pd-border-strong: #2e2e2e;
        --pd-text: #ededed;
        --pd-muted: #a1a1a1;
    }

    html {
        background: var(--pd-bg);
        scroll-behavior: smooth;
    }

    select,
    input,
    textarea {
        color-scheme: dark;
    }

    select option,
    select optgroup {
        background-color: var(--pd-surface);
        color: var(--pd-text);
    }

    select option:checked {
        background-color: #262626;
        color: #ffffff;
    }

    select option:disabled {
        color: #707070;
    }

    body {
        ${tw`font-sans text-neutral-300 antialiased`};
        min-height: 100vh;
        background: transparent;
        font-size: 14px;
        -webkit-font-smoothing: antialiased;
        -moz-osx-font-smoothing: grayscale;
        text-rendering: optimizeLegibility;
    }

    /*
     * The background: plain, near black. The other backgrounds of the Appearance page (gradients, pictures, effects)
     * are drawn by /js/appearance.js on top of this one.
     */
    body::before {
        content: '';
        position: fixed;
        inset: 0;
        z-index: -1;
        pointer-events: none;
        background: var(--pd-bg);
    }

    /*
     * What floats over the page (the top bar, menus, sheets). The class keeps its old name ("glass") for the components
     * that use it: it is now a flat surface, only the top bar is a little see-through.
     */
    .lg-glass {
        background: rgba(10, 10, 10, 0.8);
        border: 1px solid var(--pd-border);
        -webkit-backdrop-filter: saturate(150%) blur(12px);
        backdrop-filter: saturate(150%) blur(12px);
        scrollbar-width: none;
    }

    .lg-glass::-webkit-scrollbar {
        display: none;
    }

    .lg-glass-thick {
        background: var(--pd-surface);
        border: 1px solid var(--pd-border-strong);
        box-shadow: 0 16px 40px -12px rgba(0, 0, 0, 0.8);
    }

    /* The old light on the edges of the glass is gone. */
    .lg-glass::after,
    .lg-rim::after {
        display: none;
    }

    @media (prefers-reduced-motion: reduce) {
        html {
            scroll-behavior: auto;
        }

        *, *::before, *::after {
            animation-duration: 0.01ms !important;
            animation-iteration-count: 1 !important;
            transition-duration: 0.01ms !important;
        }
    }

    ::selection {
        background: rgb(var(--pd-accent-500) / 0.4);
        color: #fff;
    }

    h1, h2, h3, h4, h5, h6 {
        ${tw`font-semibold font-header text-neutral-50`};
        letter-spacing: -0.02em;
    }

    h1 {
        letter-spacing: -0.04em;
    }

    p {
        ${tw`text-neutral-300 leading-snug font-sans`};
    }

    code, kbd, pre, .font-mono {
        font-family: 'Geist Mono', ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
    }

    form {
        ${tw`m-0`};
    }

    textarea, select, input, button, button:focus, button:focus-visible {
        ${tw`outline-none`};
    }

    /* A clear ring when the keyboard is used, nothing for the mouse. */
    a:focus-visible,
    button:focus-visible,
    [role='button']:focus-visible,
    summary:focus-visible {
        outline: none;
        box-shadow: 0 0 0 2px var(--pd-bg), 0 0 0 4px rgb(var(--pd-accent-500) / 0.8);
    }

    a {
        ${tw`text-primary-400`};
    }

    input[type=number]::-webkit-outer-spin-button,
    input[type=number]::-webkit-inner-spin-button {
        -webkit-appearance: none !important;
        margin: 0;
    }

    input[type=number] {
        -moz-appearance: textfield !important;
    }

    ::-webkit-scrollbar {
        background: none;
        width: 10px;
        height: 10px;
    }

    ::-webkit-scrollbar-thumb {
        border: solid 3px transparent;
        border-radius: 9999px;
        background-clip: padding-box;
        background-color: #2e2e2e;
    }

    ::-webkit-scrollbar-thumb:hover {
        background-color: #454545;
    }

    ::-webkit-scrollbar-track-piece {
        margin: 4px 0;
    }

    ::-webkit-scrollbar-corner {
        background: transparent;
    }
`;
