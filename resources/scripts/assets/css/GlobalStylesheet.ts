import tw from 'twin.macro';
import { createGlobalStyle } from 'styled-components/macro';

export default createGlobalStyle`
    html {
        background: #000;
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
        background-color: #0a84ff;
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
     * The wallpaper: two big, heavily blurred colour blobs that drift slowly behind everything, like the iOS
     * backgrounds. Every translucent surface lets them glow through, which gives the frosted-glass look. They live on
     * fixed layers and only move with "transform", so the browser animates them without repainting the page.
     */
    body::before,
    body::after {
        content: '';
        position: fixed;
        z-index: -1;
        width: 88vmax;
        height: 88vmax;
        border-radius: 50%;
        pointer-events: none;
        filter: blur(90px);
        will-change: transform;
    }

    body::before {
        top: -28vmax;
        left: -18vmax;
        opacity: 0.7;
        background:
            radial-gradient(circle at 32% 32%, #0a84ff 0%, rgba(10, 132, 255, 0) 58%),
            radial-gradient(circle at 70% 68%, #5e5ce6 0%, rgba(94, 92, 230, 0) 60%);
        animation: pd-blob-a 30s ease-in-out infinite alternate;
    }

    body::after {
        bottom: -32vmax;
        right: -22vmax;
        opacity: 0.55;
        background:
            radial-gradient(circle at 38% 40%, #ff375f 0%, rgba(255, 55, 95, 0) 55%),
            radial-gradient(circle at 66% 62%, #64d2ff 0%, rgba(100, 210, 255, 0) 60%);
        animation: pd-blob-b 36s ease-in-out infinite alternate;
    }

    @keyframes pd-blob-a {
        0% { transform: translate(0, 0) scale(1) rotate(0deg); }
        50% { transform: translate(18vw, 12vh) scale(1.15) rotate(40deg); }
        100% { transform: translate(-6vw, 26vh) scale(0.95) rotate(-20deg); }
    }

    @keyframes pd-blob-b {
        0% { transform: translate(0, 0) scale(1) rotate(0deg); }
        50% { transform: translate(-16vw, -14vh) scale(1.2) rotate(-35deg); }
        100% { transform: translate(10vw, -24vh) scale(0.9) rotate(25deg); }
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
        background: rgba(10, 132, 255, 0.45);
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
        background-color: rgba(10, 132, 255, 0.7);
    }

    ::-webkit-scrollbar-track-piece {
        margin: 4px 0;
    }

    ::-webkit-scrollbar-corner {
        background: transparent;
    }
`;
