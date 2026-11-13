// The default picture of an account: a soft gradient circle, its colours taken from the name, until the person chooses a
// picture of their own. (The panel used to show its logo there.)
export const OLD_DEFAULT_AVATAR = '/assets/svgs/pterodactyl.svg';

const PAIRS: [string, string][] = [
    ['#0070f3', '#7928ca'],
    ['#7928ca', '#ff0080'],
    ['#ff4d4d', '#f9cb28'],
    ['#007cf0', '#00dfd8'],
    ['#50e3c2', '#0070f3'],
    ['#f5a623', '#ff0080'],
    ['#3ecf8e', '#0070f3'],
    ['#a1a1a1', '#2e2e2e'],
];

export const isCustomAvatar = (avatar?: string | null): avatar is string =>
    !!avatar && !avatar.endsWith(OLD_DEFAULT_AVATAR);

const gradient = (seed: string): string => {
    let hash = 0;
    for (let i = 0; i < seed.length; i++) hash = (hash * 31 + seed.charCodeAt(i)) | 0;
    const [from, to] = PAIRS[Math.abs(hash) % PAIRS.length];
    const svg =
        `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1">` +
        `<stop offset="0" stop-color="${from}"/><stop offset="1" stop-color="${to}"/></linearGradient></defs>` +
        `<circle cx="32" cy="32" r="32" fill="url(#g)"/></svg>`;

    return `data:image/svg+xml;utf8,${encodeURIComponent(svg)}`;
};

// The picture to show: the one the person chose, or their gradient.
export const avatarFor = (avatar: string | null | undefined, seed: string): string =>
    isCustomAvatar(avatar) ? avatar : gradient(seed || '?');
