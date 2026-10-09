import React, { useCallback, useEffect, useState } from 'react';
import tw from 'twin.macro';
import classNames from 'classnames';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import {
    faBan,
    faDownload,
    faPlus,
    faPowerOff,
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
    LOADERS,
    ModrinthHit,
    directoryForLoader,
    pullFile,
    resolveDownload,
    searchModrinth,
    sendCommand,
} from '@/api/server/minecraft';
import loadDirectory, { FileObject } from '@/api/server/files/loadDirectory';
import deleteFiles from '@/api/server/files/deleteFiles';
import renameFiles from '@/api/server/files/renameFiles';
import getFileContents from '@/api/server/files/getFileContents';

type Tab = 'content' | 'players';

const card = tw`rounded-2xl border border-white/5 bg-neutral-800 shadow-card`;
const field = tw`rounded-lg border border-white/10 bg-neutral-900 px-3 py-2 text-sm text-neutral-50 outline-none focus:border-primary-400`;

// ---- Plugins & mods ------------------------------------------------------------------------------------------------

const ContentTab = ({
    uuid,
    loader,
    gameVersion,
    onError,
    onNotice,
}: {
    uuid: string;
    loader: string;
    gameVersion: string;
    onError: (m: string) => void;
    onNotice: (m: string) => void;
}) => {
    const kind = LOADERS.find((l) => l.id === loader)?.kind === 'mod' ? 'mods' : 'plugins';
    const directory = directoryForLoader(loader);
    const match = useRouteMatch();
    const filesUrl = `${match.url.replace(/\/minecraft\/?$/, '')}/files#${encodeURIComponent(directory)}`;

    const [query, setQuery] = useState('');
    const [results, setResults] = useState<ModrinthHit[] | null>(null);
    const [searching, setSearching] = useState(false);
    const [installing, setInstalling] = useState<string | null>(null);
    const [installed, setInstalled] = useState<FileObject[] | null>(null);

    const refresh = useCallback(() => {
        loadDirectory(uuid, directory)
            .then((files) =>
                setInstalled(
                    files.filter((f) => f.isFile && /\.jar(\.disabled)?$/i.test(f.name)).sort((a, b) => a.name.localeCompare(b.name))
                )
            )
            .catch(() => setInstalled([]));
    }, [uuid, directory]);

    useEffect(() => {
        refresh();
    }, [refresh]);

    const search = () => {
        if (query.trim() === '') return;
        setSearching(true);
        onError('');
        searchModrinth(query.trim(), loader, gameVersion)
            .then(setResults)
            .catch((e) => onError(e.message || 'The search failed.'))
            .then(() => setSearching(false));
    };

    const install = (hit: ModrinthHit) => {
        setInstalling(hit.projectId);
        onError('');
        resolveDownload(hit.projectId, loader, gameVersion)
            .then((file) => {
                if (!file) {
                    throw new Error(
                        `"${hit.title}" has no build for ${loader}${gameVersion ? ' ' + gameVersion : ''}. Try another version.`
                    );
                }
                return pullFile(uuid, file.url, directory, file.filename).then(() => file.filename);
            })
            .then((filename) => {
                onNotice(`${filename} was installed into ${directory}. Restart the server to load it.`);
                setTimeout(refresh, 1500);
            })
            .catch((e) => onError(e.message || 'The install failed.'))
            .then(() => setInstalling(null));
    };

    const toggle = (file: FileObject) => {
        const disabled = file.name.endsWith('.disabled');
        const to = disabled ? file.name.replace(/\.disabled$/, '') : `${file.name}.disabled`;
        renameFiles(uuid, directory, [{ from: file.name, to }])
            .then(refresh)
            .catch((e) => onError(e.message || 'Could not change the file.'));
    };

    const remove = (file: FileObject) => {
        if (!window.confirm(`Delete ${file.name}? This cannot be undone.`)) return;
        deleteFiles(uuid, directory, [file.name])
            .then(refresh)
            .catch((e) => onError(e.message || 'Could not delete the file.'));
    };

    return (
        <div css={tw`grid grid-cols-1 lg:grid-cols-2 gap-4`}>
            <div css={card} style={{ padding: '1.25rem' }}>
                <div css={tw`flex items-center gap-2 mb-3`}>
                    <div css={tw`relative flex-1`}>
                        <FontAwesomeIcon
                            icon={faSearch}
                            css={tw`absolute left-3 top-1/2 -translate-y-1/2 text-neutral-500 text-sm`}
                        />
                        <input
                            value={query}
                            onChange={(e) => setQuery(e.currentTarget.value)}
                            onKeyDown={(e) => e.key === 'Enter' && search()}
                            placeholder={kind === 'mods' ? 'Search a mod on Modrinth...' : 'Search a plugin on Modrinth...'}
                            css={[field, tw`w-full pl-9`]}
                        />
                    </div>
                    <Button type={'button'} onClick={search} disabled={searching}>
                        {searching ? <FontAwesomeIcon icon={faCircleNotch} spin /> : <span>Search</span>}
                    </Button>
                </div>
                {results === null ? (
                    <p css={tw`text-sm text-neutral-400 py-6 text-center`}>
                        Search Modrinth, then install a plugin or mod in one click.
                    </p>
                ) : results.length === 0 ? (
                    <p css={tw`text-sm text-neutral-400 py-6 text-center`}>Nothing found. Try other words.</p>
                ) : (
                    <div css={tw`flex flex-col gap-2 max-h-[28rem] overflow-y-auto`}>
                        {results.map((hit) => (
                            <div key={hit.projectId} css={tw`flex items-center gap-3 rounded-xl border border-white/5 bg-neutral-900/50 p-3`}>
                                {hit.iconUrl ? (
                                    <img src={hit.iconUrl} alt={''} css={tw`w-10 h-10 rounded-lg flex-shrink-0 object-cover`} />
                                ) : (
                                    <div css={tw`w-10 h-10 rounded-lg flex-shrink-0 bg-primary-500/20`} />
                                )}
                                <div css={tw`min-w-0 flex-1`}>
                                    <p css={tw`text-sm font-medium text-neutral-100 truncate`}>{hit.title}</p>
                                    <p css={tw`text-xs text-neutral-400 truncate`}>{hit.description}</p>
                                </div>
                                <Button
                                    type={'button'}
                                    size={Button.Sizes.Small}
                                    onClick={() => install(hit)}
                                    disabled={installing !== null}
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

            <div css={card} style={{ padding: '1.25rem' }}>
                <div css={tw`flex items-center justify-between mb-3`}>
                    <h3 css={tw`text-sm font-semibold uppercase tracking-wide text-neutral-300`}>
                        <span>Installed in</span> <code css={tw`text-primary-300`}>{directory}</code>
                    </h3>
                    <Link to={filesUrl} css={tw`text-xs text-neutral-400 hover:text-primary-300`}>
                        <FontAwesomeIcon icon={faUpload} css={tw`mr-1`} /> <span>Upload a .jar</span>
                    </Link>
                </div>
                {installed === null ? (
                    <p css={tw`text-sm text-neutral-400 py-6 text-center`}>
                        <FontAwesomeIcon icon={faCircleNotch} spin />
                    </p>
                ) : installed.length === 0 ? (
                    <p css={tw`text-sm text-neutral-400 py-6 text-center`}>Nothing installed yet.</p>
                ) : (
                    <div css={tw`flex flex-col gap-2 max-h-[28rem] overflow-y-auto`}>
                        {installed.map((file) => {
                            const off = file.name.endsWith('.disabled');
                            return (
                                <div key={file.name} css={tw`flex items-center gap-3 rounded-xl border border-white/5 bg-neutral-900/50 px-3 py-2`}>
                                    <div css={tw`min-w-0 flex-1`}>
                                        <p css={[tw`text-sm truncate`, off ? tw`text-neutral-500 line-through` : tw`text-neutral-100`]}>
                                            {file.name.replace(/\.disabled$/, '')}
                                        </p>
                                    </div>
                                    {off && <span css={tw`text-2xs uppercase text-neutral-500`}>Disabled</span>}
                                    <button
                                        type={'button'}
                                        title={off ? 'Enable' : 'Disable'}
                                        onClick={() => toggle(file)}
                                        css={[tw`p-1.5 rounded-lg hover:bg-white/10`, off ? tw`text-neutral-500` : tw`text-green-400`]}
                                    >
                                        <FontAwesomeIcon icon={faPowerOff} />
                                    </button>
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
        <div css={card} style={{ padding: '1.25rem' }}>
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
                        <div key={name} css={tw`flex items-center gap-2 rounded-lg border border-white/5 bg-neutral-900/50 px-3 py-1.5`}>
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
        <PlayerList
            uuid={uuid}
            icon={faUsers}
            title={'Whitelist'}
            file={'/whitelist.json'}
            addCommand={(n) => `whitelist add ${n}`}
            removeCommand={(n) => `whitelist remove ${n}`}
            running={running}
            onError={onError}
        />
        <PlayerList
            uuid={uuid}
            icon={faUserShield}
            title={'Operators'}
            file={'/ops.json'}
            addCommand={(n) => `op ${n}`}
            removeCommand={(n) => `deop ${n}`}
            running={running}
            onError={onError}
        />
        <PlayerList
            uuid={uuid}
            icon={faBan}
            title={'Banned'}
            file={'/banned-players.json'}
            addCommand={(n) => `ban ${n}`}
            removeCommand={(n) => `pardon ${n}`}
            running={running}
            onError={onError}
        />
    </div>
);

// ---- Container -----------------------------------------------------------------------------------------------------

export default () => {
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const status = ServerContext.useStoreState((state) => state.status.value);
    const running = status === 'running';

    const [tab, setTab] = useState<Tab>('content');
    const [loader, setLoader] = useState('paper');
    const [gameVersion, setGameVersion] = useState('');
    const [error, setError] = useState('');
    const [notice, setNotice] = useState('');

    const tabs: { id: Tab; label: string }[] = [
        { id: 'content', label: 'Plugins & Mods' },
        { id: 'players', label: 'Players' },
    ];

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

            <div css={tw`mb-5 flex flex-wrap items-end justify-between gap-3`}>
                <div css={tw`flex gap-1 border-b border-white/10`}>
                    {tabs.map(({ id, label }) => (
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
                                tab === id
                                    ? 'border-primary-400 text-neutral-50'
                                    : 'border-transparent text-neutral-400 hover:text-neutral-200'
                            )}
                        >
                            {label}
                        </button>
                    ))}
                </div>
                {tab === 'content' && (
                    <div css={tw`flex items-end gap-2`}>
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
                        <div>
                            <label css={tw`block text-2xs uppercase text-neutral-400 mb-1`}>Version</label>
                            <input
                                value={gameVersion}
                                onChange={(e) => setGameVersion(e.currentTarget.value)}
                                placeholder={'any'}
                                css={[field, tw`w-24`]}
                            />
                        </div>
                    </div>
                )}
            </div>

            {tab === 'content' ? (
                <ContentTab
                    uuid={uuid}
                    loader={loader}
                    gameVersion={gameVersion}
                    onError={setError}
                    onNotice={setNotice}
                />
            ) : (
                <PlayersTab uuid={uuid} running={running} onError={setError} />
            )}
        </ServerContentBlock>
    );
};
