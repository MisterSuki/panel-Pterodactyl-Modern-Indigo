import React, { useEffect, useState } from 'react';
import tw from 'twin.macro';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faCheck, faCode, faCube, faFileCode, faFilm } from '@fortawesome/free-solid-svg-icons';
import http from '@/api/http';
import { Button } from '@/components/elements/button/index';
import { Dialog } from '@/components/elements/dialog';
import { ServerContext } from '@/state/server';
import { useFlashKey } from '@/plugins/useFlash';

interface Kind {
    id: number;
    name: string;
    key: 'php' | 'nodejs' | 'python' | 'emby' | 'other';
    versions: { label: string; image: string }[];
}

interface KindPage {
    current: number;
    image: string;
    kinds: Kind[];
}

const ICONS = { php: faFileCode, nodejs: faCube, python: faCode, emby: faFilm, other: faCode };

const DESCRIPTIONS: Record<string, string> = {
    php: 'Pages in HTML or PHP, WordPress and the other CMS.',
    nodejs: 'An application in JavaScript (Express, Next.js...).',
    python: 'An application in Python (Flask, Django, FastAPI...).',
    emby: 'Your films, series and music, watched in the browser or the Emby apps.',
    other: '',
};

// The kind of the site (PHP / HTML, Node.js, Python) and its version, chosen by its owner and changed whenever they
// want: the files are kept, the example files of the new kind are only added where nothing is there yet.
export default () => {
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const { clearFlashes, clearAndAddHttpError } = useFlashKey('server:web');
    const [page, setPage] = useState<KindPage | null>(null);
    const [target, setTarget] = useState<{ kind: Kind; image: string } | null>(null);
    const [busy, setBusy] = useState(false);

    useEffect(() => {
        http.get(`/api/client/servers/${uuid}/web/kind`)
            .then(({ data }) => setPage(data))
            .catch(() => setPage(null));
    }, [uuid]);

    if (!page || page.kinds.length === 0) return null;

    const current = page.kinds.find((k) => k.id === page.current);

    const apply = () => {
        if (!target) return;
        clearFlashes();
        setBusy(true);
        http.post(`/api/client/servers/${uuid}/web/kind`, { egg: target.kind.id, image: target.image })
            .then(() => window.location.reload())
            .catch((error) => {
                clearAndAddHttpError(error);
                setBusy(false);
                setTarget(null);
            });
    };

    return (
        <div css={tw`rounded-2xl bg-neutral-800 border border-white/10 overflow-hidden mt-6`}>
            <div css={tw`px-4 pt-4`}>
                <h2 css={tw`text-base text-neutral-50 font-semibold`}>Kind of site</h2>
                <p css={tw`text-xs text-neutral-400`}>
                    Choose what your site runs. You can change it later: your files are kept.
                </p>
            </div>
            <div css={tw`grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-3 p-4`}>
                {page.kinds.map((kind) => {
                    const active = kind.id === page.current;
                    const version = active ? page.image : kind.versions[0]?.image || '';

                    return (
                        <div
                            key={kind.id}
                            css={[
                                tw`relative flex flex-col gap-3 rounded-xl border px-4 py-3 transition-colors duration-150`,
                                active ? tw`border-primary-500 bg-neutral-700` : tw`border-neutral-600`,
                            ]}
                        >
                            <div css={tw`flex items-center gap-3`}>
                                <span
                                    css={tw`flex items-center justify-center w-9 h-9 rounded-lg text-white flex-shrink-0`}
                                    style={{
                                        background: active ? 'rgb(var(--pd-accent-600))' : 'rgba(255,255,255,0.08)',
                                    }}
                                >
                                    <FontAwesomeIcon icon={ICONS[kind.key]} />
                                </span>
                                <div css={tw`min-w-0 flex-1`}>
                                    <p css={tw`text-sm font-semibold text-neutral-50 truncate`}>
                                        {kind.name.replace(/^Site\s+/i, '')}
                                    </p>
                                    <p css={tw`text-xs text-neutral-400`}>{DESCRIPTIONS[kind.key]}</p>
                                </div>
                                {active && (
                                    <span css={tw`flex-shrink-0 text-xs font-medium`} style={{ color: '#86efac' }}>
                                        <FontAwesomeIcon icon={faCheck} /> <span>In use</span>
                                    </span>
                                )}
                            </div>
                            <div css={tw`flex items-center gap-2`}>
                                {kind.versions.length > 1 && (
                                    <select
                                        defaultValue={version}
                                        id={`pd-kind-${kind.id}`}
                                        onChange={(e) =>
                                            active && e.currentTarget.value !== page.image
                                                ? setTarget({ kind, image: e.currentTarget.value })
                                                : undefined
                                        }
                                        css={tw`flex-1 min-w-0 rounded-lg bg-black/30 border border-white/10 px-2 py-1.5 text-sm text-neutral-100 focus:outline-none`}
                                    >
                                        {kind.versions.map((v) => (
                                            <option key={v.image} value={v.image}>
                                                {v.label}
                                            </option>
                                        ))}
                                    </select>
                                )}
                                {!active && (
                                    <Button
                                        size={Button.Sizes.Small}
                                        onClick={() => {
                                            const select = document.getElementById(
                                                `pd-kind-${kind.id}`
                                            ) as HTMLSelectElement | null;
                                            setTarget({ kind, image: select ? select.value : version });
                                        }}
                                    >
                                        <span>Use it</span>
                                    </Button>
                                )}
                            </div>
                        </div>
                    );
                })}
            </div>

            <Dialog.Confirm
                open={target !== null}
                title={
                    target && current && target.kind.id === current.id
                        ? 'Change the version?'
                        : 'Change the kind of the site?'
                }
                confirm={busy ? 'Changing...' : 'Change'}
                onClose={() => !busy && setTarget(null)}
                onConfirmed={apply}
            >
                {target && current && target.kind.id === current.id
                    ? 'The site restarts with the new version. Your files are not touched.'
                    : 'The site is set up again for this kind: your files are kept, and example files are added only where nothing is there yet. Then start the site from the Console.'}
            </Dialog.Confirm>
        </div>
    );
};
