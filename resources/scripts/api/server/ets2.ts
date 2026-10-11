import http from '@/api/http';
import getFileContents from '@/api/server/files/getFileContents';
import saveFileContents from '@/api/server/files/saveFileContents';
import loadDirectory, { FileObject } from '@/api/server/files/loadDirectory';

/**
 * Whether this server is a Euro Truck Simulator 2 (or ATS, same tool) dedicated server, guessed from its startup.
 */
export const detectEts2 = async (uuid: string): Promise<boolean> => {
    try {
        const { data } = await http.get(`/api/client/servers/${uuid}/startup`);
        const hay = [
            data.meta?.startup_command || '',
            ...Object.values(data.meta?.docker_images || {}),
            ...((data.data || []).map((r: any) => `${r.attributes?.env_variable || ''} ${r.attributes?.server_value || ''} ${r.attributes?.default_value || ''}`)),
        ]
            .join(' ')
            .toLowerCase();

        return /euro truck|american truck|eut2|ats2|\bets2\b|\b227300\b|\b1948160\b|\b270880\b|\b2239530\b/.test(hay);
    } catch {
        return false;
    }
};

// Reads one "key: value" from a .sii file. Strings keep their quotes stripped.
export const getSii = (text: string, key: string): string | null => {
    const match = text.match(new RegExp('^\\s*' + key + ':\\s*(.*?)\\s*$', 'm'));
    if (!match) {
        return null;
    }
    let value = match[1];
    if (value.startsWith('"') && value.endsWith('"')) {
        value = value.slice(1, -1);
    }

    return value;
};

// Writes one "key: value", keeping the line's indentation. Leaves the file untouched when the key is not there.
export const setSii = (text: string, key: string, value: string, quoted: boolean): string => {
    const re = new RegExp('^(\\s*)' + key + ':\\s*.*$', 'm');
    const line = `${key}: ${quoted ? '"' + value.replace(/"/g, '') + '"' : value}`;

    return re.test(text) ? text.replace(re, (_m, indent) => indent + line) : text;
};

const CONFIG_CANDIDATES = [
    '/server_config.sii',
    '/.local/share/Euro Truck Simulator 2/server_config.sii',
    '/.local/share/American Truck Simulator/server_config.sii',
    '/server/server_config.sii',
];

/**
 * Finds the server_config.sii, trying the usual spots. Returns its path, or null when it is not there yet (the server
 * writes it the first time it starts).
 */
export const findConfig = async (uuid: string): Promise<string | null> => {
    for (const path of CONFIG_CANDIDATES) {
        try {
            await getFileContents(uuid, path);

            return path;
        } catch {
            /* try the next spot */
        }
    }
    try {
        const files = await loadDirectory(uuid, '/');
        if (files.some((f) => f.isFile && f.name === 'server_config.sii')) {
            return '/server_config.sii';
        }
    } catch {
        /* ignore */
    }

    return null;
};

export const readConfig = (uuid: string, path: string): Promise<string> => getFileContents(uuid, path);

export const writeConfig = (uuid: string, path: string, text: string): Promise<void> => saveFileContents(uuid, path, text);

const dirName = (path: string): string => {
    const i = path.lastIndexOf('/');

    return i <= 0 ? '/' : path.slice(0, i);
};

/**
 * The folder that holds the server's mods (next to server_config.sii), and the .scs files in it.
 */
export const findMods = async (configPath: string, uuid: string): Promise<{ dir: string; files: FileObject[] }> => {
    const dir = `${dirName(configPath)}/mod`.replace('//', '/');
    try {
        const files = await loadDirectory(uuid, dir);

        return { dir, files: files.filter((f) => f.isFile && /\.scs(\.off)?$/i.test(f.name)) };
    } catch {
        return { dir, files: [] };
    }
};
