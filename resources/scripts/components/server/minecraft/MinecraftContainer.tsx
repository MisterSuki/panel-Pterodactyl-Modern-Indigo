import React, { useCallback, useEffect, useState } from 'react';
import tw from 'twin.macro';
import classNames from 'classnames';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import {
    faBan,
    faBoxOpen,
    faDownload,
    faImage,
    faPlus,
    faPowerOff,
    faPuzzlePiece,
    faScroll,
    faSearch,
    faTrash,
    faUpload,
    faUserShield,
    faUsers,
    faCircleNotch,
} from '@fortawesome/free-solid-svg-icons';
import { ServerContext } from '@/state/server';
import ServerContentBlock from '@/components/elements/ServerContentBlock';
import { Button } from '@/components/elements/button/index';
import { Link, useRouteMatch } from 'react-router-dom';
import {
    CATEGORIES,
    ContentType,
    GameVersion,
    LOADERS,
    ModrinthHit,
    SORTS,
    detectStartup,
    getGameVersions,
    directoryForLoader,
    levelName,
    pullFile,
    resolveDownload,
    searchModrinth,
    sendCommand,
    setResourcePack,
} from '@/api/server/minecraft';
import loadDirectory, { FileObject } from '@/api/server/files/loadDirectory';
import deleteFiles from '@/api/server/files/deleteFiles';
import renameFiles from '@/api/server/files/renameFiles';
import getFileContents from '@/api/server/files/getFileContents';

type Tab = 'content' | 'players';

const field = tw`rounded-lg border border-white/10 bg-neutral-900 px-3 py-2 text-sm text-neutral-50 outline-none focus:border-primary-400`;
const cardPad = { padding: '1.25rem' } as React.CSSProperties;
const card = tw`rounded-2xl border border-white/5 bg-neutral-800 shadow-card`;

const clamp2 = {
    display: '-webkit-box',
    WebkitLineClamp: 2,
    WebkitBoxOrient: 'vertical',
    overflow: 'hidden',
} as React.CSSProperties;

const fmtDownloads = (n: number): string =>
    n >= 1e6 ? (n / 1e6).toFixed(1) + 'M' : n >= 1e3 ? Math.round(n / 1e3) + 'k' : String(n);

const TYPES: { id: ContentType; label: string; icon: any }[] = [
    { id: 'content', label: 'Plugins & Mods', icon: faPuzzlePiece },
    { id: 'modpack', label: 'Modpacks', icon: faBoxOpen },
    { id: 'resourcepack', label: 'Resource packs', icon: faImage },
    { id: 'datapack', label: 'Data packs', icon: faScroll },
];

const prefsKey = (uuid: string) => `mc:${uuid}`;
const loadPrefs = (uuid: string): any => {
    try {
        return JSON.parse(localStorage.getItem(prefsKey(uuid)) || '{}') || {};
    } catch {
        return {};
    }
};
const savePrefs = (uuid: string, prefs: any) => {
    try {
        localStorage.setItem(prefsKey(uuid), JSON.stringify(prefs));
    } catch {
        /* private mode: the choice is simply not remembered */
    }
};

// ---- Plugins / mods / modpacks / resource packs / data packs -------------------------------------------------------

const ContentTab = ({
    uuid,
    type,
    loader,
    gameVersion,
    onError,
    onNotice,
}: {
    uuid: string;
    type: ContentType;
    loader: string;
    gameVersion: string;
    onError: (m: string) => void;
    onNotice: (m: string) => void;
}) => {
    const match = useRouteMatch();
    const usesLoader = type === 'content' || type === 'modpack';

    const [query, setQuery] = useState('');
    const [sort, setSort] = useState('downloads');
    const [category, setCategory] = useState('');
    const [results, setResults] = useState<ModrinthHit[] | null>(null);
    const [searching, setSearching] = useState(false);
    const [installing, setInstalling] = useState<string | null>(null);

    // The right-hand panel shows what is already installed (jars, data packs) or the current resource pack.
    const [installed, setInstalled] = useState<FileObject[] | null>(null);
    const [dataDir, setDataDir] = useState<string>('/plugins');
    const [currentPack, setCurrentPack] = useState<string | null>(null);

    const runSearch = useCallback(
        (q: string) => {
            setSearching(true);
            onError('');
            searchModrinth(q, type, loader, gameVersion, { sort, category })
                .then(setResults)
                .catch((e) => onError(e.message || 'The search failed.'))
                .then(() => setSearching(false));
        },
        [type, loader, gameVersion, sort, category]
    );

    // Browse the catalogue whenever the type/loader/version/sort/category changes (before any search).
    useEffect(() => {
        setResults(null);
        runSearch('');
    }, [type, loader, gameVersion, sort, category]);

    const refreshInstalled = useCallback(() => {
        if (type === 'resourcepack') {
            getFileContents(uuid, '/server.properties')
                .then((raw) => {
                    const line = raw.split('\n').find((l) => l.trim().startsWith('resource-pack='));
                    const url = line ? line.slice(line.indexOf('=') + 1).trim() : '';
                    setCurrentPack(url || null);
                })
                .catch(() => setCurrentPack(null));

            return;
        }
        if (type === 'modpack') return;

        const compute = (dir: string) => {
            setDataDir(dir);
            loadDirectory(uuid, dir)
                .then((files) =>
                    setInstalled(
                        files
                            .filter((f) => f.isFile && /\.(jar|zip)(\.disabled)?$/i.test(f.name))
                            .sort((a, b) => a.name.localeCompare(b.name))
                    )
                )
                .catch(() => setInstalled([]));
        };

        if (type === 'datapack') {
            levelName(uuid).then((level) => compute(`/${level}/datapacks`));
        } else {
            compute(directoryForLoader(loader));
        }
    }, [uuid, type, loader]);

    useEffect(() => {
        setInstalled(null);
        setCurrentPack(null);
        refreshInstalled();
    }, [refreshInstalled]);

    const install = (hit: ModrinthHit) => {
        setInstalling(hit.projectId);
        onError('');

        const loaders = type === 'datapack' ? ['datapack'] : type === 'resourcepack' ? null : [loader];

        resolveDownload(hit.projectId, loaders, gameVersion)
            .then(async (file) => {
                if (!file) {
                    throw new Error(
                        `"${hit.title}" has no build for ${usesLoader ? loader + ' ' : ''}${gameVersion || 'this version'}. Try another version.`
                    );
                }
                if (type === 'resourcepack') {
                    await setResourcePack(uuid, file.url, file.sha1);
                    return 'The resource pack is set in server.properties. Restart the server to apply it.';
                }
                if (type === 'modpack') {
                    await pullFile(uuid, file.url, '/', file.filename);
                    return `${file.filename} was downloaded to the server root. A modpack needs a modpack egg to be applied.`;
                }
                if (type === 'datapack') {
                    const dir = `/${await levelName(uuid)}/datapacks`;
                    await pullFile(uuid, file.url, dir, file.filename);
                    return `${file.filename} was installed into ${dir}. Run /reload or restart the server.`;
                }
                const dir = directoryForLoader(loader);
                await pullFile(uuid, file.url, dir, file.filename);
                return `${file.filename} was installed into ${dir}. Restart the server to load it.`;
            })
            .then((message) => {
                onNotice(message);
                setTimeout(refreshInstalled, 1500);
            })
            .catch((e) => onError(e.message || 'The install failed.'))
            .then(() => setInstalling(null));
    };

    const toggle = (file: FileObject) => {
        const off = file.name.endsWith('.disabled');
        const to = off ? file.name.replace(/\.disabled$/, '') : `${file.name}.disabled`;
        renameFiles(uuid, dataDir, [{ from: file.name, to }])
            .then(refreshInstalled)
            .catch((e) => onError(e.message || 'Could not change the file.'));
    };

    const remove = (file: FileObject) => {
        if (!window.confirm(`Delete ${file.name}? This cannot be undone.`)) return;
        deleteFiles(uuid, dataDir, [file.name])
            .then(refreshInstalled)
            .catch((e) => onError(e.message || 'Could not delete the file.'));
    };

    const clearPack = () => {
        setResourcePack(uuid, '', null)
            .then(() => {
                onNotice('The resource pack was removed from server.properties.');
                refreshInstalled();
            })
            .catch((e) => onError(e.message || 'Could not update server.properties.'));
    };

    return (
        <div css={tw`grid grid-cols-1 lg:grid-cols-2 gap-4`}>
            <div css={card} style={{ padding: '1.5rem' }}>
                <div css={tw`flex items-center gap-2 mb-3`}>
                    <div css={tw`relative flex-1`}>
                        <FontAwesomeIcon
                            icon={faSearch}
                            css={tw`absolute left-3 top-1/2 -translate-y-1/2 text-neutral-500 text-sm`}
                        />
                        <input
                            value={query}
                            onChange={(e) => setQuery(e.currentTarget.value)}
                            onKeyDown={(e) => e.key === 'Enter' && runSearch(query)}
                            placeholder={'Search on Modrinth...'}
                            css={[field, tw`w-full pl-9`]}
                        />
                    </div>
                    <Button type={'button'} onClick={() => runSearch(query)} disabled={searching}>
                        {searching ? <FontAwesomeIcon icon={faCircleNotch} spin /> : <span>Search</span>}
                    </Button>
                </div>
                <div css={tw`flex flex-wrap items-center gap-2 mb-4`}>
                    <select value={sort} onChange={(e) => setSort(e.currentTarget.value)} css={[field, tw`py-1.5 text-xs`]}>
                        {SORTS.map((s) => (
                            <option key={s.id} value={s.id}>
                                {s.label}
                            </option>
                        ))}
                    </select>
                    <select
                        value={category}
                        onChange={(e) => setCategory(e.currentTarget.value)}
                        css={[field, tw`py-1.5 text-xs`]}
                    >
                        {CATEGORIES.map((c) => (
                            <option key={c.id} value={c.id}>
                                {c.label}
                            </option>
                        ))}
                    </select>
                    {results && (
                        <span css={tw`ml-auto text-2xs uppercase tracking-wide text-neutral-500`}>
                            {results.length} <span>results</span>
                        </span>
                    )}
                </div>
                {results === null ? (
                    <p css={tw`text-sm text-neutral-400 py-10 text-center`}>
                        <FontAwesomeIcon icon={faCircleNotch} spin />
                    </p>
                ) : results.length === 0 ? (
                    <p css={tw`text-sm text-neutral-400 py-10 text-center`}>Nothing found. Try other words.</p>
                ) : (
                    <div css={tw`flex flex-col gap-3 max-h-[40rem] overflow-y-auto pr-1`}>
                        {results.map((hit) => (
                            <div
                                key={hit.projectId}
                                css={tw`flex items-start gap-4 rounded-xl border border-white/5 bg-neutral-900/50 p-4 transition-colors duration-150 hover:border-primary-500/30`}
                            >
                                {hit.iconUrl ? (
                                    <img
                                        src={hit.iconUrl}
                                        alt={''}
                                        css={tw`w-14 h-14 rounded-xl flex-shrink-0 object-cover bg-neutral-800`}
                                    />
                                ) : (
                                    <div css={tw`w-14 h-14 rounded-xl flex-shrink-0 bg-primary-500/20`} />
                                )}
                                <div css={tw`min-w-0 flex-1`}>
                                    <p css={tw`text-base font-semibold text-neutral-100 truncate`}>{hit.title}</p>
                                    <p css={tw`text-xs text-neutral-400 mt-0.5`}>
                                        <span>by</span> {hit.author}
                                        <span css={tw`mx-1.5 text-neutral-600`}>&middot;</span>
                                        <FontAwesomeIcon icon={faDownload} css={tw`mr-1`} />
                                        {fmtDownloads(hit.downloads)}
                                    </p>
                                    <p css={tw`text-sm text-neutral-400 mt-1.5`} style={clamp2}>
                                        {hit.description}
                                    </p>
                                </div>
                                <Button
                                    type={'button'}
                                    onClick={() => install(hit)}
                                    disabled={installing !== null}
                                    css={tw`flex-shrink-0`}
                                >
                                    {installing === hit.projectId ? (
                                        <FontAwesomeIcon icon={faCircleNotch} spin />
                                    ) : (
                                        <>
                                            <FontAwesomeIcon icon={faDownload} css={tw`mr-1`} /> <span>Install</span>
                                        </>
                                    )}
                                </Button>
                            </div>
                        ))}
                    </div>
                )}
            </div>

            {/* Right panel: depends on the type. */}
            <div css={card} style={cardPad}>
                {type === 'modpack' ? (
                    <p css={tw`text-sm text-neutral-400`}>
                        <span>
                            Modpacks are downloaded to the server root as an .mrpack file. Applying one needs a modpack
                            egg (it installs the mods and configs for you).
                        </span>
                    </p>
                ) : type === 'resourcepack' ? (
                    <>
                        <h3 css={tw`text-sm font-semibold uppercase tracking-wide text-neutral-300 mb-3`}>
                            <span>Current resource pack</span>
                        </h3>
                        {currentPack ? (
                            <div css={tw`rounded-xl border border-white/5 bg-neutral-900/50 p-3`}>
                                <p css={tw`text-xs text-neutral-300 break-all`}>{currentPack}</p>
                                <button
                                    type={'button'}
                                    onClick={clearPack}
                                    css={tw`mt-2 text-xs text-red-400 hover:text-red-300`}
                                >
                                    <FontAwesomeIcon icon={faTrash} css={tw`mr-1`} /> <span>Remove</span>
                                </button>
                            </div>
                        ) : (
                            <p css={tw`text-sm text-neutral-400`}>No resource pack is set.</p>
                        )}
                    </>
                ) : (
                    <>
                        <div css={tw`flex items-center justify-between mb-3`}>
                            <h3 css={tw`text-sm font-semibold uppercase tracking-wide text-neutral-300`}>
                                <span>Installed in</span> <code css={tw`text-primary-300`}>{dataDir}</code>
                            </h3>
                            <Link
                                to={`${match.url.replace(/\/minecraft\/?$/, '')}/files#${encodeURIComponent(dataDir)}`}
                                css={tw`text-xs text-neutral-400 hover:text-primary-300`}
                            >
                                <FontAwesomeIcon icon={faUpload} css={tw`mr-1`} /> <span>Upload a file</span>
                            </Link>
                        </div>
                        {installed === null ? (
                            <p css={tw`text-sm text-neutral-400 py-6 text-center`}>
                                <FontAwesomeIcon icon={faCircleNotch} spin />
                            </p>
                        ) : installed.length === 0 ? (
                            <p css={tw`text-sm text-neutral-400 py-6 text-center`}>Nothing installed yet.</p>
                        ) : (
                            <div css={tw`flex flex-col gap-2 max-h-[30rem] overflow-y-auto`}>
                                {installed.map((file) => {
                                    const off = file.name.endsWith('.disabled');
                                    return (
                                        <div
                                            key={file.name}
                                            css={tw`flex items-center gap-3 rounded-xl border border-white/5 bg-neutral-900/50 px-3 py-2`}
                                        >
                                            <p
                                                css={[
                                                    tw`text-sm truncate flex-1`,
                                                    off ? tw`text-neutral-500 line-through` : tw`text-neutral-100`,
                                                ]}
                                            >
                                                {file.name.replace(/\.disabled$/, '')}
                                            </p>
                                            {type === 'content' && (
                                                <button
                                                    type={'button'}
                                                    title={off ? 'Enable' : 'Disable'}
                                                    onClick={() => toggle(file)}
                                                    css={[
                                                        tw`p-1.5 rounded-lg hover:bg-white/10`,
                                                        off ? tw`text-neutral-500` : tw`text-green-400`,
                                                    ]}
                                                >
                                                    <FontAwesomeIcon icon={faPowerOff} />
                                                </button>
                                            )}
                                            <button
                                                type={'button'}
                                                title={'Delete'}
                                                onClick={() => remove(file)}
                                                css={tw`p-1.5 rounded-lg text-red-400 hover:bg-white/10`}
                                            >
                                                <FontAwesomeIcon icon={faTrash} />
                                            </button>
                                        </div>
                                    );
                                })}
                            </div>
                        )}
                    </>
                )}
            </div>
        </div>
    );
};

// ---- Players (whitelist / ops / bans) ------------------------------------------------------------------------------

const readNames = async (uuid: string, file: string): Promise<string[]> => {
    try {
        const raw = await getFileContents(uuid, file);
        const data = JSON.parse(raw);
        if (!Array.isArray(data)) return [];
        return data.map((row: any) => (typeof row === 'string' ? row : row.name)).filter((n: any) => typeof n === 'string');
    } catch {
        return [];
    }
};

const PlayerList = ({
    uuid,
    icon,
    title,
    file,
    addCommand,
    removeCommand,
    running,
    onError,
}: {
    uuid: string;
    icon: any;
    title: string;
    file: string;
    addCommand: (name: string) => string;
    removeCommand: (name: string) => string;
    running: boolean;
    onError: (m: string) => void;
}) => {
    const [names, setNames] = useState<string[] | null>(null);
    const [value, setValue] = useState('');
    const [busy, setBusy] = useState(false);

    const refresh = useCallback(() => {
        readNames(uuid, file).then(setNames);
    }, [uuid, file]);

    useEffect(() => {
        refresh();
    }, [refresh]);

    const run = (command: string) => {
        setBusy(true);
        onError('');
        sendCommand(uuid, command)
            .then(() => setTimeout(refresh, 900))
            .catch((e) => onError(e.message || 'The command could not be sent (is the server running?).'))
            .then(() => setBusy(false));
    };

    const add = () => {
        const name = value.trim();
        if (name === '') return;
        run(addCommand(name));
        setValue('');
    };

    return (
        <div css={card} style={cardPad}>
            <h3 css={tw`flex items-center gap-2 text-sm font-semibold uppercase tracking-wide text-neutral-300 mb-3`}>
                <FontAwesomeIcon icon={icon} css={tw`text-primary-300`} /> {title}
                {names && <span css={tw`ml-auto text-xs font-normal text-neutral-500`}>{names.length}</span>}
            </h3>
            <div css={tw`flex gap-2 mb-3`}>
                <input
                    value={value}
                    onChange={(e) => setValue(e.currentTarget.value)}
                    onKeyDown={(e) => e.key === 'Enter' && add()}
                    placeholder={'Player name'}
                    css={[field, tw`flex-1`]}
                    disabled={!running}
                />
                <Button type={'button'} size={Button.Sizes.Small} onClick={add} disabled={!running || busy}>
                    <FontAwesomeIcon icon={faPlus} />
                </Button>
            </div>
            {!running && <p css={tw`text-xs text-yellow-300 mb-2`}>Start the server to change this list.</p>}
            {names === null ? (
                <p css={tw`text-sm text-neutral-400`}>
                    <FontAwesomeIcon icon={faCircleNotch} spin />
                </p>
            ) : names.length === 0 ? (
                <p css={tw`text-sm text-neutral-400`}>Empty.</p>
            ) : (
                <div css={tw`flex flex-col gap-1.5 max-h-72 overflow-y-auto`}>
                    {names.map((name) => (
                        <div
                            key={name}
                            css={tw`flex items-center gap-2 rounded-lg border border-white/5 bg-neutral-900/50 px-3 py-1.5`}
                        >
                            <span css={tw`text-sm text-neutral-100 flex-1 truncate`}>{name}</span>
                            <button
                                type={'button'}
                                title={'Remove'}
                                onClick={() => run(removeCommand(name))}
                                disabled={!running || busy}
                                css={tw`p-1 rounded text-red-400 hover:bg-white/10 disabled:opacity-40`}
                            >
                                <FontAwesomeIcon icon={faTrash} />
                            </button>
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
};

const PlayersTab = ({ uuid, running, onError }: { uuid: string; running: boolean; onError: (m: string) => void }) => (
    <div css={tw`grid grid-cols-1 md:grid-cols-3 gap-4`}>
        <PlayerList uuid={uuid} icon={faUsers} title={'Whitelist'} file={'/whitelist.json'} addCommand={(n) => `whitelist add ${n}`} removeCommand={(n) => `whitelist remove ${n}`} running={running} onError={onError} />
        <PlayerList uuid={uuid} icon={faUserShield} title={'Operators'} file={'/ops.json'} addCommand={(n) => `op ${n}`} removeCommand={(n) => `deop ${n}`} running={running} onError={onError} />
        <PlayerList uuid={uuid} icon={faBan} title={'Banned'} file={'/banned-players.json'} addCommand={(n) => `ban ${n}`} removeCommand={(n) => `pardon ${n}`} running={running} onError={onError} />
    </div>
);

// ---- Container -----------------------------------------------------------------------------------------------------

export default () => {
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const status = ServerContext.useStoreState((state) => state.status.value);
    const running = status === 'running';

    const saved = loadPrefs(uuid);
    const [tab, setTab] = useState<Tab>(saved.tab === 'players' ? 'players' : 'content');
    const [type, setType] = useState<ContentType>(saved.type || 'content');
    const [loader, setLoader] = useState<string>(saved.loader || 'paper');
    const [gameVersion, setGameVersion] = useState<string>(saved.gameVersion ?? '');
    const [versions, setVersions] = useState<GameVersion[]>([]);
    const [showSnapshots, setShowSnapshots] = useState(false);
    const [error, setError] = useState('');
    const [notice, setNotice] = useState('');

    // The list of Minecraft versions for the dropdown (fetched once).
    useEffect(() => {
        getGameVersions().then(setVersions);
    }, []);

    // On first visit (no saved choice), read the egg's startup to pre-fill the loader and version.
    useEffect(() => {
        const prefs = loadPrefs(uuid);
        if (prefs.loader || prefs.gameVersion) return;
        detectStartup(uuid).then(({ loader: l, gameVersion: v }) => {
            if (l) setLoader(l);
            if (v) setGameVersion(v);
        });
    }, [uuid]);

    // Remember the choice so it is still there after leaving the page.
    useEffect(() => {
        savePrefs(uuid, { tab, type, loader, gameVersion });
    }, [uuid, tab, type, loader, gameVersion]);

    const usesLoader = type === 'content' || type === 'modpack';

    return (
        <ServerContentBlock title={'Minecraft'}>
            <div css={tw`mb-5`}>
                <h1 css={tw`text-2xl font-semibold text-neutral-50`}>Minecraft</h1>
                <p css={tw`text-sm text-neutral-400 mt-1`}>
                    Install plugins and mods from Modrinth, and manage your players.
                </p>
            </div>

            {notice && (
                <p css={tw`mb-4 rounded-lg border border-green-500/30 bg-green-500/10 px-4 py-3 text-sm text-green-200`}>{notice}</p>
            )}
            {error && (
                <p css={tw`mb-4 rounded-lg border border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-200`}>{error}</p>
            )}

            <div css={tw`mb-5 flex gap-1 border-b border-white/10`}>
                {([
                    { id: 'content' as Tab, label: 'Plugins & Mods' },
                    { id: 'players' as Tab, label: 'Players' },
                ]).map(({ id, label }) => (
                    <button
                        key={id}
                        type={'button'}
                        onClick={() => {
                            setError('');
                            setNotice('');
                            setTab(id);
                        }}
                        className={classNames(
                            'px-4 py-2 text-sm font-medium border-b-2 -mb-px transition-colors duration-150',
                            tab === id ? 'border-primary-400 text-neutral-50' : 'border-transparent text-neutral-400 hover:text-neutral-200'
                        )}
                    >
                        {label}
                    </button>
                ))}
            </div>

            {tab === 'content' && (
                <div css={tw`mb-5 flex flex-wrap items-end justify-between gap-3`}>
                    <div css={tw`flex flex-wrap gap-2`}>
                        {TYPES.map((t) => (
                            <button
                                key={t.id}
                                type={'button'}
                                onClick={() => setType(t.id)}
                                className={classNames(
                                    'inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-xs font-medium transition-colors duration-150',
                                    type === t.id
                                        ? 'border-primary-400 bg-primary-500/20 text-primary-100'
                                        : 'border-white/10 bg-white/5 text-neutral-300 hover:bg-white/10'
                                )}
                            >
                                <FontAwesomeIcon icon={t.icon} /> {t.label}
                            </button>
                        ))}
                    </div>
                    <div css={tw`flex items-end gap-2`}>
                        {usesLoader && (
                            <div>
                                <label css={tw`block text-2xs uppercase text-neutral-400 mb-1`}>Loader</label>
                                <select value={loader} onChange={(e) => setLoader(e.currentTarget.value)} css={field}>
                                    {LOADERS.map((l) => (
                                        <option key={l.id} value={l.id}>
                                            {l.label}
                                        </option>
                                    ))}
                                </select>
                            </div>
                        )}
                        <div>
                            <label css={tw`block text-2xs uppercase text-neutral-400 mb-1`}>Version</label>
                            <div css={tw`flex items-center gap-2`}>
                                <select
                                    value={gameVersion}
                                    onChange={(e) => setGameVersion(e.currentTarget.value)}
                                    css={[field, tw`w-36`]}
                                >
                                    <option value={''}>Any version</option>
                                    {gameVersion !== '' && !versions.some((v) => v.version === gameVersion) && (
                                        <option value={gameVersion}>{gameVersion}</option>
                                    )}
                                    {versions
                                        .filter((v) => showSnapshots || !v.snapshot)
                                        .map((v) => (
                                            <option key={v.version} value={v.version}>
                                                {v.version}
                                            </option>
                                        ))}
                                </select>
                                <label
                                    css={tw`flex items-center gap-1.5 text-2xs text-neutral-400 whitespace-nowrap cursor-pointer`}
                                >
                                    <input
                                        type={'checkbox'}
                                        checked={showSnapshots}
                                        onChange={(e) => setShowSnapshots(e.currentTarget.checked)}
                                    />
                                    <span>Snapshots</span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            )}

            {tab === 'content' ? (
                <ContentTab uuid={uuid} type={type} loader={loader} gameVersion={gameVersion} onError={setError} onNotice={setNotice} />
            ) : (
                <PlayersTab uuid={uuid} running={running} onError={setError} />
            )}
        </ServerContentBlock>
    );
};
