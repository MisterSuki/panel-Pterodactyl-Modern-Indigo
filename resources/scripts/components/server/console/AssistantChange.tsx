import React, { useState } from 'react';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faCheck, faFileAlt, faSpinner, faTimes, faUndo } from '@fortawesome/free-solid-svg-icons';
import classNames from 'classnames';
import { applyFileChange, dismissFileChange, FileChange, revertFileChange } from '@/api/server/assistant';
import { httpErrorToHuman } from '@/api/http';

// Lines shown of a piece before "Show everything".
const PREVIEW_LINES = 14;

const STATES: Record<FileChange['state'], { label: string; className: string }> = {
    proposed: { label: 'Not applied', className: 'bg-amber-500/15 text-amber-300 border-amber-500/30' },
    applied: { label: 'Applied', className: 'bg-green-500/15 text-green-300 border-green-500/30' },
    reverted: { label: 'Put back as before', className: 'bg-gray-500/15 text-gray-300 border-gray-500/30' },
    dismissed: { label: 'Refused', className: 'bg-gray-500/15 text-gray-400 border-gray-500/30' },
};

const Lines = ({ text, sign, all }: { text: string; sign: '-' | '+'; all: boolean }) => {
    const lines = text.replace(/\n$/, '').split('\n');
    const shown = all ? lines : lines.slice(0, PREVIEW_LINES);

    return (
        <>
            {shown.map((line, index) => (
                <div
                    key={index}
                    className={classNames(
                        'whitespace-pre-wrap break-all px-2',
                        sign === '-' ? 'bg-red-500/10 text-red-200' : 'bg-green-500/10 text-green-200'
                    )}
                >
                    <span className={'select-none opacity-60 mr-2'}>{sign}</span>
                    {line || ' '}
                </div>
            ))}
        </>
    );
};

// A change of a file prepared by the assistant: what it changes (lines removed in red, added in green), and the
// buttons to apply it, refuse it, or put the file back as it was.
export default ({
    uuid,
    change,
    onChange,
}: {
    uuid: string;
    change: FileChange;
    onChange: (c: FileChange) => void;
}) => {
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState('');
    const [askForce, setAskForce] = useState(false);
    const [all, setAll] = useState(false);
    const long = change.hunks.some(
        (h) => h.removed.split('\n').length > PREVIEW_LINES || h.added.split('\n').length > PREVIEW_LINES
    );

    const run = (action: () => Promise<FileChange>) => {
        setBusy(true);
        setError('');
        action()
            .then((updated) => {
                setAskForce(false);
                onChange(updated);
            })
            .catch((e) => {
                if (e?.response?.status === 409) {
                    setAskForce(true);
                } else {
                    setError(httpErrorToHuman(e));
                }
            })
            .then(() => setBusy(false));
    };

    return (
        <div className={'rounded-xl border border-gray-700 bg-gray-900/60 overflow-hidden'}>
            <div className={'flex items-start gap-2 px-3 py-2 border-b border-gray-700'}>
                <FontAwesomeIcon icon={faFileAlt} className={'mt-1 text-gray-400 flex-shrink-0'} />
                <div className={'min-w-0 flex-1'}>
                    <p className={'font-mono text-xs text-gray-100 break-all'} data-no-translate>
                        {change.path}
                        {change.created && <span className={'ml-2 font-sans text-green-300'}>(new file)</span>}
                    </p>
                    {change.summary && (
                        <p className={'text-xs text-gray-400 mt-0.5'} data-no-translate>
                            {change.summary}
                        </p>
                    )}
                </div>
                <span
                    className={classNames(
                        'flex-shrink-0 rounded-full border px-2 py-0.5 text-2xs font-semibold',
                        STATES[change.state].className
                    )}
                >
                    {STATES[change.state].label}
                </span>
            </div>

            <div className={'max-h-80 overflow-y-auto font-mono text-xs leading-5 py-1'} data-no-translate>
                {change.hunks.map((hunk, index) => (
                    <div key={index} className={classNames(index > 0 && 'mt-1 pt-1 border-t border-gray-800')}>
                        {hunk.removed !== '' && <Lines text={hunk.removed} sign={'-'} all={all} />}
                        {hunk.added !== '' && <Lines text={hunk.added} sign={'+'} all={all} />}
                    </div>
                ))}
            </div>
            {long && (
                <button
                    type={'button'}
                    onClick={() => setAll((v) => !v)}
                    className={'w-full border-t border-gray-800 py-1 text-xs text-gray-400 hover:text-gray-100'}
                >
                    {all ? 'Show less' : 'Show everything'}
                </button>
            )}

            {(change.state === 'proposed' || change.state === 'applied' || error) && (
                <div className={'flex flex-wrap items-center gap-2 px-3 py-2 border-t border-gray-700'}>
                    {change.state === 'proposed' && (
                        <>
                            <button
                                type={'button'}
                                disabled={busy}
                                onClick={() => run(() => applyFileChange(uuid, change.id))}
                                className={
                                    'inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 text-xs font-semibold text-white disabled:opacity-50'
                                }
                                style={{ background: 'rgb(var(--pd-accent-600))' }}
                            >
                                <FontAwesomeIcon icon={busy ? faSpinner : faCheck} spin={busy} />
                                <span>Apply</span>
                            </button>
                            <button
                                type={'button'}
                                disabled={busy}
                                onClick={() => run(() => dismissFileChange(uuid, change.id))}
                                className={
                                    'inline-flex items-center gap-1.5 rounded-md border border-gray-600 px-3 py-1.5 text-xs font-semibold text-gray-200 hover:bg-gray-700 disabled:opacity-50'
                                }
                            >
                                <FontAwesomeIcon icon={faTimes} />
                                <span>Refuse</span>
                            </button>
                        </>
                    )}
                    {change.state === 'applied' && !askForce && (
                        <>
                            <button
                                type={'button'}
                                disabled={busy}
                                onClick={() => run(() => revertFileChange(uuid, change.id))}
                                className={
                                    'inline-flex items-center gap-1.5 rounded-md border border-gray-600 px-3 py-1.5 text-xs font-semibold text-gray-200 hover:bg-gray-700 disabled:opacity-50'
                                }
                            >
                                <FontAwesomeIcon icon={busy ? faSpinner : faUndo} spin={busy} />
                                <span>Put the file back as before</span>
                            </button>
                            <span className={'text-xs text-gray-400'}>Restart the server if needed.</span>
                        </>
                    )}
                    {change.state === 'applied' && askForce && (
                        <div className={'w-full'}>
                            <p className={'text-xs text-amber-300'}>
                                The file was changed again after this change. Putting it back will also undo those
                                changes.
                            </p>
                            <div className={'mt-2 flex gap-2'}>
                                <button
                                    type={'button'}
                                    disabled={busy}
                                    onClick={() => run(() => revertFileChange(uuid, change.id, true))}
                                    className={
                                        'inline-flex items-center gap-1.5 rounded-md bg-amber-600 px-3 py-1.5 text-xs font-semibold text-white disabled:opacity-50'
                                    }
                                >
                                    <FontAwesomeIcon icon={busy ? faSpinner : faUndo} spin={busy} />
                                    <span>Put it back anyway</span>
                                </button>
                                <button
                                    type={'button'}
                                    onClick={() => setAskForce(false)}
                                    className={
                                        'rounded-md border border-gray-600 px-3 py-1.5 text-xs font-semibold text-gray-200 hover:bg-gray-700'
                                    }
                                >
                                    Cancel
                                </button>
                            </div>
                        </div>
                    )}
                    {error && <p className={'w-full text-xs text-red-400'}>{error}</p>}
                </div>
            )}
        </div>
    );
};
