/**
 * Given a valid six character HEX color code, converts it into its associated
 * RGBA value with a user controllable alpha channel.
 */
function hexToRgba(hex: string, alpha = 1): string {
    // noinspection RegExpSimplifiable
    if (!/#?([a-fA-F0-9]{2}){3}/.test(hex)) {
        return hex;
    }

    // noinspection RegExpSimplifiable
    const [r, g, b] = hex.match(/[a-fA-F0-9]{2}/g)!.map((v) => parseInt(v, 16));

    return `rgba(${r}, ${g}, ${b}, ${alpha})`;
}

/**
 * The accent colour (see /js/appearance.js) as a hex value, for the places that cannot read a CSS variable, such as the
 * charts drawn on a canvas.
 */
function accentHex(shade: 50 | 100 | 200 | 300 | 400 | 500 | 600 | 700 | 800 | 900 = 400): string {
    try {
        const raw = getComputedStyle(document.documentElement).getPropertyValue(`--pd-accent-${shade}`).trim();
        const parts = raw.split(/[\s,]+/).map(Number);
        if (parts.length >= 3 && parts.slice(0, 3).every((v) => Number.isFinite(v))) {
            return '#' + parts.slice(0, 3).map((v) => Math.round(v).toString(16).padStart(2, '0')).join('');
        }
    } catch {
        // No document (tests): the default blue below.
    }

    return '#3b9dff';
}

export { hexToRgba, accentHex };
