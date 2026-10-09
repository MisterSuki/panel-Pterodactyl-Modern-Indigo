import http from '@/api/http';

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

const facets = (loaderId: string, gameVersion: string): string => {
    // On Modrinth the loader (a "category") decides plugin vs mod — most server add-ons are project_type "mod"
    // regardless — so we filter by the loader only. The install folder is chosen from the loader's kind, not the type.
    const groups: string[][] = [[`categories:${loaderId}`]];
    if (gameVersion.trim() !== '') {
        groups.push([`versions:${gameVersion.trim()}`]);
    }

    return JSON.stringify(groups);
};

/**
 * Searches Modrinth for plugins or mods matching the loader (and game version, when given). Runs straight from the
 * browser against Modrinth's public API — nothing is sent through the panel.
 */
export const searchModrinth = async (query: string, loaderId: string, gameVersion: string): Promise<ModrinthHit[]> => {
    const params = new URLSearchParams({
        query,
        limit: '20',
        index: 'relevance',
        facets: facets(loaderId, gameVersion),
    });
    const response = await fetch(`${MODRINTH}/search?${params.toString()}`, {
        headers: { Accept: 'application/json' },
    });
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
}

/**
 * The newest file of a project that fits the loader (and game version, when given), ready to download onto the server.
 */
export const resolveDownload = async (
    projectId: string,
    loaderId: string,
    gameVersion: string
): Promise<ResolvedDownload | null> => {
    const params = new URLSearchParams({ loaders: JSON.stringify([loaderId]) });
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

    return { url: file.url, filename: file.filename, versionName: version.version_number || '' };
};

/**
 * Asks Wings to download a file from a URL straight onto the server (used to install a plugin or mod).
 */
export const pullFile = (uuid: string, url: string, directory: string, filename?: string): Promise<void> => {
    return http
        .post(`/api/client/servers/${uuid}/files/pull`, {
            url,
            directory,
            filename,
            use_header: true,
            foreground: true,
        })
        .then(() => undefined);
};

/**
 * Sends one command to the running server (used for the whitelist, operators and bans).
 */
export const sendCommand = (uuid: string, command: string): Promise<void> => {
    return http.post(`/api/client/servers/${uuid}/command`, { command }).then(() => undefined);
};
