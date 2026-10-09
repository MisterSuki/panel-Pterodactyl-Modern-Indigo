import http from '@/api/http';
import getFileContents from '@/api/server/files/getFileContents';
import saveFileContents from '@/api/server/files/saveFileContents';

// The loaders we offer, and where their files live. Plugins go in /plugins (Bukkit family), mods in /mods (Fabric family).
export interface Loader {
    id: string;
    label: string;
    kind: 'plugin' | 'mod';
}

export const LOADERS: Loader[] = [
    { id: 'paper', label: 'Paper', kind: 'plugin' },
    { id: 'purpur', label: 'Purpur', kind: 'plugin' },
    { id: 'spigot', label: 'Spigot', kind: 'plugin' },
    { id: 'bukkit', label: 'Bukkit', kind: 'plugin' },
    { id: 'folia', label: 'Folia', kind: 'plugin' },
    { id: 'fabric', label: 'Fabric', kind: 'mod' },
    { id: 'forge', label: 'Forge', kind: 'mod' },
    { id: 'neoforge', label: 'NeoForge', kind: 'mod' },
    { id: 'quilt', label: 'Quilt', kind: 'mod' },
];

// The kinds of content a person can browse. Plugins/mods use the loader; the others are chosen by type.
export type ContentType = 'content' | 'modpack' | 'resourcepack' | 'datapack';

export const directoryForLoader = (loaderId: string): string => {
    const loader = LOADERS.find((l) => l.id === loaderId);

    return loader?.kind === 'mod' ? '/mods' : '/plugins';
};

export interface ModrinthHit {
    projectId: string;
    slug: string;
    title: string;
    description: string;
    author: string;
    downloads: number;
    iconUrl: string | null;
    categories: string[];
    projectType: string;
}

const MODRINTH = 'https://api.modrinth.com/v2';

const facetsFor = (type: ContentType, loaderId: string, gameVersion: string): string => {
    const groups: string[][] = [];
    if (type === 'content') {
        // On Modrinth the loader (a "category") decides plugin vs mod; filter by the loader only.
        groups.push([`categories:${loaderId}`]);
    } else if (type === 'modpack') {
        groups.push(['project_type:modpack'], [`categories:${loaderId}`]);
    } else if (type === 'resourcepack') {
        groups.push(['project_type:resourcepack']);
    } else if (type === 'datapack') {
        groups.push(['project_type:datapack']);
    }
    if (gameVersion.trim() !== '') {
        groups.push([`versions:${gameVersion.trim()}`]);
    }

    return JSON.stringify(groups);
};

/**
 * Searches Modrinth. With no words it returns the most downloaded projects (a catalogue to browse). Runs straight from
 * the browser against Modrinth's public API — nothing is sent through the panel.
 */
export const searchModrinth = async (
    query: string,
    type: ContentType,
    loaderId: string,
    gameVersion: string
): Promise<ModrinthHit[]> => {
    const params = new URLSearchParams({
        query: query.trim(),
        limit: '30',
        index: query.trim() === '' ? 'downloads' : 'relevance',
        facets: facetsFor(type, loaderId, gameVersion),
    });
    const response = await fetch(`${MODRINTH}/search?${params.toString()}`, { headers: { Accept: 'application/json' } });
    if (!response.ok) {
        throw new Error('Modrinth is not reachable right now. Try again in a moment.');
    }
    const data = await response.json();

    return (data.hits || []).map((hit: any) => ({
        projectId: hit.project_id,
        slug: hit.slug,
        title: hit.title,
        description: hit.description,
        author: hit.author,
        downloads: hit.downloads,
        iconUrl: hit.icon_url || null,
        categories: hit.categories || [],
        projectType: hit.project_type,
    }));
};

export interface ResolvedDownload {
    url: string;
    filename: string;
    versionName: string;
    sha1: string | null;
}

/**
 * The newest file of a project that fits the given loaders (omit for resource packs) and game version.
 */
export const resolveDownload = async (
    projectId: string,
    loaders: string[] | null,
    gameVersion: string
): Promise<ResolvedDownload | null> => {
    const params = new URLSearchParams();
    if (loaders && loaders.length > 0) {
        params.set('loaders', JSON.stringify(loaders));
    }
    if (gameVersion.trim() !== '') {
        params.set('game_versions', JSON.stringify([gameVersion.trim()]));
    }
    const response = await fetch(`${MODRINTH}/project/${encodeURIComponent(projectId)}/version?${params.toString()}`, {
        headers: { Accept: 'application/json' },
    });
    if (!response.ok) {
        throw new Error('The versions of this project could not be read from Modrinth.');
    }
    const versions = await response.json();
    if (!Array.isArray(versions) || versions.length === 0) {
        return null;
    }
    const version = versions[0];
    const file = (version.files || []).find((f: any) => f.primary) || (version.files || [])[0];
    if (!file) {
        return null;
    }

    return {
        url: file.url,
        filename: file.filename,
        versionName: version.version_number || '',
        sha1: file.hashes?.sha1 || null,
    };
};

/**
 * Asks Wings to download a file from a URL straight onto the server (used to install a plugin, mod or datapack).
 */
export const pullFile = (uuid: string, url: string, directory: string, filename?: string): Promise<void> => {
    return http
        .post(`/api/client/servers/${uuid}/files/pull`, { url, directory, filename, use_header: true, foreground: true })
        .then(() => undefined);
};

/**
 * Sends one command to the running server (used for the whitelist, operators and bans).
 */
export const sendCommand = (uuid: string, command: string): Promise<void> => {
    return http.post(`/api/client/servers/${uuid}/command`, { command }).then(() => undefined);
};

// ---- server.properties ---------------------------------------------------------------------------------------------

const readProperties = async (uuid: string): Promise<Record<string, string>> => {
    try {
        const raw = await getFileContents(uuid, '/server.properties');
        const out: Record<string, string> = {};
        raw.split('\n').forEach((line) => {
            const trimmed = line.trim();
            if (trimmed === '' || trimmed.startsWith('#')) return;
            const eq = trimmed.indexOf('=');
            if (eq === -1) return;
            out[trimmed.slice(0, eq).trim()] = trimmed.slice(eq + 1);
        });

        return out;
    } catch {
        return {};
    }
};

/**
 * The world folder name, so a datapack goes in the right place. Defaults to "world".
 */
export const levelName = async (uuid: string): Promise<string> => {
    const props = await readProperties(uuid);

    return (props['level-name'] || 'world').trim() || 'world';
};

/**
 * Points the server at a resource pack by writing its URL (and checksum) into server.properties. A running server must
 * be restarted for players to get it. Lines that are already there keep their place; the two keys are set or added.
 */
export const setResourcePack = async (uuid: string, url: string, sha1: string | null): Promise<void> => {
    let raw = '';
    try {
        raw = await getFileContents(uuid, '/server.properties');
    } catch {
        raw = '';
    }
    const lines = raw.split('\n');
    const set = (key: string, value: string) => {
        const index = lines.findIndex((l) => l.trim().startsWith(key + '='));
        if (index === -1) {
            lines.push(`${key}=${value}`);
        } else {
            lines[index] = `${key}=${value}`;
        }
    };
    set('resource-pack', url);
    set('resource-pack-sha1', sha1 || '');
    await saveFileContents(uuid, '/server.properties', lines.join('\n'));
};

// ---- auto-detection from the egg -----------------------------------------------------------------------------------

const LOADER_KEYWORDS: { id: string; needle: string }[] = [
    { id: 'purpur', needle: 'purpur' },
    { id: 'folia', needle: 'folia' },
    { id: 'paper', needle: 'paper' },
    { id: 'spigot', needle: 'spigot' },
    { id: 'bukkit', needle: 'bukkit' },
    { id: 'fabric', needle: 'fabric' },
    { id: 'quilt', needle: 'quilt' },
    { id: 'neoforge', needle: 'neoforge' },
    { id: 'forge', needle: 'forge' },
];

/**
 * Guesses the loader and Minecraft version from the server's startup (its variables, command and image). Best-effort:
 * returns what it can, and the UI keeps a dropdown to correct it. Needs the "startup.read" permission.
 */
export const detectStartup = async (uuid: string): Promise<{ loader: string | null; gameVersion: string | null }> => {
    try {
        const { data } = await http.get(`/api/client/servers/${uuid}/startup`);
        const rows = (data.data || []).map((r: any) => r.attributes || {});
        const haystack = [
            data.meta?.startup_command || '',
            ...rows.map((v: any) => `${v.env_variable || ''} ${v.server_value || ''} ${v.default_value || ''}`),
            ...Object.values(data.meta?.docker_images || {}),
        ]
            .join(' ')
            .toLowerCase();

        const loader = LOADER_KEYWORDS.find((l) => haystack.includes(l.needle))?.id ?? null;

        let gameVersion: string | null = null;
        const versioned = rows.find((v: any) => /version/i.test(v.env_variable || '') && /^\d+\.\d+/.test(String(v.server_value || '')));
        if (versioned) {
            gameVersion = String(versioned.server_value).trim();
        } else {
            const match = haystack.match(/\b1\.(?:[1-9]\d|\d)(?:\.\d+)?\b/);
            gameVersion = match ? match[0] : null;
        }

        return { loader, gameVersion };
    } catch {
        return { loader: null, gameVersion: null };
    }
};
