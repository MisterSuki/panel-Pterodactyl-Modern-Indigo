import React, { useEffect, useMemo, useRef, useState } from 'react';
import tw from 'twin.macro';
import useSWR from 'swr';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import {
    faCloudUploadAlt,
    faCopy,
    faDownload,
    faExternalLinkAlt,
    faFile,
    faFileAudio,
    faGlobe,
    faLock,
    faPen,
    faPlay,
    faSearch,
    faTimes,
    faTrashAlt,
} from '@fortawesome/free-solid-svg-icons';
import PageContentBlock from '@/components/elements/PageContentBlock';
import { Button } from '@/components/elements/button/index';
import { Dialog } from '@/components/elements/dialog';
import { Input } from '@/components/elements/inputs';
import {
    deleteHostedFile,
    getHostedFiles,
    HostedFile,
    HostingMeta,
    updateHostedFile,
    uploadHostedFile,
} from '@/api/hosting';
import { bytesToString } from '@/lib/formatters';
import { httpErrorToHuman } from '@/api/http';
import useFlash from '@/plugins/useFlash';

type Filter = 'all' | 'image' | 'video' | 'audio' | 'other';

const FILTERS: { value: Filter; label: string }[] = [
    { value: 'all', label: 'All' },
    { value: 'image', label: 'Pictures' },
    { value: 'video', label: 'Videos' },
    { value: 'audio', label: 'Sounds' },
    { value: 'other', label: 'Other files' },
];

interface Upload {
    id: number;
    name: string;
    size: number;
    sent: number;
    error: string | null;
    signal: { cancelled: boolean };
}

const extension = (name: string) => {
    const dot = name.lastIndexOf('.');

    return dot > 0
        ? name
              .slice(dot + 1)
              .toUpperCase()
              .slice(0, 5)
        : 'FILE';
};

const Preview = ({ file, large }: { file: HostedFile; large?: boolean }) => {
    if (file.kind === 'image' && file.inline) {
        return (
            <img
                src={file.url}
                alt={''}
                loading={'lazy'}
                css={large ? tw`max-w-full mx-auto rounded-lg` : tw`absolute inset-0 w-full h-full object-cover`}
                style={large ? { maxHeight: '70vh' } : undefined}
            />
        );
    }
    if (file.kind === 'video' && file.inline) {
        return large ? (
            <video
                src={file.url}
                controls
                autoPlay
                css={tw`max-w-full mx-auto rounded-lg bg-black`}
                style={{ maxHeight: '70vh' }}
            />
        ) : (
            <>
                <video
                    src={`${file.url}#t=0.5`}
                    preload={'metadata'}
                    muted
                    css={tw`absolute inset-0 w-full h-full object-cover`}
                />
                <span css={tw`absolute inset-0 flex items-center justify-center`}>
                    <span
                        css={tw`flex items-center justify-center w-10 h-10 rounded-full text-white`}
                        style={{ background: 'rgba(0, 0, 0, 0.55)' }}
                    >
                        <FontAwesomeIcon icon={faPlay} css={tw`text-sm ml-0.5`} />
                    </span>
                </span>
            </>
        );
    }
    if (file.kind === 'audio' && file.inline && large) {
        return (
            <div css={tw`py-10 text-center`}>
                <FontAwesomeIcon icon={faFileAudio} css={tw`text-5xl text-neutral-500 mb-6`} />
                <audio src={file.url} controls autoPlay css={tw`w-full`} />
            </div>
        );
    }

    return (
        <span
            css={
                large
                    ? tw`flex flex-col items-center gap-3 py-12`
                    : tw`absolute inset-0 flex flex-col items-center justify-center gap-2`
            }
        >
            <FontAwesomeIcon icon={file.kind === 'audio' ? faFileAudio : faFile} css={tw`text-3xl text-neutral-500`} />
            <span css={tw`rounded border border-neutral-600 px-1.5 py-0.5 font-mono text-xs text-neutral-400`}>
                {extension(file.name)}
            </span>
        </span>
    );
};

const IconButton = ({
    title,
    onClick,
    icon,
    danger,
}: {
    title: string;
    onClick: () => void;
    icon: typeof faCopy;
    danger?: boolean;
}) => (
    <button
        type={'button'}
        title={title}
        aria-label={title}
        onClick={onClick}
        css={[
            tw`flex items-center justify-center w-7 h-7 rounded-md text-neutral-400 hover:bg-neutral-700`,
            danger ? tw`hover:text-red-400` : tw`hover:text-neutral-50`,
        ]}
    >
        <FontAwesomeIcon icon={icon} css={tw`text-xs`} />
    </button>
);

// "My files": the pictures, videos and files the person keeps on the panel, with a link to share each one.
export default () => {
    const { addFlash, clearFlashes, clearAndAddHttpError } = useFlash();
    const { data, error, mutate } = useSWR<{ files: HostedFile[]; meta: HostingMeta }>('hosted-files', getHostedFiles, {
        revalidateOnFocus: false,
    });
    const [filter, setFilter] = useState<Filter>('all');
    const [search, setSearch] = useState('');
    const [uploads, setUploads] = useState<Upload[]>([]);
    const [dragging, setDragging] = useState(false);
    const [viewing, setViewing] = useState<HostedFile | null>(null);
    const [renaming, setRenaming] = useState<HostedFile | null>(null);
    const [newName, setNewName] = useState('');
    const [removing, setRemoving] = useState<HostedFile | null>(null);
    const picker = useRef<HTMLInputElement>(null);
    const counter = useRef(0);

    useEffect(() => {
        if (error) clearAndAddHttpError({ key: 'files', error });
    }, [error]);

    const files = useMemo(() => {
        const words = search.trim().toLowerCase();

        return (data?.files || []).filter(
            (file) => (filter === 'all' || file.kind === filter) && (!words || file.name.toLowerCase().includes(words))
        );
    }, [data, filter, search]);

    const meta = data?.meta;
    const ratio = meta && meta.quota > 0 ? Math.min(1, meta.used / meta.quota) : 0;

    const send = (list: FileList | File[]) => {
        clearFlashes('files');
        Array.from(list).forEach((file) => {
            const upload: Upload = {
                id: ++counter.current,
                name: file.name,
                size: file.size,
                sent: 0,
                error: null,
                signal: { cancelled: false },
            };
            setUploads((all) => [...all, upload]);
            const change = (values: Partial<Upload>) =>
                setUploads((all) => all.map((item) => (item.id === upload.id ? { ...item, ...values } : item)));

            uploadHostedFile(file, (sent) => change({ sent }), upload.signal)
                .then((saved) => {
                    setUploads((all) => all.filter((item) => item.id !== upload.id));
                    mutate(
                        (current) =>
                            current && {
                                files: [saved, ...current.files],
                                meta: { ...current.meta, used: current.meta.used + saved.size },
                            },
                        false
                    );
                })
                .catch((e) => {
                    if (upload.signal.cancelled) {
                        setUploads((all) => all.filter((item) => item.id !== upload.id));
                    } else {
                        change({ error: e && e.response ? httpErrorToHuman(e) : 'The file could not be sent.' });
                    }
                });
        });
    };

    const replace = (saved: HostedFile) =>
        mutate(
            (current) =>
                current && { ...current, files: current.files.map((f) => (f.uuid === saved.uuid ? saved : f)) },
            false
        );

    const copy = (file: HostedFile) => {
        const done = () => addFlash({ key: 'files', type: 'success', message: 'The link was copied.' });
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(file.url).then(done);
        } else {
            const area = document.createElement('textarea');
            area.value = file.url;
            document.body.appendChild(area);
            area.select();
            document.execCommand('copy');
            document.body.removeChild(area);
            done();
        }
    };

    const togglePublic = (file: HostedFile) =>
        updateHostedFile(file.uuid, { public: !file.public })
            .then(replace)
            .catch((e) => clearAndAddHttpError({ key: 'files', error: e }));

    const rename = () => {
        if (!renaming || !newName.trim()) return;
        const target = renaming;
        setRenaming(null);
        updateHostedFile(target.uuid, { name: newName.trim() })
            .then(replace)
            .catch((e) => clearAndAddHttpError({ key: 'files', error: e }));
    };

    const remove = () => {
        if (!removing) return;
        const target = removing;
        setRemoving(null);
        deleteHostedFile(target.uuid)
            .then(() =>
                mutate(
                    (current) =>
                        current && {
                            files: current.files.filter((f) => f.uuid !== target.uuid),
                            meta: { ...current.meta, used: Math.max(0, current.meta.used - target.size) },
                        },
                    false
                )
            )
            .catch((e) => clearAndAddHttpError({ key: 'files', error: e }));
    };

    return (
        <PageContentBlock title={'My files'} showFlashKey={'files'}>
            <div
                onDragEnter={(e) => {
                    if (e.dataTransfer.types.includes('Files')) setDragging(true);
                }}
                onDragOver={(e) => e.preventDefault()}
                onDragLeave={(e) => {
                    if (e.currentTarget === e.target) setDragging(false);
                }}
                onDrop={(e) => {
                    e.preventDefault();
                    setDragging(false);
                    if (e.dataTransfer.files.length) send(e.dataTransfer.files);
                }}
                css={tw`relative`}
            >
                {dragging && (
                    <div
                        css={tw`absolute inset-0 z-20 flex flex-col items-center justify-center gap-3 rounded-2xl border-2 border-dashed pointer-events-none`}
                        style={{ borderColor: 'rgb(var(--pd-accent-500))', background: 'rgb(10 10 10 / 0.85)' }}
                    >
                        <FontAwesomeIcon icon={faCloudUploadAlt} css={tw`text-4xl text-neutral-200`} />
                        <p css={tw`text-sm text-neutral-200`}>Drop the files to send them</p>
                    </div>
                )}

                <div css={tw`flex flex-wrap items-end justify-between gap-4 mb-6`}>
                    <div>
                        <h1 css={tw`text-3xl font-semibold text-neutral-50 tracking-tight`}>My files</h1>
                        <p css={tw`text-sm text-neutral-400 mt-1.5`}>
                            Keep pictures, videos and files here, and share them with a link.
                        </p>
                    </div>
                    <div css={tw`flex items-center gap-3`}>
                        <input
                            ref={picker}
                            type={'file'}
                            multiple
                            css={tw`hidden`}
                            onChange={(e) => {
                                if (e.currentTarget.files) send(e.currentTarget.files);
                                e.currentTarget.value = '';
                            }}
                        />
                        <Button onClick={() => picker.current?.click()} disabled={!meta}>
                            <FontAwesomeIcon icon={faCloudUploadAlt} css={tw`mr-2 text-xs`} />
                            <span>Send files</span>
                        </Button>
                    </div>
                </div>

                {meta && (
                    <div className={'pd-neon'} css={tw`relative rounded-xl bg-neutral-800 px-4 py-3 mb-6`}>
                        <div css={tw`relative flex flex-wrap items-baseline justify-between gap-2 text-sm`}>
                            <span css={tw`text-neutral-300`}>
                                <span css={tw`font-mono text-neutral-50`}>{bytesToString(meta.used)}</span>{' '}
                                <span>used of</span> <span css={tw`font-mono`}>{bytesToString(meta.quota)}</span>
                            </span>
                            <span css={tw`text-xs text-neutral-500`}>
                                <span>Biggest file:</span> {bytesToString(meta.maxFile)}
                                {meta.mediaOnly && (
                                    <>
                                        {' · '}
                                        <span>pictures, videos and sounds only</span>
                                    </>
                                )}
                            </span>
                        </div>
                        <div css={tw`relative mt-2 h-1.5 rounded-full bg-neutral-700 overflow-hidden`}>
                            <div
                                css={tw`h-full rounded-full transition-all duration-500`}
                                style={{
                                    width: `${Math.max(1, ratio * 100)}%`,
                                    background:
                                        ratio >= 0.9
                                            ? '#e5484d'
                                            : ratio >= 0.75
                                            ? '#f5a524'
                                            : 'rgb(var(--pd-accent-500))',
                                }}
                            />
                        </div>
                    </div>
                )}

                {uploads.length > 0 && (
                    <div css={tw`flex flex-col gap-2 mb-6`}>
                        {uploads.map((upload) => (
                            <div
                                key={upload.id}
                                className={'pd-neon'}
                                css={tw`relative flex items-center gap-3 rounded-xl bg-neutral-800 px-4 py-3`}
                            >
                                <FontAwesomeIcon icon={faCloudUploadAlt} css={tw`text-neutral-500`} />
                                <div css={tw`flex-1 min-w-0`}>
                                    <div css={tw`flex items-baseline justify-between gap-3 text-sm`}>
                                        <span css={tw`truncate text-neutral-100`}>{upload.name}</span>
                                        <span css={tw`flex-shrink-0 font-mono text-xs text-neutral-400`}>
                                            {upload.error
                                                ? ''
                                                : `${Math.floor((upload.sent / Math.max(1, upload.size)) * 100)} %`}
                                        </span>
                                    </div>
                                    {upload.error ? (
                                        <p css={tw`mt-1 text-xs text-red-400`}>{upload.error}</p>
                                    ) : (
                                        <div css={tw`mt-2 h-1 rounded-full bg-neutral-700 overflow-hidden`}>
                                            <div
                                                css={tw`h-full rounded-full transition-all duration-200`}
                                                style={{
                                                    width: `${(upload.sent / Math.max(1, upload.size)) * 100}%`,
                                                    background: 'rgb(var(--pd-accent-500))',
                                                }}
                                            />
                                        </div>
                                    )}
                                </div>
                                <IconButton
                                    title={upload.error ? 'Close' : 'Cancel'}
                                    icon={faTimes}
                                    onClick={() => {
                                        upload.signal.cancelled = true;
                                        if (upload.error)
                                            setUploads((all) => all.filter((item) => item.id !== upload.id));
                                    }}
                                />
                            </div>
                        ))}
                    </div>
                )}

                <div css={tw`flex flex-wrap items-center justify-between gap-3 mb-4`}>
                    <div css={tw`flex flex-wrap items-center gap-0.5 rounded-md border border-neutral-600 p-0.5`}>
                        {FILTERS.map((item) => (
                            <button
                                key={item.value}
                                type={'button'}
                                onClick={() => setFilter(item.value)}
                                css={[
                                    tw`rounded px-3 py-1 text-sm transition-colors duration-150`,
                                    filter === item.value
                                        ? tw`bg-neutral-700 text-neutral-50`
                                        : tw`text-neutral-400 hover:text-neutral-100`,
                                ]}
                            >
                                {item.label}
                            </button>
                        ))}
                    </div>
                    <label css={tw`relative flex items-center`}>
                        <FontAwesomeIcon icon={faSearch} css={tw`absolute left-3 text-xs text-neutral-500`} />
                        <input
                            type={'text'}
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder={'Search'}
                            css={tw`h-8 w-56 rounded-md border border-neutral-600 bg-neutral-900 pl-8 pr-3 text-sm text-neutral-100 placeholder-neutral-500 outline-none focus:border-neutral-500`}
                        />
                    </label>
                </div>

                {!data ? (
                    <div css={tw`grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-4`}>
                        {[0, 1, 2, 3].map((i) => (
                            <div key={i} className={'pd-skeleton'} css={tw`h-52 rounded-xl`} />
                        ))}
                    </div>
                ) : files.length === 0 ? (
                    <button
                        type={'button'}
                        onClick={() => picker.current?.click()}
                        css={tw`w-full rounded-2xl border-2 border-dashed border-neutral-700 px-6 py-16 text-center hover:border-neutral-500 transition-colors duration-150`}
                    >
                        <FontAwesomeIcon icon={faCloudUploadAlt} css={tw`text-3xl text-neutral-500`} />
                        <p css={tw`mt-3 text-sm text-neutral-300`}>
                            {data.files.length === 0 ? 'Drop files here, or click to choose them.' : 'No file matches.'}
                        </p>
                    </button>
                ) : (
                    <div css={tw`grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-4`}>
                        {files.map((file) => (
                            <div
                                key={file.uuid}
                                className={'pd-neon'}
                                css={tw`relative flex flex-col overflow-hidden rounded-xl bg-neutral-800`}
                            >
                                <button
                                    type={'button'}
                                    onClick={() => setViewing(file)}
                                    css={tw`relative block w-full bg-neutral-900 overflow-hidden`}
                                    style={{ aspectRatio: '16 / 10' }}
                                    aria-label={file.name}
                                >
                                    <Preview file={file} />
                                    {!file.public && (
                                        <span
                                            css={tw`absolute top-2 left-2 flex items-center gap-1 rounded-full px-2 py-0.5 text-xs text-neutral-100`}
                                            style={{ background: 'rgba(0, 0, 0, 0.6)' }}
                                        >
                                            <FontAwesomeIcon icon={faLock} css={tw`text-xs`} />
                                            <span>Private</span>
                                        </span>
                                    )}
                                </button>
                                <div css={tw`relative px-3 pt-2.5 pb-2`}>
                                    <p css={tw`truncate text-sm font-medium text-neutral-100`} title={file.name}>
                                        {file.name}
                                    </p>
                                    <p css={tw`text-xs text-neutral-500`}>
                                        {bytesToString(file.size)}
                                        {file.createdAt ? ` · ${file.createdAt.toLocaleDateString()}` : ''}
                                        {` · ${file.views} `}
                                        <span>views</span>
                                    </p>
                                    <div css={tw`mt-1.5 flex items-center gap-0.5 -ml-1`}>
                                        <IconButton title={'Copy the link'} icon={faCopy} onClick={() => copy(file)} />
                                        <IconButton
                                            title={'Open'}
                                            icon={faExternalLinkAlt}
                                            onClick={() => window.open(file.url, '_blank', 'noopener')}
                                        />
                                        <IconButton
                                            title={'Download'}
                                            icon={faDownload}
                                            onClick={() => window.open(`${file.url}?dl=1`, '_blank', 'noopener')}
                                        />
                                        {(meta?.publicAllowed || file.public) && (
                                            <IconButton
                                                title={file.public ? 'Make it private' : 'Make it public'}
                                                icon={file.public ? faGlobe : faLock}
                                                onClick={() => togglePublic(file)}
                                            />
                                        )}
                                        <IconButton
                                            title={'Rename'}
                                            icon={faPen}
                                            onClick={() => {
                                                setNewName(file.name);
                                                setRenaming(file);
                                            }}
                                        />
                                        <span css={tw`flex-1`} />
                                        <IconButton
                                            title={'Delete'}
                                            icon={faTrashAlt}
                                            danger
                                            onClick={() => setRemoving(file)}
                                        />
                                    </div>
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </div>

            <Dialog open={viewing !== null} onClose={() => setViewing(null)} title={viewing?.name}>
                {viewing && (
                    <div css={tw`mt-4`}>
                        <Preview file={viewing} large />
                        <div css={tw`mt-4 flex flex-wrap gap-2 justify-end`}>
                            <Button.Text onClick={() => copy(viewing)}>
                                <FontAwesomeIcon icon={faCopy} css={tw`mr-2 text-xs`} />
                                <span>Copy the link</span>
                            </Button.Text>
                            <Button onClick={() => window.open(`${viewing.url}?dl=1`, '_blank', 'noopener')}>
                                <FontAwesomeIcon icon={faDownload} css={tw`mr-2 text-xs`} />
                                <span>Download</span>
                            </Button>
                        </div>
                    </div>
                )}
            </Dialog>

            <Dialog open={renaming !== null} onClose={() => setRenaming(null)} title={'Rename the file'}>
                <form
                    css={tw`mt-4`}
                    onSubmit={(e) => {
                        e.preventDefault();
                        rename();
                    }}
                >
                    <Input.Text
                        value={newName}
                        autoFocus
                        maxLength={180}
                        onChange={(e: React.ChangeEvent<HTMLInputElement>) => setNewName(e.currentTarget.value)}
                    />
                </form>
                <Dialog.Footer>
                    <Button.Text onClick={() => setRenaming(null)}>Cancel</Button.Text>
                    <Button onClick={rename} disabled={!newName.trim()}>
                        Save
                    </Button>
                </Dialog.Footer>
            </Dialog>

            <Dialog.Confirm
                open={removing !== null}
                title={'Delete this file?'}
                confirm={'Delete'}
                onClose={() => setRemoving(null)}
                onConfirmed={remove}
            >
                The file and its link are deleted for good.
            </Dialog.Confirm>
        </PageContentBlock>
    );
};
