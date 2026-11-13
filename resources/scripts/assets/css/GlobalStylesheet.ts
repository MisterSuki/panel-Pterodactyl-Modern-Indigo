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
        /* The two neighbours of the accent on the colour wheel, for the aurora and the neon edges. */
        --pd-neon-2: 84 10 255;
        --pd-neon-3: 10 255 206;

        /* The surfaces of the theme. */
        --pd-bg: #0a0a0a;
        --pd-surface: rgb(17 17 17 / 0.86);
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
     * The default background, an aurora: three soft lights (the accent and its two neighbours) that drift slowly behind
     * the page, under a faint grid of dots and a dark veil towards the bottom. Only a transform moves, so the browser
     * hands it to the graphics card. The other backgrounds of the Appearance page (gradients, pictures, effects) are
     * drawn by /js/appearance.js on top of it, and the grid then goes away.
     */
    @keyframes pd-aurora {
        0% {
            transform: translate3d(0, 0, 0) rotate(0deg) scale(1);
        }
        50% {
            transform: translate3d(5%, 4%, 0) rotate(10deg) scale(1.1);
        }
        100% {
            transform: translate3d(-4%, 6%, 0) rotate(-8deg) scale(1.05);
        }
    }

    body::before {
        content: '';
        position: fixed;
        inset: -25%;
        z-index: -1;
        pointer-events: none;
        background: radial-gradient(24% 26% at 30% 26%, rgb(var(--pd-accent-500) / 0.5), transparent 70%),
            radial-gradient(24% 24% at 72% 22%, rgb(var(--pd-neon-2) / 0.42), transparent 70%),
            radial-gradient(28% 30% at 62% 64%, rgb(var(--pd-neon-3) / 0.2), transparent 70%),
            radial-gradient(24% 26% at 24% 72%, rgb(var(--pd-neon-2) / 0.26), transparent 70%),
            radial-gradient(18% 20% at 84% 78%, rgb(var(--pd-accent-500) / 0.26), transparent 70%), var(--pd-bg);
        animation: pd-aurora 36s ease-in-out infinite alternate;
        will-change: transform;
    }

    body::after {
        content: '';
        position: fixed;
        inset: 0;
        z-index: -1;
        pointer-events: none;
        background: linear-gradient(180deg, transparent 0%, rgba(5, 5, 8, 0.1) 50%, rgba(5, 5, 8, 0.45) 100%),
            radial-gradient(rgba(255, 255, 255, 0.07) 1px, transparent 1px) 0 0 / 24px 24px;
    }

    html[data-pd-background]:not([data-pd-background='liquid']) body::after {
        display: none;
    }

    /*
     * The neon edge of the cards: a thin line that fades from the accent to its neighbours around the card, drawn with a
     * mask so only the edge shows. The cards themselves are dark glass that lets the aurora through (not blurred: the
     * aurora is already soft, and a blur under every card would be redrawn on every frame of its movement).
     */
    .lg-rim.lg-rim,
    .pd-neon.pd-neon {
        border-color: transparent !important;
        background-color: rgba(14, 14, 20, 0.62);
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.05), 0 20px 50px -30px rgba(0, 0, 0, 0.9);
    }

    .lg-rim::before,
    .pd-neon::before {
        content: '';
        position: absolute;
        inset: 0;
        z-index: 0;
        border-radius: inherit;
        padding: 1px;
        pointer-events: none;
        background: linear-gradient(
            135deg,
            rgb(var(--pd-accent-400) / 0.6) 0%,
            rgb(var(--pd-neon-2) / 0.22) 30%,
            rgba(255, 255, 255, 0.06) 55%,
            rgb(var(--pd-neon-3) / 0.35) 100%
        );
        -webkit-mask: linear-gradient(#000 0 0) content-box, linear-gradient(#000 0 0);
        -webkit-mask-composite: xor;
        mask: linear-gradient(#000 0 0) content-box exclude, linear-gradient(#000 0 0);
        opacity: 0.75;
        transition: opacity 200ms ease;
    }

    .lg-rim:hover::before,
    .pd-neon:hover::before {
        opacity: 1;
    }

    /* A figure that glows a little in the accent colour. */
    .pd-glow-text {
        text-shadow: 0 0 22px rgb(var(--pd-accent-500) / 0.55), 0 0 2px rgb(var(--pd-accent-300) / 0.35);
    }

    /* The mark of the panel, in the accent colour. */
    .pd-mark {
        background: linear-gradient(135deg, rgb(var(--pd-accent-300)) 0%, rgb(var(--pd-accent-600)) 55%, rgb(var(--pd-neon-2)) 100%);
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.35), 0 0 18px -2px rgb(var(--pd-accent-500) / 0.7);
    }

    /* A word in the accent colour, fading from light to deep (the name of the person on the dashboard). */
    @keyframes pd-gradient-flow {
        to {
            background-position: 200% 0;
        }
    }

    .pd-gradient-text {
        background: linear-gradient(
                90deg,
                rgb(var(--pd-accent-300)) 0%,
                rgb(var(--pd-accent-100)) 50%,
                rgb(var(--pd-accent-300)) 100%
            )
            0 0 / 200% 100%;
        -webkit-background-clip: text;
        background-clip: text;
        -webkit-text-fill-color: transparent;
        animation: pd-gradient-flow 8s linear infinite;
        filter: drop-shadow(0 0 18px rgb(var(--pd-accent-500) / 0.35));
    }

    /* Where the browser can mix colours, the neighbours of the accent join in, lightened so they read on the dark. */
    @supports (color: color-mix(in srgb, red 50%, white)) {
        .pd-gradient-text {
            background-image: linear-gradient(
                90deg,
                rgb(var(--pd-accent-300)) 0%,
                color-mix(in srgb, rgb(var(--pd-neon-2)) 55%, #ffffff) 33%,
                color-mix(in srgb, rgb(var(--pd-neon-3)) 70%, #ffffff) 66%,
                rgb(var(--pd-accent-300)) 100%
            );
        }
    }

    /* A block that stands for content on its way: a light that runs across it. */
    @keyframes pd-shimmer {
        from {
            background-position: 150% 0;
        }
        to {
            background-position: -50% 0;
        }
    }

    .pd-skeleton {
        background: linear-gradient(
                100deg,
                rgba(255, 255, 255, 0) 30%,
                rgba(255, 255, 255, 0.07) 50%,
                rgba(255, 255, 255, 0) 70%
            )
            0 0 / 200% 100%,
            rgba(255, 255, 255, 0.05);
        animation: pd-shimmer 1.6s ease-in-out infinite;
    }

    /* How strong the light that follows the pointer over a card is (0 to 1), so it can fade in and out. */
    @property --pd-glow {
        syntax: '<number>';
        inherits: false;
        initial-value: 0;
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
