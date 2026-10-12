import React, { useCallback, useEffect, useState } from 'react';
import tw from 'twin.macro';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import {
    faCheck,
    faCopy,
    faPlus,
    faPowerOff,
    faTrash,
    faTruck,
    faUpload,
    faUserShield,
    faCircleNotch,
} from '@fortawesome/free-solid-svg-icons';
import { ServerContext } from '@/state/server';
import ServerContentBlock from '@/components/elements/ServerContentBlock';
import { Button } from '@/components/elements/button/index';
import { Link, useRouteMatch } from 'react-router-dom';
import {
    findConfig,
    findMods,
    getModerators,
    getSii,
    readConfig,
    setConvoySize,
    setModerators as applyModerators,
    setSii,
    writeConfig,
} from '@/api/server/ets2';
import { FileObject } from '@/api/server/files/loadDirectory';
import renameFiles from '@/api/server/files/renameFiles';
import deleteFiles from '@/api/server/files/deleteFiles';

type FieldType = 'text' | 'number' | 'bool';
interface Field {
    key: string;
    label: string;
    type: FieldType;
    help?: string;
}

const GENERAL: Field[] = [
    { key: 'lobby_name', label: 'Server name', type: 'text' },
    { key: 'description', label: 'Description', type: 'text' },
    { key: 'welcome_message', label: 'Welcome message', type: 'text' },
    { key: 'password', label: 'Password (empty = public)', type: 'text' },
];
const LIMITS: Field[] = [
    {
        key: 'max_players',
        label: 'Max players',
        type: 'number',
        help: 'Edit while the server is stopped. The panel also updates g_max_convoy_size on the server, but EACH player must set the same g_max_convoy_size in their own config.cfg, or the server will not appear for them.',
    },
    { key: 'max_vehicles_total', label: 'Max vehicles', type: 'number' },
    { key: 'max_ai_vehicles_player', label: 'Max AI per player', type: 'number' },
    { key: 'connection_dedicated_port', label: 'Game port', type: 'number' },
    { key: 'query_dedicated_port', label: 'Query port', type: 'number' },
];
const GAMEPLAY: Field[] = [
    { key: 'show_server', label: 'Listed publicly', type: 'bool' },
    { key: 'friends_only', label: 'Friends only', type: 'bool' },
    { key: 'player_damage', label: 'Player damage', type: 'bool' },
    { key: 'traffic', label: 'Traffic', type: 'bool' },
    { key: 'hide_in_company', label: 'Hide in company', type: 'bool' },
    { key: 'hide_colliding', label: 'Hide colliding', type: 'bool' },
    { key: 'force_speed_limiter', label: 'Force speed limiter', type: 'bool' },
    { key: 'mods_optioning', label: 'Allow mods', type: 'bool' },
    { key: 'name_tags', label: 'Name tags', type: 'bool' },
    { key: 'service_no_collision', label: 'No collision at services', type: 'bool' },
    { key: 'in_menu_ghost_mode', label: 'Ghost mode in menu', type: 'bool' },
];

const card = tw`rounded-2xl border border-white/5 bg-neutral-800 shadow-card`;
const cardPad = { padding: '1.5rem' } as React.CSSProperties;
const field = tw`w-full rounded-lg border border-white/10 bg-neutral-900 px-3 py-2 text-sm text-neutral-50 outline-none focus:border-primary-400`;

// A command shown with a one-click "copy" button.
const CommandLine = ({ command }: { command: string }) => {
    const [copied, setCopied] = useState(false);
    const copy = () => {
        try {
            navigator.clipboard.writeText(command).then(() => {
                setCopied(true);
                setTimeout(() => setCopied(false), 1500);
            });
        } catch {
            /* clipboard blocked: the person can still select the text */
        }
    };

    return (
        <div css={tw`flex items-center gap-2 rounded-lg border border-white/10 bg-neutral-900 px-3 py-2`}>
            <code css={tw`flex-1 text-sm text-primary-300 break-all`}>{command}</code>
            <button type={'button'} onClick={copy} css={tw`flex items-center gap-1 text-xs text-neutral-400 hover:text-neutral-100 flex-shrink-0`}>
                <FontAwesomeIcon icon={copied ? faCheck : faCopy} /> <span>{copied ? 'Copied' : 'Copy'}</span>
            </button>
        </div>
    );
};

// Explains how to get a mod pack working on a dedicated server.
const ModsHelp = () => (
    <div css={tw`mt-4 rounded-xl border border-white/5 bg-neutral-900/40 p-4`}>
        <p css={tw`text-xs font-semibold uppercase tracking-wide text-neutral-400 mb-3`}>
            <span>How to add mods</span>
        </p>
        <ol css={tw`list-decimal pl-5 text-sm text-neutral-300 space-y-2`}>
            <li>
                <span>In Euro Truck Simulator 2, enable the mods you want in the Mod Manager and load a profile.</span>
            </li>
            <li>
                <span>Open the in-game console with the ~ key, then run this command:</span>
                <div css={tw`mt-2`}>
                    <CommandLine command={'export_server_packages'} />
                </div>
                <p css={tw`mt-1 text-2xs text-neutral-500`}>
                    <span>If the console does not open, set</span>{' '}
                    <code css={tw`text-neutral-400`}>uset g_console "1"</code>{' '}
                    <span>in config.cfg first.</span>
                </p>
            </li>
            <li>
                <span>
                    It creates two files in your Documents/Euro Truck Simulator 2 folder: server_packages.sii and
                    server_packages.dat.
                </span>
            </li>
            <li>
                <span>
                    Upload those two files to the server root (next to server_config.sii), and put your mod .scs files
                    in the mod folder.
                </span>
            </li>
            <li>
                <span>Turn on &quot;Allow mods&quot; above, then restart the server.</span>
            </li>
        </ol>
    </div>
);

export default () => {
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const running = ServerContext.useStoreState((state) => state.status.value) === 'running';
    const match = useRouteMatch();

    const [loading, setLoading] = useState(true);
    const [path, setPath] = useState<string | null>(null);
    const [raw, setRaw] = useState('');
    const [values, setValues] = useState<Record<string, string>>({});
    const [present, setPresent] = useState<Set<string>>(new Set());
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState('');
    const [notice, setNotice] = useState('');

    const [mods, setMods] = useState<{ dir: string; files: FileObject[] } | null>(null);
    const [moderators, setModeratorsState] = useState<string[]>([]);
    const [modInput, setModInput] = useState('');

    const load = useCallback(() => {
        setLoading(true);
        findConfig(uuid)
            .then(async (found) => {
                setPath(found);
                if (!found) return;
                const text = await readConfig(uuid, found);
                setRaw(text);
                const v: Record<string, string> = {};
                const have = new Set<string>();
                [...GENERAL, ...LIMITS, ...GAMEPLAY].forEach((f) => {
                    const read = getSii(text, f.key);
                    if (read !== null) {
                        have.add(f.key);
                        v[f.key] = read;
                    }
                });
                setValues(v);
                setPresent(have);
                setModeratorsState(getModerators(text));
                findMods(found, uuid).then(setMods);
            })
            .catch((e) => setError(e.message || 'The config could not be read.'))
            .then(() => setLoading(false));
    }, [uuid]);

    useEffect(() => {
        load();
    }, [load]);

    const save = () => {
        setSaving(true);
        setError('');
        setNotice('');
        let out = raw;
        [...GENERAL, ...LIMITS, ...GAMEPLAY].forEach((f) => {
            if (!present.has(f.key)) return;
            out = setSii(out, f.key, values[f.key] ?? '', f.type === 'text');
        });
        out = applyModerators(out, moderators);
        writeConfig(uuid, path!, out)
            .then(() => (present.has('max_players') ? setConvoySize(uuid, path!, values['max_players'] ?? '8') : undefined))
            .then(() => {
                setRaw(out);
                setNotice('Saved. Restart the server to apply the changes.');
            })
            .catch((e) => setError(e.message || 'The config could not be saved.'))
            .then(() => setSaving(false));
    };

    const set = (key: string, value: string) => setValues((s) => ({ ...s, [key]: value }));

    const addModerator = () => {
        const value = modInput.trim();
        if (!/^\d{17}$/.test(value)) {
            setError('Enter a valid SteamID64 (17 digits).');

            return;
        }
        setError('');
        if (!moderators.includes(value)) {
            setModeratorsState([...moderators, value]);
        }
        setModInput('');
    };

    const removeModerator = (id: string) => setModeratorsState(moderators.filter((x) => x !== id));

    const toggleMod = (file: FileObject) => {
        if (!mods) return;
        const off = file.name.endsWith('.off');
        const to = off ? file.name.replace(/\.off$/, '') : `${file.name}.off`;
        renameFiles(uuid, mods.dir, [{ from: file.name, to }])
            .then(() => findMods(path!, uuid).then(setMods))
            .catch((e) => setError(e.message || 'Could not change the mod.'));
    };

    const removeMod = (file: FileObject) => {
        if (!mods) return;
        if (!window.confirm(`Delete ${file.name}?`)) return;
        deleteFiles(uuid, mods.dir, [file.name])
            .then(() => findMods(path!, uuid).then(setMods))
            .catch((e) => setError(e.message || 'Could not delete the mod.'));
    };

    const renderField = (f: Field) => {
        if (!present.has(f.key)) return null;
        if (f.type === 'bool') {
            const on = values[f.key] === 'true';
            return (
                <label key={f.key} css={tw`flex items-center justify-between gap-3 py-2`}>
                    <span css={tw`text-sm text-neutral-200`}>{f.label}</span>
                    <button
                        type={'button'}
                        onClick={() => set(f.key, on ? 'false' : 'true')}
                        css={[
                            tw`relative w-11 h-6 rounded-full transition-colors duration-150 flex-shrink-0`,
                            on ? tw`bg-primary-500` : tw`bg-neutral-600`,
                        ]}
                    >
                        <span
                            css={[
                                tw`absolute top-0.5 w-5 h-5 rounded-full bg-white transition-all duration-150`,
                                on ? tw`left-5` : tw`left-0.5`,
                            ]}
                        />
                    </button>
                </label>
            );
        }
        return (
            <div key={f.key} css={tw`py-2`}>
                <label css={tw`mb-1 block text-xs text-neutral-400`}>{f.label}</label>
                <input
                    type={f.type === 'number' ? 'number' : 'text'}
                    value={values[f.key] ?? ''}
                    onChange={(e) => set(f.key, e.currentTarget.value)}
                    css={field}
                />
                {f.help && (
                    <p css={tw`mt-1 text-2xs text-yellow-500/80`}>
                        <span>{f.help}</span>
                    </p>
                )}
            </div>
        );
    };

    return (
        <ServerContentBlock title={'Euro Truck'}>
            <div css={tw`mb-5 flex flex-wrap items-end justify-between gap-3`}>
                <div>
                    <h1 css={tw`text-2xl font-semibold text-neutral-50 flex items-center gap-3`}>
                        <FontAwesomeIcon icon={faTruck} css={tw`text-primary-400`} /> Euro Truck
                    </h1>
                    <p css={tw`text-sm text-neutral-400 mt-1`}>Configure your dedicated server and manage its mods.</p>
                </div>
                {path && (
                    <Button type={'button'} onClick={save} disabled={saving || loading}>
                        {saving ? <FontAwesomeIcon icon={faCircleNotch} spin /> : <span>Save</span>}
                    </Button>
                )}
            </div>

            {notice && (
                <p css={tw`mb-4 rounded-lg border border-green-500/30 bg-green-500/10 px-4 py-3 text-sm text-green-200`}>{notice}</p>
            )}
            {error && (
                <p css={tw`mb-4 rounded-lg border border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-200`}>{error}</p>
            )}
            {running && path && (
                <p css={tw`mb-4 rounded-lg border border-yellow-500/30 bg-yellow-500/10 px-4 py-3 text-sm text-yellow-200`}>
                    <span>The server is running. Stop it before saving, or the game may overwrite your changes.</span>
                </p>
            )}

            {loading ? (
                <p css={tw`text-center text-neutral-400 py-10`}>
                    <FontAwesomeIcon icon={faCircleNotch} spin size={'lg'} />
                </p>
            ) : !path ? (
                <div css={card} style={cardPad}>
                    <p css={tw`text-sm text-neutral-300`}>
                        <span>
                            No server_config.sii was found yet. Start the server once so it creates the file, then come
                            back here.
                        </span>
                    </p>
                </div>
            ) : (
                <div css={tw`grid grid-cols-1 lg:grid-cols-2 gap-4`}>
                    <div css={card} style={cardPad}>
                        <h3 css={tw`text-sm font-semibold uppercase tracking-wide text-neutral-300 mb-2`}>
                            <span>General</span>
                        </h3>
                        {GENERAL.map(renderField)}
                        <h3 css={tw`text-sm font-semibold uppercase tracking-wide text-neutral-300 mt-4 mb-2`}>
                            <span>Limits & ports</span>
                        </h3>
                        {LIMITS.map(renderField)}
                    </div>
                    <div css={tw`flex flex-col gap-4`}>
                        <div css={card} style={cardPad}>
                            <h3 css={tw`text-sm font-semibold uppercase tracking-wide text-neutral-300 mb-1`}>
                                <span>Gameplay rules</span>
                            </h3>
                            <div css={tw`divide-y divide-white/5`}>{GAMEPLAY.map(renderField)}</div>
                        </div>

                        <div css={card} style={cardPad}>
                            <h3 css={tw`flex items-center gap-2 text-sm font-semibold uppercase tracking-wide text-neutral-300 mb-3`}>
                                <FontAwesomeIcon icon={faUserShield} css={tw`text-primary-300`} /> <span>Moderators</span>
                            </h3>
                            <div css={tw`flex gap-2 mb-3`}>
                                <input
                                    value={modInput}
                                    onChange={(e) => setModInput(e.currentTarget.value)}
                                    onKeyDown={(e) => e.key === 'Enter' && addModerator()}
                                    placeholder={'SteamID64 (17 digits)'}
                                    css={field}
                                />
                                <Button type={'button'} onClick={addModerator} css={tw`flex-shrink-0`}>
                                    <FontAwesomeIcon icon={faPlus} />
                                </Button>
                            </div>
                            {moderators.length === 0 ? (
                                <p css={tw`text-sm text-neutral-400`}>No moderator.</p>
                            ) : (
                                <div css={tw`flex flex-col gap-2`}>
                                    {moderators.map((id) => (
                                        <div
                                            key={id}
                                            css={tw`flex items-center gap-2 rounded-xl border border-white/5 bg-neutral-900/50 px-3 py-1.5`}
                                        >
                                            <code css={tw`text-sm text-neutral-100 flex-1 truncate`}>{id}</code>
                                            <button
                                                type={'button'}
                                                title={'Remove'}
                                                onClick={() => removeModerator(id)}
                                                css={tw`p-1 rounded text-red-400 hover:bg-white/10`}
                                            >
                                                <FontAwesomeIcon icon={faTrash} />
                                            </button>
                                        </div>
                                    ))}
                                </div>
                            )}
                            <p css={tw`mt-3 text-2xs text-neutral-500`}>
                                <span>Use each moderator&apos;s SteamID64 (17 digits). Click Save to apply.</span>
                            </p>
                        </div>

                        <div css={card} style={cardPad}>
                            <div css={tw`flex items-center justify-between mb-3`}>
                                <h3 css={tw`text-sm font-semibold uppercase tracking-wide text-neutral-300`}>
                                    <span>Mods</span>
                                </h3>
                                {mods && (
                                    <Link
                                        to={`${match.url.replace(/\/ets2\/?$/, '')}/files#${encodeURIComponent(mods.dir)}`}
                                        css={tw`text-xs text-neutral-400 hover:text-primary-300`}
                                    >
                                        <FontAwesomeIcon icon={faUpload} css={tw`mr-1`} /> <span>Upload a .scs</span>
                                    </Link>
                                )}
                            </div>
                            {!mods || mods.files.length === 0 ? (
                                <p css={tw`text-sm text-neutral-400`}>No mod here yet.</p>
                            ) : (
                                <div css={tw`flex flex-col gap-2`}>
                                    {mods.files.map((file) => {
                                        const off = file.name.endsWith('.off');
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
                                                    {file.name.replace(/\.off$/, '')}
                                                </p>
                                                <button
                                                    type={'button'}
                                                    title={off ? 'Enable' : 'Disable'}
                                                    onClick={() => toggleMod(file)}
                                                    css={[tw`p-1.5 rounded-lg hover:bg-white/10`, off ? tw`text-neutral-500` : tw`text-green-400`]}
                                                >
                                                    <FontAwesomeIcon icon={faPowerOff} />
                                                </button>
                                                <button
                                                    type={'button'}
                                                    title={'Delete'}
                                                    onClick={() => removeMod(file)}
                                                    css={tw`p-1.5 rounded-lg text-red-400 hover:bg-white/10`}
                                                >
                                                    <FontAwesomeIcon icon={faTrash} />
                                                </button>
                                            </div>
                                        );
                                    })}
                                </div>
                            )}
                            <ModsHelp />
                        </div>
                    </div>
                </div>
            )}
        </ServerContentBlock>
    );
};
