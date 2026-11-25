import React, { useEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import useSWR from 'swr';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faHistory, faMagic, faPaperPlane, faRedo, faSpinner, faTimes } from '@fortawesome/free-solid-svg-icons';
import classNames from 'classnames';
import { ServerContext } from '@/state/server';
import { askAssistant, AssistantMessage, FileChange, getAssistant, getFileChanges } from '@/api/server/assistant';
import { httpErrorToHuman } from '@/api/http';
import { readConsole } from '@/components/server/console/consoleText';
import AssistantText from '@/components/server/console/AssistantText';
import AssistantChange from '@/components/server/console/AssistantChange';

// What is sent of the console: its last lines (the panel keeps the last 200 of them).
const CONSOLE_LINES = 300;

const SUGGESTIONS = [
    'Why does my server crash or not start?',
    'Explain the errors of the console and how to fix them.',
    'How can I make my server faster?',
    'How do I install a mod or a plugin on this server?',
];

// The width of the panel, and the width of the window from which the page moves aside to stay visible next to it.
const WIDTH = 460;
const SIDE_BY_SIDE = 1200;

// A message of the conversation, with the changes of files the assistant prepared in it.
type ChatMessage = AssistantMessage & { changes?: number[] };

const storageKey = (uuid: string) => `pd-assistant:${uuid}`;

const load = (uuid: string): { messages: ChatMessage[]; changes: Record<number, FileChange> } => {
    try {
        const saved = JSON.parse(window.sessionStorage.getItem(storageKey(uuid)) || '{}');
        const messages = Array.isArray(saved) ? saved : saved.messages;

        return {
            messages: Array.isArray(messages)
                ? messages.filter((m: any) => m && typeof m.content === 'string').slice(-30)
                : [],
            changes: saved && !Array.isArray(saved) && saved.changes ? saved.changes : {},
        };
    } catch {
        return { messages: [], changes: {} };
    }
};

// The AI assistant of the server: a button next to the state of the server, and a panel on the right where the
// person asks about the errors of their server. Each question sends the end of the console with it.
export default ({ className }: { className?: string }) => {
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const status = ServerContext.useStoreState((state) => state.status.value);
    const { data: info, mutate } = useSWR(['assistant', uuid], () => getAssistant(uuid), {
        revalidateOnFocus: false,
        shouldRetryOnError: false,
    });
    const [open, setOpen] = useState(false);
    const [messages, setMessages] = useState<ChatMessage[]>(() => load(uuid).messages);
    // The changes of files, by id (kept up to date when one is applied or put back).
    const [changes, setChanges] = useState<Record<number, FileChange>>(() => load(uuid).changes);
    // The list of every change of the last 30 days, instead of the conversation.
    const [history, setHistory] = useState<FileChange[] | null>(null);
    const [draft, setDraft] = useState('');
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState('');
    const bottom = useRef<HTMLDivElement>(null);
    const input = useRef<HTMLTextAreaElement>(null);

    useEffect(() => {
        try {
            window.sessionStorage.setItem(storageKey(uuid), JSON.stringify({ messages: messages.slice(-30), changes }));
        } catch {
            // Not kept: the conversation stays for this page only.
        }
    }, [messages, changes]);

    useEffect(() => {
        bottom.current?.scrollIntoView({ block: 'end' });
    }, [messages, busy, open]);

    // On a wide screen the page moves to the left, so the console stays visible next to the conversation.
    useEffect(() => {
        if (!open) return;
        const place = () => {
            document.body.style.paddingRight = window.innerWidth >= SIDE_BY_SIDE ? `${WIDTH}px` : '';
        };
        place();
        window.addEventListener('resize', place);

        return () => {
            window.removeEventListener('resize', place);
            document.body.style.paddingRight = '';
        };
    }, [open]);

    useEffect(() => {
        if (!open) return;
        const onKey = (e: KeyboardEvent) => e.key === 'Escape' && setOpen(false);
        document.addEventListener('keydown', onKey);
        window.setTimeout(() => input.current?.focus(), 50);

        return () => document.removeEventListener('keydown', onKey);
    }, [open]);

    if (!info?.enabled) {
        return null;
    }

    const updateChange = (change: FileChange) => {
        setChanges((map) => ({ ...map, [change.id]: change }));
        setHistory((list) => (list ? list.map((c) => (c.id === change.id ? change : c)) : list));
    };

    const showHistory = () => {
        if (history) {
            setHistory(null);

            return;
        }
        setError('');
        getFileChanges(uuid)
            .then((list) => {
                setHistory(list);
                setChanges((map) => ({ ...map, ...Object.fromEntries(list.map((c) => [c.id, c])) }));
            })
            .catch((e) => setError(httpErrorToHuman(e)));
    };

    const send = (text: string) => {
        const question = text.trim();
        if (!question || busy) return;
        const next: ChatMessage[] = [...messages, { role: 'user', content: question }];
        setMessages(next);
        setHistory(null);
        setDraft('');
        setError('');
        setBusy(true);
        const console = readConsole().split('\n').slice(-CONSOLE_LINES).join('\n');
        askAssistant(
            uuid,
            next.map((m) => ({ role: m.role, content: m.content })),
            console,
            status
        )
            .then(({ answer, changes: prepared, remaining }) => {
                setChanges((map) => ({ ...map, ...Object.fromEntries(prepared.map((c) => [c.id, c])) }));
                setMessages((list) => [
                    ...list,
                    { role: 'assistant', content: answer, changes: prepared.map((c) => c.id) },
                ]);
                mutate({ enabled: true, remaining }, false);
            })
            .catch((e) => setError(httpErrorToHuman(e)))
            .then(() => setBusy(false));
    };

    return (
        <>
            <button
                type={'button'}
                onClick={() => setOpen(true)}
                className={classNames(
                    'inline-flex items-center gap-2 h-8 rounded-full border pl-2.5 pr-3 text-xs font-semibold transition-all duration-150 hover:-translate-y-px',
                    'bg-gradient-to-b from-white/[0.12] to-white/[0.04] border-white/15 text-gray-50 hover:from-white/[0.18] hover:to-white/[0.08] shadow-[inset_0_1px_0_rgba(255,255,255,0.18)]',
                    className
                )}
            >
                <span
                    className={'flex items-center justify-center w-5 h-5 rounded-full text-white'}
                    style={{ background: 'linear-gradient(135deg, #a855f7, #6366f1)' }}
                >
                    <FontAwesomeIcon icon={faMagic} className={'w-2.5 h-2.5'} />
                </span>
                <span>AI assistant</span>
            </button>

            {/* In the body of the page: a parent with an animation would hold a "fixed" panel inside it. Docked on
                the right edge of the window, over the whole height, without darkening the page. */}
            {open &&
                createPortal(
                    <aside
                        className={
                            'fixed top-0 right-0 bottom-0 z-50 flex flex-col w-full bg-gray-900 border-l border-gray-700 shadow-2xl'
                        }
                        style={{ maxWidth: window.innerWidth >= 640 ? WIDTH : undefined }}
                        role={'dialog'}
                        aria-label={'AI assistant'}
                    >
                        <header className={'flex items-center gap-3 px-4 py-3 border-b border-gray-700'}>
                            <span
                                className={'flex items-center justify-center w-8 h-8 rounded-full text-white'}
                                style={{ background: 'linear-gradient(135deg, #a855f7, #6366f1)' }}
                            >
                                <FontAwesomeIcon icon={faMagic} className={'w-3.5 h-3.5'} />
                            </span>
                            <div className={'min-w-0 flex-1'}>
                                <p className={'text-sm font-semibold text-gray-50'}>AI assistant</p>
                                <p className={'text-xs text-gray-400 truncate'}>
                                    It reads the console and the settings of this server.
                                </p>
                            </div>
                            <button
                                type={'button'}
                                onClick={showHistory}
                                title={'Changes of files'}
                                aria-label={'Changes of files'}
                                className={classNames(
                                    'flex items-center justify-center w-8 h-8 rounded-md hover:text-gray-100 hover:bg-gray-700',
                                    history ? 'text-gray-100 bg-gray-700' : 'text-gray-400'
                                )}
                            >
                                <FontAwesomeIcon icon={faHistory} className={'w-3.5 h-3.5'} />
                            </button>
                            {messages.length > 0 && (
                                <button
                                    type={'button'}
                                    onClick={() => {
                                        setHistory(null);
                                        setMessages([]);
                                        setError('');
                                    }}
                                    title={'New conversation'}
                                    aria-label={'New conversation'}
                                    className={
                                        'flex items-center justify-center w-8 h-8 rounded-md text-gray-400 hover:text-gray-100 hover:bg-gray-700'
                                    }
                                >
                                    <FontAwesomeIcon icon={faRedo} className={'w-3.5 h-3.5'} />
                                </button>
                            )}
                            <button
                                type={'button'}
                                onClick={() => setOpen(false)}
                                title={'Close'}
                                aria-label={'Close'}
                                className={
                                    'flex items-center justify-center w-8 h-8 rounded-md text-gray-400 hover:text-gray-100 hover:bg-gray-700'
                                }
                            >
                                <FontAwesomeIcon icon={faTimes} />
                            </button>
                        </header>

                        <div className={'flex-1 overflow-y-auto px-4 py-4 space-y-4'}>
                            {history && (
                                <div className={'space-y-3'}>
                                    <p className={'text-sm font-semibold text-gray-100'}>Changes of files</p>
                                    <p className={'text-xs text-gray-400'}>
                                        Every change prepared by the assistant on this server in the last 30 days. An
                                        applied change can be put back as before at any time.
                                    </p>
                                    {history.length === 0 ? (
                                        <p className={'text-sm text-gray-400'}>No change yet.</p>
                                    ) : (
                                        history.map((change) => (
                                            <AssistantChange
                                                key={change.id}
                                                uuid={uuid}
                                                change={changes[change.id] || change}
                                                onChange={updateChange}
                                            />
                                        ))
                                    )}
                                </div>
                            )}
                            {!history && messages.length === 0 && (
                                <div>
                                    <p className={'text-sm text-gray-300'}>
                                        Ask about a crash, an error of the console, a mod, a setting... The assistant
                                        knows the game of this server and reads its console to answer.
                                    </p>
                                    <div className={'mt-4 flex flex-col gap-2'}>
                                        {SUGGESTIONS.map((text) => (
                                            <button
                                                key={text}
                                                type={'button'}
                                                onClick={() => send(text)}
                                                className={
                                                    'text-left rounded-lg border border-gray-700 bg-gray-800 px-3 py-2 text-sm text-gray-200 hover:border-gray-500 hover:bg-gray-700 transition-colors duration-150'
                                                }
                                            >
                                                {text}
                                            </button>
                                        ))}
                                    </div>
                                </div>
                            )}
                            {!history &&
                                messages.map((message, index) =>
                                    message.role === 'user' ? (
                                        <div key={index} className={'flex justify-end'} data-no-translate>
                                            <p
                                                className={
                                                    'max-w-[85%] whitespace-pre-wrap break-words rounded-2xl rounded-br-sm px-3 py-2 text-sm text-white'
                                                }
                                                style={{ background: 'rgb(var(--pd-accent-600))' }}
                                            >
                                                {message.content}
                                            </p>
                                        </div>
                                    ) : (
                                        <div key={index} className={'space-y-2'}>
                                            <div
                                                className={
                                                    'rounded-2xl rounded-bl-sm border border-gray-700 bg-gray-800 px-3 py-2'
                                                }
                                                data-no-translate
                                            >
                                                <AssistantText text={message.content} />
                                            </div>
                                            {(message.changes || [])
                                                .filter((id) => changes[id])
                                                .map((id) => (
                                                    <AssistantChange
                                                        key={id}
                                                        uuid={uuid}
                                                        change={changes[id]}
                                                        onChange={updateChange}
                                                    />
                                                ))}
                                        </div>
                                    )
                                )}
                            {busy && !history && (
                                <p className={'flex items-center gap-2 text-sm text-gray-400'}>
                                    <FontAwesomeIcon icon={faSpinner} spin />
                                    <span>The assistant reads the console and thinks...</span>
                                </p>
                            )}
                            {error && <p className={'text-sm text-red-400'}>{error}</p>}
                            <div ref={bottom} />
                        </div>

                        <form
                            className={'border-t border-gray-700 p-3'}
                            onSubmit={(e) => {
                                e.preventDefault();
                                send(draft);
                            }}
                        >
                            <div className={'flex items-end gap-2'}>
                                <textarea
                                    ref={input}
                                    value={draft}
                                    onChange={(e) => setDraft(e.target.value)}
                                    onKeyDown={(e) => {
                                        if (e.key === 'Enter' && !e.shiftKey) {
                                            e.preventDefault();
                                            send(draft);
                                        }
                                    }}
                                    rows={2}
                                    maxLength={4000}
                                    placeholder={'Describe the problem...'}
                                    className={
                                        'flex-1 resize-none rounded-lg border border-gray-600 bg-gray-800 px-3 py-2 text-sm text-gray-100 placeholder-gray-500 outline-none focus:border-gray-400'
                                    }
                                />
                                <button
                                    type={'submit'}
                                    disabled={busy || !draft.trim()}
                                    title={'Send'}
                                    aria-label={'Send'}
                                    className={
                                        'flex items-center justify-center w-10 h-10 rounded-lg text-white disabled:opacity-40 transition-opacity duration-150'
                                    }
                                    style={{ background: 'rgb(var(--pd-accent-600))' }}
                                >
                                    <FontAwesomeIcon icon={busy ? faSpinner : faPaperPlane} spin={busy} />
                                </button>
                            </div>
                            <p className={'mt-2 text-xs text-gray-500'}>
                                <span>
                                    The end of the console is sent with each question (passwords and keys hidden).
                                </span>
                                {info.remaining !== null && (
                                    <>
                                        {' '}
                                        <span>Questions left today:</span> {info.remaining}
                                    </>
                                )}
                            </p>
                        </form>
                    </aside>,
                    document.body
                )}
        </>
    );
};
