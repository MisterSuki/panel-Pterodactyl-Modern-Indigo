import React, { useState } from 'react';
import tw from 'twin.macro';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faCheck, faCopy, faExternalLinkAlt, faGlobe, faLock } from '@fortawesome/free-solid-svg-icons';
import { Button } from '@/components/elements/button/index';
import { Dialog } from '@/components/elements/dialog';
import { FolderShare, shareHostedFolder } from '@/api/hosting';
import { httpErrorToHuman } from '@/api/http';

interface Props {
    // The folder ("Holidays/2026"), or null when the window is closed.
    folder: string | null;
    shares: FolderShare[];
    allowed: boolean;
    onClose: () => void;
    onChange: (shares: FolderShare[]) => void;
}

const copyText = (text: string): Promise<void> => {
    if (navigator.clipboard && window.isSecureContext) {
        return navigator.clipboard.writeText(text);
    }
    const area = document.createElement('textarea');
    area.value = text;
    document.body.appendChild(area);
    area.select();
    document.execCommand('copy');
    document.body.removeChild(area);

    return Promise.resolve();
};

// Shares a folder with a public link: anyone with it sees the files of the folder and of its folders (even the ones
// that are not public one by one), and can download them, until sharing is stopped.
export default ({ folder, shares, allowed, onClose, onChange }: Props) => {
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState('');
    const [copied, setCopied] = useState(false);
    const share = folder ? shares.find((s) => s.folder === folder) : undefined;
    const name = folder ? folder.split('/').pop() : '';

    const toggle = (on: boolean) => {
        if (!folder) return;
        setBusy(true);
        setError('');
        shareHostedFolder(folder, on)
            .then(onChange)
            .catch((e) => setError(httpErrorToHuman(e)))
            .then(() => setBusy(false));
    };

    const copy = () => {
        if (!share) return;
        copyText(share.url).then(() => {
            setCopied(true);
            window.setTimeout(() => setCopied(false), 1500);
        });
    };

    return (
        <Dialog
            open={folder !== null}
            onClose={() => {
                setError('');
                onClose();
            }}
            title={'Share the folder'}
        >
            <div css={tw`mt-3`}>
                <p css={tw`flex items-center gap-2 text-sm text-neutral-200`}>
                    <FontAwesomeIcon
                        icon={share ? faGlobe : faLock}
                        css={share ? tw`text-green-400` : tw`text-neutral-400`}
                    />
                    <span css={tw`font-semibold truncate`}>{name}</span>
                    <span css={tw`text-neutral-500`}>·</span>
                    <span css={tw`text-neutral-400`}>{share ? 'Public' : 'Private'}</span>
                </p>
                {share ? (
                    <>
                        <p css={tw`mt-2 text-sm text-neutral-400`}>
                            Anyone with the link sees the files of this folder and of its folders, and can download
                            them.
                        </p>
                        <div css={tw`mt-3 flex items-center gap-2`}>
                            <input
                                readOnly
                                value={share.url}
                                onFocus={(e) => e.currentTarget.select()}
                                css={tw`flex-1 min-w-0 h-9 rounded-md border border-neutral-600 bg-neutral-900 px-3 text-sm text-neutral-100 outline-none`}
                            />
                            <Button.Text size={Button.Sizes.Small} onClick={copy} title={'Copy the link'}>
                                <FontAwesomeIcon icon={copied ? faCheck : faCopy} />
                            </Button.Text>
                            <a href={share.url} target={'_blank'} rel={'noopener noreferrer'} title={'Open'}>
                                <Button.Text size={Button.Sizes.Small} type={'button'}>
                                    <FontAwesomeIcon icon={faExternalLinkAlt} />
                                </Button.Text>
                            </a>
                        </div>
                        <p css={tw`mt-2 text-xs text-neutral-500`}>
                            Opened {share.views} <span>times</span>
                        </p>
                    </>
                ) : (
                    <p css={tw`mt-2 text-sm text-neutral-400`}>
                        Only you see this folder. Share it to get a link: anyone with it will see its files and the
                        files of its folders, and can download them.
                    </p>
                )}
                {!allowed && !share && (
                    <p css={tw`mt-3 text-sm text-yellow-300`}>Public links are turned off by the administration.</p>
                )}
                {error && <p css={tw`mt-3 text-sm text-red-400`}>{error}</p>}
            </div>
            <Dialog.Footer>
                <Button.Text onClick={onClose}>Close</Button.Text>
                {share ? (
                    <Button.Danger disabled={busy} onClick={() => toggle(false)}>
                        Stop sharing
                    </Button.Danger>
                ) : (
                    <Button disabled={busy || !allowed} onClick={() => toggle(true)}>
                        Share with a link
                    </Button>
                )}
            </Dialog.Footer>
        </Dialog>
    );
};
