import React, { useEffect, useMemo, useRef, useState } from 'react';
import tw from 'twin.macro';
import useSWR from 'swr';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import {
    faArrowRight,
    faCheck,
    faChevronRight,
    faCloudUploadAlt,
    faCopy,
    faDownload,
    faExternalLinkAlt,
    faFile,
    faFileAudio,
    faFolder,
    faFolderPlus,
    faGlobe,
    faLock,
    faPen,
    faPlay,
    faSearch,
    faTimes,
    faRedo,
    faTrashAlt,
} from '@fortawesome/free-solid-svg-icons';
import PageContentBlock from '@/components/elements/PageContentBlock';
import { Button } from '@/components/elements/button/index';
import { Dialog } from '@/components/elements/dialog';
import { Input } from '@/components/elements/inputs';
import {
    bulkHostedFiles,
    BulkAction,
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

// Files sent at the same time; the others wait their turn.
const PARALLEL = 3;

interface Upload {
    id: number;
    name: string;
    folder: string;
    size: number;
    sent: number;
    started: boolean;
    error: string | null;
    signal: { cancelled: boolean };
    file: File;
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

const join = (...parts: string[]) => parts.filter(Boolean).join('/');

// The files of a dropped folder, with the folder each one is in (read from the entries of the drop).
const readEntries = async (items: DataTransferItemList): Promise<{ file: File; dir: string }[]> => {
    const out: { file: File; dir: string }[] = [];
    const walk = async (entry: any, dir: string): Promise<void> => {
        if (!entry) return;
        if (entry.isFile) {
            const file: File = await new Promise((resolve, reject) => entry.file(resolve, reject));
            out.push({ file, dir });
        } else if (entry.isDirectory) {
            const reader = entry.createReader();
            const next = (): Promise<any[]> => new Promise((resolve, reject) => reader.readEntries(resolve, reject));
            let batch = await next();
            while (batch.length) {
                for (const child of batch) await walk(child, join(dir, entry.name));
                batch = await next();
            }
        }
    };
    const entries = Array.from(items)
        .map((item) => (item.webkitGetAsEntry ? item.webkitGetAsEntry() : null))
        .filter(Boolean);
    for (const entry of entries) await walk(entry, '');

    return out;
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

const Check = ({ checked, onClick, label }: { checked: boolean; onClick: () => void; label: string }) => (
    <button
        type={'button'}
        role={'checkbox'}
        aria-checked={checked}
        aria-label={label}
        title={label}
        onClick={(e) => {
            e.stopPropagation();
            onClick();
        }}
        css={[
            tw`flex items-center justify-center w-5 h-5 rounded border text-white transition-colors duration-100`,
            checked ? tw`border-primary-500 bg-primary-500` : tw`border-neutral-400 hover:border-neutral-200`,
        ]}
        style={checked ? undefined : { background: 'rgba(0, 0, 0, 0.45)' }}
    >
        {checked && <FontAwesomeIcon icon={faCheck} css={tw`text-xs`} />}
    </button>
);

// "My files": the pictures, videos and files the person keeps on the panel, in folders, with a link to share each one.
export default () => {
    const { addFlash, clearFlashes, clearAndAddHttpError } = useFlash();
    const { data, error, mutate } = useSWR<{ files: HostedFile[]; meta: HostingMeta }>('hosted-files', getHostedFiles, {
        revalidateOnFocus: false,
    });
    const [path, setPath] = useState('');
    const [filter, setFilter] = useState<Filter>('all');
    const [search, setSearch] = useState('');
    const [uploads, setUploads] = useState<Upload[]>([]);
    const [dragging, setDragging] = useState(false);
    const [viewing, setViewing] = useState<HostedFile | null>(null);
    const [renaming, setRenaming] = useState<HostedFile | null>(null);
    const [newName, setNewName] = useState('');
    const [removing, setRemoving] = useState<HostedFile[] | null>(null);
    const [moving, setMoving] = useState(false);
    const [target, setTarget] = useState('');
    const [creating, setCreating] = useState(false);
    const [folderName, setFolderName] = useState('');
    // Folders made here and still empty (a folder exists on the panel once a file is in it).
    const [emptyFolders, setEmptyFolders] = useState<string[]>([]);
    const [selected, setSelected] = useState<Set<string>>(new Set());
    // Folders checked: everything inside them (sub-folders too) is part of the selection.
    const [selectedFolders, setSelectedFolders] = useState<Set<string>>(new Set());
    const picker = useRef<HTMLInputElement>(null);
    const folderPicker = useRef<HTMLInputElement>(null);
    const counter = useRef(0);

    useEffect(() => {
        if (error) clearAndAddHttpError({ key: 'files', error });
    }, [error]);

    const unselect = () => {
        setSelected(new Set());
        setSelectedFolders(new Set());
    };

    useEffect(unselect, [path]);

    const all = data?.files || [];
    const meta = data?.meta;
    const ratio = meta && meta.quota > 0 ? Math.min(1, meta.used / meta.quota) : 0;
    const words = search.trim().toLowerCase();

    // The files shown: the ones of this folder, or every matching one when searching.
    const files = useMemo(
        () =>
            all.filter(
                (file) =>
                    (filter === 'all' || file.kind === filter) &&
                    (words ? file.name.toLowerCase().includes(words) : file.folder === path)
            ),
        [all, filter, words, path]
    );

    // The folders inside this one, with what they hold.
    const folders = useMemo(() => {
        if (words) return [];
        const found = new Map<string, { files: number; size: number }>();
        const prefix = path ? `${path}/` : '';
        const add = (folder: string, size: number, counts: boolean) => {
            if (!folder.startsWith(prefix) || folder === path) return;
            const name = folder.slice(prefix.length).split('/')[0];
            const item = found.get(name) || { files: 0, size: 0 };
            if (counts) {
                item.files += 1;
                item.size += size;
            }
            found.set(name, item);
        };
        all.forEach((file) => add(file.folder, file.size, true));
        emptyFolders.forEach((folder) => add(folder, 0, false));

        return Array.from(found.entries()).sort(([a], [b]) => a.localeCompare(b));
    }, [all, emptyFolders, path, words]);

    const allFolders = useMemo(
        () =>
            Array.from(new Set([...all.map((f) => f.folder), ...emptyFolders]))
                .filter(Boolean)
                .sort(),
        [all, emptyFolders]
    );

    // Sending: at most PARALLEL at a time, the others wait.
    useEffect(() => {
        const running = uploads.filter((u) => u.started && !u.error).length;
        const waiting = uploads.filter((u) => !u.started && !u.error);
        waiting.slice(0, Math.max(0, PARALLEL - running)).forEach((upload) => {
            const change = (values: Partial<Upload>) =>
                setUploads((list) => list.map((item) => (item.id === upload.id ? { ...item, ...values } : item)));
            change({ started: true });
            uploadHostedFile(upload.file, upload.folder, (sent) => change({ sent }), upload.signal)
                .then((saved) => {
                    setUploads((list) => list.filter((item) => item.id !== upload.id));
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
                        setUploads((list) => list.filter((item) => item.id !== upload.id));
                    } else {
                        change({ error: e && e.response ? httpErrorToHuman(e) : 'The file could not be sent.' });
                    }
                });
        });
    }, [uploads]);

    const send = (list: { file: File; dir: string }[]) => {
        clearFlashes('files');
        const items: Upload[] = list.map(({ file, dir }) => ({
            id: ++counter.current,
            name: file.name,
            folder: join(path, dir),
            size: file.size,
            sent: 0,
            started: false,
            error:
                meta && file.size > meta.maxFile
                    ? 'This file is bigger than the biggest file you may send.'
                    : file.size === 0
                    ? 'This file is empty.'
                    : null,
            signal: { cancelled: false },
            file,
        }));
        setUploads((current) => [...current, ...items]);
    };

    const fromPicker = (input: HTMLInputElement) => {
        const list = Array.from(input.files || []).map((file) => {
            const relative = (file as any).webkitRelativePath || '';
            const dir = relative.includes('/') ? relative.split('/').slice(0, -1).join('/') : '';

            return { file, dir };
        });
        if (list.length) send(list);
        input.value = '';
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
        const file = renaming;
        setRenaming(null);
        updateHostedFile(file.uuid, { name: newName.trim() })
            .then(replace)
            .catch((e) => clearAndAddHttpError({ key: 'files', error: e }));
    };

    const bulk = (action: BulkAction, uuids: string[], folder?: string) => {
        clearFlashes('files');
        return bulkHostedFiles(action, uuids, folder)
            .then(() => {
                unselect();
                return mutate();
            })
            .catch((e) => clearAndAddHttpError({ key: 'files', error: e }));
    };

    const remove = () => {
        if (!removing) return;
        const list = removing;
        setRemoving(null);
        if (list.length === 1) {
            deleteHostedFile(list[0].uuid)
                .then(() => mutate())
                .catch((e) => clearAndAddHttpError({ key: 'files', error: e }));
        } else {
            bulk(
                'delete',
                list.map((f) => f.uuid)
            );
        }
    };

    const toggle = (uuid: string) =>
        setSelected((current) => {
            const next = new Set(current);
            if (next.has(uuid)) next.delete(uuid);
            else next.add(uuid);
            return next;
        });

    const toggleFolder = (folder: string) =>
        setSelectedFolders((current) => {
            const next = new Set(current);
            if (next.has(folder)) next.delete(folder);
            else next.add(folder);
            return next;
        });

    // The checked folder a file is in (the highest one), if any.
    const inFolder = (file: HostedFile) =>
        Array.from(selectedFolders)
            .filter((d) => file.folder === d || file.folder.startsWith(`${d}/`))
            .sort((a, b) => a.length - b.length)[0];

    const folderPaths = folders.map(([name]) => join(path, name));
    const allSelected =
        files.length + folderPaths.length > 0 &&
        files.every((f) => selected.has(f.uuid)) &&
        folderPaths.every((d) => selectedFolders.has(d));
    const selection = all.filter((f) => selected.has(f.uuid) || !!inFolder(f));
    const selecting = selected.size + selectedFolders.size > 0;

    const selectAll = () => {
        if (allSelected) {
            unselect();
        } else {
            setSelected(new Set(files.map((f) => f.uuid)));
            setSelectedFolders(new Set(folderPaths));
        }
    };

    // Moving keeps the tree: a checked folder goes into the target with what it holds.
    const moveTo = (destination: string) => {
        const groups = new Map<string, string[]>();
        selection.forEach((file) => {
            const folder = inFolder(file);
            const next = folder
                ? join(destination, folder.split('/').pop() || '', file.folder.slice(folder.length + 1))
                : destination;
            groups.set(next, [...(groups.get(next) || []), file.uuid]);
        });
        clearFlashes('files');
        Promise.all(Array.from(groups.entries()).map(([folder, uuids]) => bulkHostedFiles('move', uuids, folder)))
            .then(() => {
                unselect();
                return mutate();
            })
            .catch((e) => clearAndAddHttpError({ key: 'files', error: e }));
    };

    const retry = (ids?: number[]) =>
        setUploads((list) =>
            list.map((u) =>
                u.error && (!ids || ids.includes(u.id))
                    ? { ...u, error: null, started: false, sent: 0, signal: { cancelled: false } }
                    : u
            )
        );
    const crumbs = path ? path.split('/') : [];

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
                    const items = e.dataTransfer.items;
                    if (items && items.length && typeof (items[0] as any).webkitGetAsEntry === 'function') {
                        readEntries(items)
                            .then((list) => list.length && send(list))
                            .catch(() => send(Array.from(e.dataTransfer.files).map((file) => ({ file, dir: '' }))));
                    } else if (e.dataTransfer.files.length) {
                        send(Array.from(e.dataTransfer.files).map((file) => ({ file, dir: '' })));
                    }
                }}
                css={tw`relative`}
            >
                {dragging && (
                    <div
                        css={tw`absolute inset-0 z-20 flex flex-col items-center justify-center gap-3 rounded-2xl border-2 border-dashed pointer-events-none`}
                        style={{ borderColor: 'rgb(var(--pd-accent-500))', background: 'rgb(10 10 10 / 0.85)' }}
                    >
                        <FontAwesomeIcon icon={faCloudUploadAlt} css={tw`text-4xl text-neutral-200`} />
                        <p css={tw`text-sm text-neutral-200`}>Drop files or folders to send them</p>
                    </div>
                )}

                <div css={tw`flex flex-wrap items-end justify-between gap-4 mb-6`}>
                    <div>
                        <h1 css={tw`text-3xl font-semibold text-neutral-50 tracking-tight`}>My files</h1>
                        <p css={tw`text-sm text-neutral-400 mt-1.5`}>
                            Keep pictures, videos and files here, and share them with a link.
                        </p>
                    </div>
                    <div css={tw`flex flex-wrap items-center gap-2`}>
                        <input
                            ref={picker}
                            type={'file'}
                            multiple
                            css={tw`hidden`}
                            onChange={(e) => fromPicker(e.currentTarget)}
                        />
                        <input
                            ref={folderPicker}
                            type={'file'}
                            multiple
                            css={tw`hidden`}
                            {...({ webkitdirectory: '', directory: '' } as any)}
                            onChange={(e) => fromPicker(e.currentTarget)}
                        />
                        <Button.Text onClick={() => setCreating(true)} disabled={!meta}>
                            <FontAwesomeIcon icon={faFolderPlus} css={tw`mr-2 text-xs`} />
                            <span>New folder</span>
                        </Button.Text>
                        <Button.Text onClick={() => folderPicker.current?.click()} disabled={!meta}>
                            <FontAwesomeIcon icon={faFolder} css={tw`mr-2 text-xs`} />
                            <span>Send a folder</span>
                        </Button.Text>
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
                        {uploads.length > 1 && (
                            <div css={tw`flex items-center justify-between text-xs text-neutral-400`}>
                                <span>
                                    {uploads.filter((u) => !u.error).length} <span>files being sent</span>
                                </span>
                                {uploads.some((u) => u.error) && (
                                    <span css={tw`flex items-center gap-4`}>
                                        <button
                                            type={'button'}
                                            css={tw`hover:text-neutral-100`}
                                            onClick={() => retry()}
                                        >
                                            <span>Retry all</span>
                                        </button>
                                        <button
                                            type={'button'}
                                            css={tw`hover:text-neutral-100`}
                                            onClick={() => setUploads((list) => list.filter((u) => !u.error))}
                                        >
                                            <span>Clear the errors</span>
                                        </button>
                                    </span>
                                )}
                            </div>
                        )}
                        {uploads.slice(0, 12).map((upload) => (
                            <div
                                key={upload.id}
                                className={'pd-neon'}
                                css={tw`relative flex items-center gap-3 rounded-xl bg-neutral-800 px-4 py-3`}
                            >
                                <FontAwesomeIcon icon={faCloudUploadAlt} css={tw`text-neutral-500`} />
                                <div css={tw`flex-1 min-w-0`}>
                                    <div css={tw`flex items-baseline justify-between gap-3 text-sm`}>
                                        <span css={tw`truncate text-neutral-100`}>
                                            {upload.folder && <span css={tw`text-neutral-500`}>{upload.folder}/</span>}
                                            {upload.name}
                                        </span>
                                        <span css={tw`flex-shrink-0 font-mono text-xs text-neutral-400`}>
                                            {upload.error
                                                ? ''
                                                : upload.started
                                                ? `${Math.floor((upload.sent / Math.max(1, upload.size)) * 100)} %`
                                                : '…'}
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
                                {upload.error && upload.size > 0 && (
                                    <IconButton title={'Retry'} icon={faRedo} onClick={() => retry([upload.id])} />
                                )}
                                <IconButton
                                    title={upload.error ? 'Close' : 'Cancel'}
                                    icon={faTimes}
                                    onClick={() => {
                                        upload.signal.cancelled = true;
                                        if (upload.error || !upload.started) {
                                            setUploads((list) => list.filter((item) => item.id !== upload.id));
                                        }
                                    }}
                                />
                            </div>
                        ))}
                        {uploads.length > 12 && (
                            <p css={tw`text-xs text-neutral-500`}>
                                + {uploads.length - 12} <span>more</span>
                            </p>
                        )}
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
                            placeholder={'Search in every folder'}
                            css={tw`h-8 w-64 rounded-md border border-neutral-600 bg-neutral-900 pl-8 pr-3 text-sm text-neutral-100 placeholder-neutral-500 outline-none focus:border-neutral-500`}
                        />
                    </label>
                </div>

                <div
                    css={tw`flex flex-wrap items-center justify-between gap-3 mb-4 rounded-lg px-3 py-1.5`}
                    style={{
                        minHeight: '2.25rem',
                        background: selecting ? 'rgb(var(--pd-accent-500) / 0.12)' : 'transparent',
                        border: selecting ? '1px solid rgb(var(--pd-accent-500) / 0.35)' : '1px solid transparent',
                    }}
                >
                    <div css={tw`flex items-center gap-3 min-w-0`}>
                        <Check
                            checked={allSelected}
                            label={allSelected ? 'Unselect all' : 'Select all'}
                            onClick={selectAll}
                        />
                        {selecting ? (
                            <span css={tw`text-sm text-neutral-100`}>
                                {selection.length} <span>files selected</span>
                                {selectedFolders.size > 0 && (
                                    <span css={tw`text-neutral-400`}>
                                        {' '}
                                        (<span>including</span> {selectedFolders.size} <span>folders</span>)
                                    </span>
                                )}
                                <span css={tw`text-neutral-400`}>
                                    {' · '}
                                    {bytesToString(selection.reduce((t, f) => t + f.size, 0))}
                                </span>
                            </span>
                        ) : (
                            <nav css={tw`flex flex-wrap items-center gap-1 text-sm min-w-0`}>
                                <button
                                    type={'button'}
                                    onClick={() => setPath('')}
                                    css={[path ? tw`text-neutral-400 hover:text-neutral-100` : tw`text-neutral-100`]}
                                >
                                    <span>My files</span>
                                </button>
                                {crumbs.map((part, index) => (
                                    <React.Fragment key={index}>
                                        <FontAwesomeIcon icon={faChevronRight} css={tw`text-xs text-neutral-600`} />
                                        <button
                                            type={'button'}
                                            onClick={() => setPath(crumbs.slice(0, index + 1).join('/'))}
                                            css={[
                                                index === crumbs.length - 1
                                                    ? tw`text-neutral-100`
                                                    : tw`text-neutral-400 hover:text-neutral-100`,
                                                tw`truncate`,
                                            ]}
                                        >
                                            {part}
                                        </button>
                                    </React.Fragment>
                                ))}
                            </nav>
                        )}
                    </div>
                    {selecting && (
                        <div css={tw`flex flex-wrap items-center gap-1`}>
                            {meta?.publicAllowed && (
                                <Button.Text
                                    size={Button.Sizes.Small}
                                    onClick={() =>
                                        bulk(
                                            'public',
                                            selection.map((f) => f.uuid)
                                        )
                                    }
                                >
                                    <FontAwesomeIcon icon={faGlobe} css={tw`mr-2 text-xs`} />
                                    <span>Public</span>
                                </Button.Text>
                            )}
                            <Button.Text
                                size={Button.Sizes.Small}
                                onClick={() =>
                                    bulk(
                                        'private',
                                        selection.map((f) => f.uuid)
                                    )
                                }
                            >
                                <FontAwesomeIcon icon={faLock} css={tw`mr-2 text-xs`} />
                                <span>Private</span>
                            </Button.Text>
                            <Button.Text
                                size={Button.Sizes.Small}
                                onClick={() => {
                                    setTarget(path);
                                    setMoving(true);
                                }}
                            >
                                <FontAwesomeIcon icon={faArrowRight} css={tw`mr-2 text-xs`} />
                                <span>Move</span>
                            </Button.Text>
                            <Button.Danger
                                size={Button.Sizes.Small}
                                onClick={() => {
                                    if (selection.length > 0) {
                                        setRemoving(selection);
                                    } else {
                                        // Only empty folders made here: nothing to delete on the panel.
                                        setEmptyFolders((list) =>
                                            list.filter(
                                                (d) =>
                                                    !Array.from(selectedFolders).some(
                                                        (x) => d === x || d.startsWith(`${x}/`)
                                                    )
                                            )
                                        );
                                        unselect();
                                    }
                                }}
                            >
                                <FontAwesomeIcon icon={faTrashAlt} css={tw`mr-2 text-xs`} />
                                <span>Delete</span>
                            </Button.Danger>
                            <IconButton title={'Unselect all'} icon={faTimes} onClick={unselect} />
                        </div>
                    )}
                </div>

                {!data ? (
                    <div css={tw`grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-4`}>
                        {[0, 1, 2, 3].map((i) => (
                            <div key={i} className={'pd-skeleton'} css={tw`h-52 rounded-xl`} />
                        ))}
                    </div>
                ) : files.length === 0 && folders.length === 0 ? (
                    <button
                        type={'button'}
                        onClick={() => picker.current?.click()}
                        css={tw`w-full rounded-2xl border-2 border-dashed border-neutral-700 px-6 py-16 text-center hover:border-neutral-500 transition-colors duration-150`}
                    >
                        <FontAwesomeIcon icon={faCloudUploadAlt} css={tw`text-3xl text-neutral-500`} />
                        <p css={tw`mt-3 text-sm text-neutral-300`}>
                            {all.length === 0 || (!words && filter === 'all')
                                ? 'Drop files or folders here, or click to choose files.'
                                : 'No file matches.'}
                        </p>
                    </button>
                ) : (
                    <>
                        {folders.length > 0 && (
                            <div css={tw`grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-3 mb-4`}>
                                {folders.map(([name, info]) => (
                                    <div
                                        key={name}
                                        role={'button'}
                                        tabIndex={0}
                                        onClick={() =>
                                            selecting ? toggleFolder(join(path, name)) : setPath(join(path, name))
                                        }
                                        onKeyDown={(e) => e.key === 'Enter' && setPath(join(path, name))}
                                        className={'pd-neon'}
                                        css={tw`relative flex items-center gap-3 rounded-xl bg-neutral-800 pl-3 pr-4 py-3 text-left cursor-pointer hover:bg-neutral-700 transition-colors duration-150`}
                                        style={
                                            selectedFolders.has(join(path, name))
                                                ? { boxShadow: '0 0 0 2px rgb(var(--pd-accent-500))' }
                                                : undefined
                                        }
                                    >
                                        <Check
                                            checked={selectedFolders.has(join(path, name))}
                                            label={'Select'}
                                            onClick={() => toggleFolder(join(path, name))}
                                        />
                                        <FontAwesomeIcon
                                            icon={faFolder}
                                            css={tw`text-xl`}
                                            style={{ color: '#f5a524' }}
                                        />
                                        <span css={tw`min-w-0`}>
                                            <span css={tw`block truncate text-sm font-medium text-neutral-100`}>
                                                {name}
                                            </span>
                                            <span css={tw`block text-xs text-neutral-500`}>
                                                {info.files} <span>files</span> · {bytesToString(info.size)}
                                            </span>
                                        </span>
                                    </div>
                                ))}
                            </div>
                        )}
                        <div css={tw`grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-4`}>
                            {files.map((file) => {
                                const isSelected = selected.has(file.uuid) || !!inFolder(file);

                                return (
                                    <div
                                        key={file.uuid}
                                        className={'pd-neon'}
                                        css={tw`relative flex flex-col overflow-hidden rounded-xl bg-neutral-800`}
                                        style={
                                            isSelected
                                                ? { boxShadow: '0 0 0 2px rgb(var(--pd-accent-500))' }
                                                : undefined
                                        }
                                    >
                                        <button
                                            type={'button'}
                                            onClick={() => (selecting ? toggle(file.uuid) : setViewing(file))}
                                            css={tw`relative block w-full bg-neutral-900 overflow-hidden`}
                                            style={{ aspectRatio: '16 / 10' }}
                                            aria-label={file.name}
                                        >
                                            <Preview file={file} />
                                            {!file.public && (
                                                <span
                                                    css={tw`absolute bottom-2 left-2 flex items-center gap-1 rounded-full px-2 py-0.5 text-xs text-neutral-100`}
                                                    style={{ background: 'rgba(0, 0, 0, 0.6)' }}
                                                >
                                                    <FontAwesomeIcon icon={faLock} css={tw`text-xs`} />
                                                    <span>Private</span>
                                                </span>
                                            )}
                                        </button>
                                        <span css={tw`absolute top-2 left-2`}>
                                            <Check
                                                checked={isSelected}
                                                label={'Select'}
                                                onClick={() => toggle(file.uuid)}
                                            />
                                        </span>
                                        <div css={tw`relative px-3 pt-2.5 pb-2`}>
                                            <p
                                                css={tw`truncate text-sm font-medium text-neutral-100`}
                                                title={file.name}
                                            >
                                                {file.name}
                                            </p>
                                            <p css={tw`truncate text-xs text-neutral-500`}>
                                                {words && file.folder ? `${file.folder} · ` : ''}
                                                {bytesToString(file.size)}
                                                {file.createdAt ? ` · ${file.createdAt.toLocaleDateString()}` : ''}
                                                {` · ${file.views} `}
                                                <span>views</span>
                                            </p>
                                            <div css={tw`mt-1.5 flex items-center gap-0.5 -ml-1`}>
                                                <IconButton
                                                    title={'Copy the link'}
                                                    icon={faCopy}
                                                    onClick={() => copy(file)}
                                                />
                                                <IconButton
                                                    title={'Open'}
                                                    icon={faExternalLinkAlt}
                                                    onClick={() => window.open(file.url, '_blank', 'noopener')}
                                                />
                                                <IconButton
                                                    title={'Download'}
                                                    icon={faDownload}
                                                    onClick={() =>
                                                        window.open(`${file.url}?dl=1`, '_blank', 'noopener')
                                                    }
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
                                                    onClick={() => setRemoving([file])}
                                                />
                                            </div>
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    </>
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

            <Dialog open={moving} onClose={() => setMoving(false)} title={'Move to a folder'}>
                <form
                    css={tw`mt-4`}
                    onSubmit={(e) => {
                        e.preventDefault();
                        setMoving(false);
                        moveTo(target.trim());
                    }}
                >
                    <Input.Text
                        value={target}
                        autoFocus
                        list={'pd-hosting-folders'}
                        placeholder={'Empty: the top of My files'}
                        onChange={(e: React.ChangeEvent<HTMLInputElement>) => setTarget(e.currentTarget.value)}
                    />
                    <datalist id={'pd-hosting-folders'}>
                        {allFolders.map((folder) => (
                            <option key={folder} value={folder} />
                        ))}
                    </datalist>
                    <p css={tw`mt-2 text-xs text-neutral-400`}>
                        A folder in a folder: separate them with a slash, like Holidays/2026. A new folder is made.
                    </p>
                </form>
                <Dialog.Footer>
                    <Button.Text onClick={() => setMoving(false)}>Cancel</Button.Text>
                    <Button
                        onClick={() => {
                            setMoving(false);
                            moveTo(target.trim());
                        }}
                    >
                        Move
                    </Button>
                </Dialog.Footer>
            </Dialog>

            <Dialog open={creating} onClose={() => setCreating(false)} title={'New folder'}>
                <form
                    css={tw`mt-4`}
                    onSubmit={(e) => {
                        e.preventDefault();
                        const name = folderName.trim().replace(/\//g, '-');
                        if (!name) return;
                        setEmptyFolders((list) => Array.from(new Set([...list, join(path, name)])));
                        setCreating(false);
                        setFolderName('');
                        setPath(join(path, name));
                    }}
                >
                    <Input.Text
                        value={folderName}
                        autoFocus
                        maxLength={60}
                        placeholder={'Name of the folder'}
                        onChange={(e: React.ChangeEvent<HTMLInputElement>) => setFolderName(e.currentTarget.value)}
                    />
                    <p css={tw`mt-2 text-xs text-neutral-400`}>
                        The folder is kept once a file is sent or moved into it.
                    </p>
                </form>
                <Dialog.Footer>
                    <Button.Text onClick={() => setCreating(false)}>Cancel</Button.Text>
                    <Button
                        disabled={!folderName.trim()}
                        onClick={() => {
                            const name = folderName.trim().replace(/\//g, '-');
                            setEmptyFolders((list) => Array.from(new Set([...list, join(path, name)])));
                            setCreating(false);
                            setFolderName('');
                            setPath(join(path, name));
                        }}
                    >
                        Create
                    </Button>
                </Dialog.Footer>
            </Dialog>

            <Dialog.Confirm
                open={removing !== null}
                title={removing && removing.length > 1 ? 'Delete these files?' : 'Delete this file?'}
                confirm={'Delete'}
                onClose={() => setRemoving(null)}
                onConfirmed={remove}
            >
                The files and their links are deleted for good.
            </Dialog.Confirm>
        </PageContentBlock>
    );
};
