import React, { useEffect, useRef, useState } from 'react';
import tw from 'twin.macro';
import useSWR from 'swr';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import {
    faCheck,
    faExchangeAlt,
    faExclamationTriangle,
    faFileAudio,
    faFileImage,
    faFileVideo,
    faSpinner,
} from '@fortawesome/free-solid-svg-icons';
import { Button } from '@/components/elements/button/index';
import { Dialog } from '@/components/elements/dialog';
import { convertHostedFile, convertTargets, FileConversion, getConversions, HostedFile } from '@/api/hosting';
import { httpErrorToHuman } from '@/api/http';

// What each format is, in a few words.
const FORMATS: Record<string, { label: string; text: string; icon: typeof faFileImage }> = {
    png: { label: 'PNG', text: 'Picture without loss, keeps transparency', icon: faFileImage },
    jpg: { label: 'JPG', text: 'Light picture, for photos', icon: faFileImage },
    webp: { label: 'WebP', text: 'Very light picture, for the web', icon: faFileImage },
    gif: { label: 'GIF', text: 'Animated picture (the first 30 seconds of a video)', icon: faFileImage },
    mp4: { label: 'MP4', text: 'Video that plays everywhere', icon: faFileVideo },
    webm: { label: 'WebM', text: 'Light video, for the web', icon: faFileVideo },
    mp3: { label: 'MP3', text: 'Sound that plays everywhere (the sound of a video)', icon: faFileAudio },
    ogg: { label: 'OGG', text: 'Light sound (Vorbis)', icon: faFileAudio },
    wav: { label: 'WAV', text: 'Sound without compression', icon: faFileAudio },
    m4a: { label: 'M4A', text: 'Sound in AAC (Apple)', icon: faFileAudio },
    flac: { label: 'FLAC', text: 'Sound without loss, compressed', icon: faFileAudio },
};

// Chooses the format to turn a file into.
export const ConvertDialog = ({
    file,
    onClose,
    onStarted,
}: {
    file: HostedFile | null;
    onClose: () => void;
    onStarted: () => void;
}) => {
    const [busy, setBusy] = useState<string | null>(null);
    const [error, setError] = useState('');
    const targets = file ? convertTargets(file) : [];

    useEffect(() => {
        setError('');
        setBusy(null);
    }, [file]);

    const start = (format: string) => {
        if (!file) return;
        setBusy(format);
        setError('');
        convertHostedFile(file.uuid, format)
            .then(() => {
                onStarted();
                onClose();
            })
            .catch((e) => setError(httpErrorToHuman(e)))
            .then(() => setBusy(null));
    };

    return (
        <Dialog open={file !== null} onClose={onClose} title={'Convert the file'}>
            <p css={tw`mt-2 text-sm text-neutral-400 truncate`}>{file?.name}</p>
            <div css={tw`mt-4 grid grid-cols-1 sm:grid-cols-2 gap-2`}>
                {targets.map((format) => (
                    <button
                        key={format}
                        type={'button'}
                        disabled={busy !== null}
                        onClick={() => start(format)}
                        css={tw`flex items-start gap-3 rounded-lg border border-neutral-600 bg-neutral-800 p-3 text-left hover:border-neutral-400 hover:bg-neutral-700 transition-colors duration-150 disabled:opacity-60`}
                    >
                        <FontAwesomeIcon
                            icon={busy === format ? faSpinner : FORMATS[format]?.icon || faExchangeAlt}
                            spin={busy === format}
                            css={tw`mt-0.5 text-neutral-400`}
                        />
                        <span css={tw`min-w-0`}>
                            <span css={tw`block text-sm font-semibold text-neutral-100`}>
                                {FORMATS[format]?.label || format.toUpperCase()}
                            </span>
                            <span css={tw`block text-xs text-neutral-400`}>{FORMATS[format]?.text}</span>
                        </span>
                    </button>
                ))}
            </div>
            <p css={tw`mt-3 text-xs text-neutral-500`}>
                The new file is put next to this one and counts in your space. A long video can take a few minutes.
            </p>
            {error && <p css={tw`mt-3 text-sm text-red-400`}>{error}</p>}
            <Dialog.Footer>
                <Button.Text onClick={onClose}>Close</Button.Text>
            </Dialog.Footer>
        </Dialog>
    );
};

// The conversions running, and the ones that ended in the last half hour; the list of the files is reloaded when one
// is done.
export const ConversionsPanel = ({ refreshKey, onDone }: { refreshKey: number; onDone: () => void }) => {
    const { data, mutate } = useSWR<FileConversion[]>('hosted-conversions', getConversions, {
        revalidateOnFocus: false,
    });
    const states = useRef<Record<number, string>>({});
    const active = !!data?.some((c) => c.state === 'queued' || c.state === 'running');

    // A new conversion was started: look at once.
    useEffect(() => {
        if (refreshKey > 0) mutate();
    }, [refreshKey]);

    // Every two seconds while one runs.
    useEffect(() => {
        if (!active) return;
        const timer = window.setInterval(() => mutate(), 2000);

        return () => window.clearInterval(timer);
    }, [active]);

    useEffect(() => {
        let finished = false;
        (data || []).forEach((c) => {
            const before = states.current[c.id];
            if (before && before !== 'done' && c.state === 'done') finished = true;
            states.current[c.id] = c.state;
        });
        if (finished) onDone();
    }, [data]);

    const shown = (data || []).filter(
        (c) =>
            c.state === 'queued' ||
            c.state === 'running' ||
            (c.createdAt && Date.now() - c.createdAt.getTime() < 30 * 60 * 1000)
    );
    if (shown.length === 0) return null;

    return (
        <div css={tw`flex flex-col gap-2 mb-6`}>
            {shown.map((c) => (
                <div
                    key={c.id}
                    css={tw`flex items-center gap-3 rounded-lg border border-neutral-700 bg-neutral-800 px-3 py-2`}
                >
                    <FontAwesomeIcon
                        icon={c.state === 'done' ? faCheck : c.state === 'failed' ? faExclamationTriangle : faSpinner}
                        spin={c.state === 'queued' || c.state === 'running'}
                        css={[
                            tw`text-sm`,
                            c.state === 'done'
                                ? tw`text-green-400`
                                : c.state === 'failed'
                                ? tw`text-red-400`
                                : tw`text-neutral-400`,
                        ]}
                    />
                    <div css={tw`min-w-0 flex-1`}>
                        <p css={tw`flex items-center gap-2 text-sm text-neutral-100 min-w-0`}>
                            <span css={tw`truncate`}>{c.name}</span>
                            <FontAwesomeIcon icon={faExchangeAlt} css={tw`text-2xs text-neutral-500 flex-shrink-0`} />
                            <span css={tw`font-semibold flex-shrink-0`}>
                                {FORMATS[c.format]?.label || c.format.toUpperCase()}
                            </span>
                        </p>
                        {c.state === 'failed' ? (
                            <p css={tw`text-xs text-red-400 truncate`} title={c.error || undefined}>
                                {c.error || 'The conversion failed.'}
                            </p>
                        ) : c.state === 'done' ? (
                            <p css={tw`text-xs text-neutral-400`}>Converted: the new file is with the others.</p>
                        ) : (
                            <div css={tw`mt-1.5 h-1.5 w-full rounded-full bg-neutral-700 overflow-hidden`}>
                                <div
                                    css={tw`h-full rounded-full transition-all duration-500`}
                                    style={{
                                        width: `${c.state === 'queued' ? 3 : Math.max(3, c.progress)}%`,
                                        background: 'rgb(var(--pd-accent-500))',
                                    }}
                                />
                            </div>
                        )}
                    </div>
                    {(c.state === 'queued' || c.state === 'running') && (
                        <span css={tw`text-xs text-neutral-400 tabular-nums flex-shrink-0`}>
                            {c.state === 'queued' ? 'Waiting...' : `${c.progress}%`}
                        </span>
                    )}
                </div>
            ))}
        </div>
    );
};
