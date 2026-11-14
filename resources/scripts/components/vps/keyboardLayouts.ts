// Typing a text into a machine as if the keys were pressed: the console sends physical keys, and the system of the
// machine turns them into characters with its own keyboard layout. So for each character we find the key (and Shift or
// AltGr) that gives it on the layout of the machine.

export type Layout = 'fr' | 'us';

export const LAYOUTS: { value: Layout; label: string }[] = [
    { value: 'fr', label: 'AZERTY (FR)' },
    { value: 'us', label: 'QWERTY (US)' },
];

// [without Shift, with Shift, with AltGr] for each physical key (KeyboardEvent.code).
type Keys = Record<string, [string, string, string?]>;

const letters = (map: Record<string, string>): Keys =>
    Object.fromEntries(Object.entries(map).map(([code, char]) => [code, [char, char.toUpperCase()]]));

// The US layout: also what QEMU uses to turn a keysym into a key when the console cannot send the key itself.
const US: Keys = {
    ...letters(Object.fromEntries('abcdefghijklmnopqrstuvwxyz'.split('').map((c) => [`Key${c.toUpperCase()}`, c]))),
    Backquote: ['`', '~'],
    Digit1: ['1', '!'],
    Digit2: ['2', '@'],
    Digit3: ['3', '#'],
    Digit4: ['4', '$'],
    Digit5: ['5', '%'],
    Digit6: ['6', '^'],
    Digit7: ['7', '&'],
    Digit8: ['8', '*'],
    Digit9: ['9', '('],
    Digit0: ['0', ')'],
    Minus: ['-', '_'],
    Equal: ['=', '+'],
    BracketLeft: ['[', '{'],
    BracketRight: [']', '}'],
    Backslash: ['\\', '|'],
    Semicolon: [';', ':'],
    Quote: ["'", '"'],
    Comma: [',', '<'],
    Period: ['.', '>'],
    Slash: ['/', '?'],
    IntlBackslash: ['\\', '|'],
    Space: [' ', ' '],
};

// The French AZERTY layout (as on Linux).
const FR: Keys = {
    ...letters(Object.fromEntries('bcdefghijklnoprstuvxy'.split('').map((c) => [`Key${c.toUpperCase()}`, c]))),
    KeyQ: ['a', 'A'],
    KeyW: ['z', 'Z'],
    KeyA: ['q', 'Q'],
    KeyZ: ['w', 'W'],
    Semicolon: ['m', 'M'],
    Backquote: ['²', '²'],
    Digit1: ['&', '1'],
    Digit2: ['é', '2', '~'],
    Digit3: ['"', '3', '#'],
    Digit4: ["'", '4', '{'],
    Digit5: ['(', '5', '['],
    Digit6: ['-', '6', '|'],
    Digit7: ['è', '7', '`'],
    Digit8: ['_', '8', '\\'],
    Digit9: ['ç', '9', '^'],
    Digit0: ['à', '0', '@'],
    Minus: [')', '°', ']'],
    Equal: ['=', '+', '}'],
    BracketRight: ['$', '£', '¤'],
    Quote: ['ù', '%'],
    Backslash: ['*', 'µ'],
    KeyM: [',', '?'],
    Comma: [';', '.'],
    Period: [':', '/'],
    Slash: ['!', '§'],
    IntlBackslash: ['<', '>'],
    Space: [' ', ' '],
};

const TABLES: Record<Layout, Keys> = { fr: FR, us: US };

export interface Stroke {
    code: string;
    shift: boolean;
    altgr: boolean;
    // The keysym sent with the key: the US character of this key, so QEMU finds the same key even without the codes.
    keysym: number;
}

const symOf = (char: string): number => {
    const code = char.codePointAt(0) || 0;

    return code < 0x100 ? code : 0x01000000 | code;
};

/**
 * The key to press for a character on the layout of the machine, or null when the layout has no key for it.
 */
export const strokeFor = (char: string, layout: Layout): Stroke | null => {
    const table = TABLES[layout];
    for (const [code, [plain, shifted, altgr]] of Object.entries(table)) {
        const level = plain === char ? 0 : shifted === char ? 1 : altgr === char ? 2 : -1;
        if (level < 0) continue;
        const us = US[code];
        const shift = level === 1;

        return {
            code,
            shift,
            altgr: level === 2,
            keysym: symOf(us ? (shift ? us[1] : us[0]) : char),
        };
    }

    return null;
};

export const defaultLayout = (): Layout => {
    try {
        const saved = window.localStorage.getItem('pd:vps-layout');
        if (saved === 'fr' || saved === 'us') return saved;
    } catch {
        // Not kept.
    }
    const language = (document.documentElement.lang || navigator.language || '').toLowerCase();

    return language.startsWith('fr') ? 'fr' : 'us';
};

export const saveLayout = (layout: Layout): void => {
    try {
        window.localStorage.setItem('pd:vps-layout', layout);
    } catch {
        // Not kept.
    }
};
