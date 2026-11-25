import React, { useEffect, useRef, useState } from 'react';
import useSWR from 'swr';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faMagic, faPaperPlane, faRedo, faSpinner, faTimes } from '@fortawesome/free-solid-svg-icons';
import classNames from 'classnames';
import { ServerContext } from '@/state/server';
import { askAssistant, AssistantMessage, getAssistant } from '@/api/server/assistant';
import { httpErrorToHuman } from '@/api/http';
import { readConsole } from '@/components/server/console/consoleText';
import AssistantText from '@/components/server/console/AssistantText';

// What is sent of the console: its last lines (the panel keeps the last 200 of them).
const CONSOLE_LINES = 300;

const SUGGESTIONS = [
    'Why does my server crash or not start?',
    'Explain the errors of the console and how to fix them.',
    'How can I make my server faster?',
    'How do I install a mod or a plugin on this server?',
];

const storageKey = (uuid: string) => `pd-assistant:${uuid}`;

const load = (uuid: string): AssistantMessage[] => {
    try {
        const saved = JSON.parse(window.sessionStorage.getItem(storageKey(uuid)) || '[]');

        return Array.isArray(saved) ? saved.filter((m) => m && typeof m.content === 'string').slice(-30) : [];
    } catch {
        return [];
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
    const [messages, setMessages] = useState<AssistantMessage[]>(() => load(uuid));
    const [draft, setDraft] = useState('');
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState('');
    const bottom = useRef<HTMLDivElement>(null);
    const input = useRef<HTMLTextAreaElement>(null);

    useEffect(() => {
        try {
            window.sessionStorage.setItem(storageKey(uuid), JSON.stringify(messages.slice(-30)));
        } catch {
            // Not kept: the conversation stays for this page only.
        }
    }, [messages]);

    useEffect(() => {
        bottom.current?.scrollIntoView({ block: 'end' });
    }, [messages, busy, open]);

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

    const send = (text: string) => {
        const question = text.trim();
        if (!question || busy) return;
        const next: AssistantMessage[] = [...messages, { role: 'user', content: question }];
        setMessages(next);
        setDraft('');
        setError('');
        setBusy(true);
        const console = readConsole().split('\n').slice(-CONSOLE_LINES).join('\n');
        askAssistant(uuid, next, console, status)
            .then(({ answer, remaining }) => {
                setMessages((list) => [...list, { role: 'assistant', content: answer }]);
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

            {open && (
                <div className={'fixed inset-0 z-50 flex justify-end'}>
                    <div className={'absolute inset-0 bg-black/50'} onClick={() => setOpen(false)} />
                    <aside
                        className={
                            'relative flex flex-col w-full sm:w-[460px] h-full bg-gray-900 border-l border-gray-700 shadow-2xl'
                        }
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
                            {messages.length > 0 && (
                                <button
                                    type={'button'}
                                    onClick={() => {
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
                            {messages.length === 0 && (
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
                            {messages.map((message, index) =>
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
                                    <div
                                        key={index}
                                        className={
                                            'rounded-2xl rounded-bl-sm border border-gray-700 bg-gray-800 px-3 py-2'
                                        }
                                        data-no-translate
                                    >
                                        <AssistantText text={message.content} />
                                    </div>
                                )
                            )}
                            {busy && (
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
                    </aside>
                </div>
            )}
        </>
    );
};
