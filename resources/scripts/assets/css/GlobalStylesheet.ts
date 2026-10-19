import tw from 'twin.macro';
import { createGlobalStyle } from 'styled-components/macro';

export default createGlobalStyle`
    /* The accent colour, iOS blue by default. /js/appearance.js replaces it with the one the person picked. */
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
    }

    html {
        background: #070816;
        scroll-behavior: smooth;
    }

    /*
     * The list of a drop-down is drawn by the browser and cannot be translucent: it gets a solid iOS dark grey so the
     * choices can be read. The dark scheme also darkens date pickers and scrollbars in fields.
     */
    select,
    input,
    textarea {
        color-scheme: dark;
    }

    select option,
    select optgroup {
        background-color: #1c1c1e;
        color: #f5f5f7;
    }

    select option:checked {
        background-color: rgb(var(--pd-accent-500));
        color: #ffffff;
    }

    select option:disabled {
        color: #636366;
    }

    body {
        ${tw`font-sans text-neutral-200 antialiased`};
        min-height: 100vh;
        background: transparent;
        -webkit-font-smoothing: antialiased;
    }

    /*
     * The wallpaper: soft, saturated colour glows on a deep base, like the iOS 26/27 wallpapers. It is made only of
     * gradients on one fixed layer, painted once: no "filter: blur" and no animation, so it costs nothing while
     * scrolling. The glows are already soft, so a translucent card over them looks frosted without blurring anything.
     */
    body::before {
        content: '';
        position: fixed;
        inset: 0;
        z-index: -1;
        pointer-events: none;
        background:
            radial-gradient(55% 45% at 12% 8%, rgb(var(--pd-accent-500) / 0.55), transparent 70%),
            radial-gradient(45% 40% at 88% 12%, rgba(191, 90, 242, 0.45), transparent 70%),
            radial-gradient(50% 45% at 82% 92%, rgba(255, 55, 95, 0.38), transparent 70%),
            radial-gradient(45% 45% at 8% 88%, rgba(100, 210, 255, 0.32), transparent 70%),
            linear-gradient(160deg, #0b1030 0%, #160c2c 48%, #05060f 100%);
    }

    /*
     * Liquid Glass, used by everything that floats over the page (navigation, menus, sheets). A light tint, a blur of
     * what is behind, a bright rim on the top edge (the "specular" highlight) and a soft drop shadow.
     */
    .lg-glass {
        background: linear-gradient(180deg, rgba(255, 255, 255, 0.14) 0%, rgba(255, 255, 255, 0.06) 100%);
        border: 1px solid rgba(255, 255, 255, 0.18);
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.4), inset 0 -1px 0 rgba(255, 255, 255, 0.06),
            0 12px 36px -12px rgba(0, 0, 0, 0.55);
        -webkit-backdrop-filter: saturate(180%) blur(22px);
        backdrop-filter: saturate(180%) blur(22px);
    }

    /* The capsules can scroll sideways on small screens: no scroll bar inside the glass. */
    .lg-glass {
        scrollbar-width: none;
    }

    .lg-glass::-webkit-scrollbar {
        display: none;
    }

    /* Thicker glass for sheets and menus that hold text over the page. */
    .lg-glass-thick {
        background: linear-gradient(180deg, rgba(58, 58, 68, 0.78) 0%, rgba(34, 34, 42, 0.82) 100%);
        border: 1px solid rgba(255, 255, 255, 0.16);
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.3), 0 24px 60px -16px rgba(0, 0, 0, 0.7);
        -webkit-backdrop-filter: saturate(180%) blur(28px);
        backdrop-filter: saturate(180%) blur(28px);
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
        background: rgb(var(--pd-accent-500) / 0.45);
        color: #fff;
    }

    h1, h2, h3, h4, h5, h6 {
        ${tw`font-semibold font-header`};
        letter-spacing: -0.01em;
    }

    p {
        ${tw`text-neutral-200 leading-snug font-sans`};
    }

    form {
        ${tw`m-0`};
    }

    textarea, select, input, button, button:focus, button:focus-visible {
        ${tw`outline-none`};
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

    /* Thin, rounded, translucent scroll bars, like iOS. */
    ::-webkit-scrollbar {
        background: none;
        width: 10px;
        height: 10px;
    }

    ::-webkit-scrollbar-thumb {
        border: solid 3px transparent;
        border-radius: 9999px;
        background-clip: padding-box;
        background-color: rgba(255, 255, 255, 0.18);
        transition: background-color 150ms ease;
    }

    ::-webkit-scrollbar-thumb:hover {
        background-color: rgb(var(--pd-accent-500) / 0.7);
    }

    ::-webkit-scrollbar-track-piece {
        margin: 4px 0;
    }

    ::-webkit-scrollbar-corner {
        background: transparent;
    }
`;
