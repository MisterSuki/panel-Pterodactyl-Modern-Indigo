import React, { useCallback, useEffect, useRef, useState } from 'react';
import { Link } from 'react-router-dom';
import useSWR, { mutate } from 'swr';
import classNames from 'classnames';
import { formatDistanceToNow } from 'date-fns';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import {
    faArrowLeft,
    faDownload,
    faExpandAlt,
    faLifeRing,
    faLock,
    faPaperPlane,
    faPlus,
    faTimes,
    faUnlock,
} from '@fortawesome/free-solid-svg-icons';
import Tooltip from '@/components/elements/tooltip/Tooltip';
import Select from '@/components/elements/Select';
import Input, { Textarea } from '@/components/elements/Input';
import Spinner from '@/components/elements/Spinner';
import { httpErrorToHuman } from '@/api/http';
import getServers from '@/api/getServers';
import { StatusPill } from '@/components/tickets/TicketPills';
import {
    closeTicket,
    createTicket,
    downloadTranscript,
    getTicket,
    getTicketMessages,
    getTickets,
    getUnreadTickets,
    reopenTicket,
    sendTicketMessage,
    Ticket,
    TicketCategory,
    TicketMessage,
} from '@/api/tickets';

// How often the conversation asks whether something new was written, while it is open.
const POLL_MS = 2000;

type View = { name: 'list' } | { name: 'new' } | { name: 'ticket'; id: number };

const categories: { value: TicketCategory; label: string }[] = [
    { value: 'general', label: 'General' },
    { value: 'technical', label: 'Technical' },
    { value: 'billing', label: 'Billing' },
    { value: 'other', label: 'Other' },
];

const formatTime = (date: Date): string =>
    new Intl.DateTimeFormat(undefined, { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' }).format(
        date
    );

const IconButton = ({
    label,
    onClick,
    children,
}: {
    label: string;
    onClick: () => void;
    children: React.ReactNode;
}) => (
    <button
        type={'button'}
        aria-label={label}
        title={label}
        onClick={onClick}
        className={
            'flex items-center justify-center h-8 w-8 rounded-lg text-neutral-300 hover:text-neutral-50 hover:bg-white/10 transition-colors duration-150'
        }
    >
        {children}
    </button>
);

const Header = ({
    title,
    onBack,
    onClose,
    children,
}: {
    title: React.ReactNode;
    onBack?: () => void;
    onClose: () => void;
    children?: React.ReactNode;
}) => (
    <div className={'flex items-center gap-1 px-3 py-2.5 border-b border-white/10 bg-white/[0.04]'}>
        {onBack && (
            <IconButton label={'Back'} onClick={onBack}>
                <FontAwesomeIcon icon={faArrowLeft} />
            </IconButton>
        )}
        <div className={'flex-1 min-w-0 px-1 text-sm font-semibold text-neutral-50 truncate'}>{title}</div>
        {children}
        <IconButton label={'Close'} onClick={onClose}>
            <FontAwesomeIcon icon={faTimes} />
        </IconButton>
    </div>
);

const List = ({ onOpen, onNew, onClose }: { onOpen: (id: number) => void; onNew: () => void; onClose: () => void }) => {
    const { data: tickets } = useSWR<Ticket[]>('tickets', getTickets, { refreshInterval: 10000 });

    return (
        <>
            <Header
                title={
                    <span className={'flex items-center gap-2'}>
                        <FontAwesomeIcon icon={faLifeRing} className={'text-primary-400'} />
                        Support
                    </span>
                }
                onClose={onClose}
            >
                <Tooltip placement={'bottom'} content={'Open the full page'}>
                    <Link
                        to={'/tickets'}
                        onClick={onClose}
                        className={
                            'flex items-center justify-center h-8 w-8 rounded-lg text-neutral-300 hover:text-neutral-50 hover:bg-white/10 transition-colors duration-150'
                        }
                    >
                        <FontAwesomeIcon icon={faExpandAlt} />
                    </Link>
                </Tooltip>
            </Header>
            <div className={'flex-1 overflow-y-auto p-3'}>
                {!tickets ? (
                    <Spinner size={'large'} centered />
                ) : tickets.length === 0 ? (
                    <p className={'text-center text-sm text-neutral-400 py-10 px-4'}>
                        You have no ticket yet. Ask the team for help and they will answer here.
                    </p>
                ) : (
                    <ul className={'flex flex-col gap-2'}>
                        {tickets.map((ticket) => (
                            <li key={ticket.id}>
                                <button
                                    type={'button'}
                                    onClick={() => onOpen(ticket.id)}
                                    className={
                                        'w-full text-left rounded-xl border border-white/5 bg-white/[0.03] hover:bg-white/[0.07] px-3 py-2.5 transition-colors duration-150'
                                    }
                                >
                                    <div className={'flex items-center gap-2'}>
                                        {ticket.unread && (
                                            <span className={'w-2 h-2 rounded-full bg-primary-400 flex-shrink-0'} />
                                        )}
                                        <span className={'flex-1 min-w-0 text-sm font-medium text-neutral-50 truncate'}>
                                            {ticket.subject}
                                        </span>
                                        <StatusPill status={ticket.status} />
                                    </div>
                                    <p className={'mt-1 text-xs text-neutral-400'}>
                                        #{ticket.id} &middot; {formatDistanceToNow(ticket.lastMessageAt)}
                                    </p>
                                </button>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
            <div className={'p-3 border-t border-white/10 bg-black/20'}>
                <button
                    type={'button'}
                    onClick={onNew}
                    className={
                        'w-full flex items-center justify-center gap-2 rounded-lg bg-primary-500 hover:bg-primary-400 px-4 py-2 text-sm font-semibold text-white transition-colors duration-150'
                    }
                >
                    <FontAwesomeIcon icon={faPlus} />
                    New ticket
                </button>
            </div>
        </>
    );
};

const NewTicket = ({
    onBack,
    onClose,
    onDone,
}: {
    onBack: () => void;
    onClose: () => void;
    onDone: (ticket: Ticket) => void;
}) => {
    const [subject, setSubject] = useState('');
    const [category, setCategory] = useState<TicketCategory>('general');
    const [serverId, setServerId] = useState<number | null>(null);
    const [message, setMessage] = useState('');
    const [error, setError] = useState('');
    const [busy, setBusy] = useState(false);
    const { data: servers } = useSWR('tickets:servers', () => getServers({ page: 1 }), { revalidateOnFocus: false });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        setBusy(true);
        setError('');
        createTicket({ subject, category, priority: 'normal', serverId, message })
            .then((ticket) => {
                mutate('tickets');
                onDone(ticket);
            })
            .catch((e) => {
                setError(httpErrorToHuman(e));
                setBusy(false);
            });
    };

    return (
        <>
            <Header title={'New ticket'} onBack={onBack} onClose={onClose} />
            <form onSubmit={submit} className={'flex-1 flex flex-col gap-3 overflow-y-auto p-3'}>
                <Input
                    value={subject}
                    maxLength={120}
                    required
                    placeholder={'What is it about?'}
                    onChange={(e: React.ChangeEvent<HTMLInputElement>) => setSubject(e.currentTarget.value)}
                />
                <div className={'grid grid-cols-2 gap-2'}>
                    <Select
                        value={category}
                        onChange={(e: React.ChangeEvent<HTMLSelectElement>) =>
                            setCategory(e.currentTarget.value as TicketCategory)
                        }
                    >
                        {categories.map((c) => (
                            <option key={c.value} value={c.value}>
                                {c.label}
                            </option>
                        ))}
                    </Select>
                    <Select
                        value={serverId ?? ''}
                        onChange={(e: React.ChangeEvent<HTMLSelectElement>) =>
                            setServerId(e.currentTarget.value ? Number(e.currentTarget.value) : null)
                        }
                    >
                        <option value={''}>No server</option>
                        {servers?.items.map((s) => (
                            <option key={s.uuid} value={String(s.internalId)}>
                                {s.name}
                            </option>
                        ))}
                    </Select>
                </div>
                <Textarea
                    rows={7}
                    value={message}
                    maxLength={5000}
                    required
                    placeholder={'Explain the problem as precisely as you can.'}
                    onChange={(e: React.ChangeEvent<HTMLTextAreaElement>) => setMessage(e.currentTarget.value)}
                />
                {error && <p className={'text-sm text-red-400'}>{error}</p>}
                <button
                    type={'submit'}
                    disabled={busy || subject.trim().length < 3 || !message.trim()}
                    className={
                        'mt-auto w-full rounded-lg bg-primary-500 hover:bg-primary-400 disabled:opacity-50 disabled:cursor-not-allowed px-4 py-2 text-sm font-semibold text-white transition-colors duration-150'
                    }
                >
                    Open the ticket
                </button>
            </form>
        </>
    );
};

const Thread = ({ id, onBack, onClose }: { id: number; onBack: () => void; onClose: () => void }) => {
    const [ticket, setTicket] = useState<Ticket | null>(null);
    const [messages, setMessages] = useState<TicketMessage[]>([]);
    const [missing, setMissing] = useState(false);
    const [text, setText] = useState('');
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState('');
    const last = useRef(0);
    const box = useRef<HTMLDivElement>(null);
    const stick = useRef(true);

    // Adds what is new, once each: the answer to what was sent and the next question can both bring the same message.
    const append = useCallback((incoming: TicketMessage[]) => {
        setMessages((current) => {
            const fresh = incoming.filter((m) => m.id > last.current);
            if (fresh.length === 0) {
                return current;
            }
            last.current = fresh[fresh.length - 1].id;

            return current.concat(fresh);
        });
    }, []);

    useEffect(() => {
        let alive = true;
        last.current = 0;
        setMessages([]);
        setTicket(null);
        getTicket(id)
            .then((result) => {
                if (alive) {
                    setTicket(result.ticket);
                    append(result.messages);
                    // Opening it reads it: the number on the bubble goes down.
                    mutate('tickets:unread');
                    mutate('tickets');
                }
            })
            .catch(() => alive && setMissing(true));

        const timer = window.setInterval(() => {
            if (document.hidden) {
                return;
            }
            getTicketMessages(id, last.current)
                .then((result) => {
                    if (!alive) {
                        return;
                    }
                    append(result.messages);
                    setTicket((t) => (t && t.status !== result.status ? { ...t, status: result.status } : t));
                })
                .catch(() => undefined);
        }, POLL_MS);

        return () => {
            alive = false;
            window.clearInterval(timer);
        };
    }, [id]);

    // The conversation follows the newest message, unless the person scrolled up to read.
    useEffect(() => {
        if (box.current && stick.current) {
            box.current.scrollTop = box.current.scrollHeight;
        }
    }, [messages, ticket]);

    const send = (e?: React.FormEvent) => {
        e?.preventDefault();
        const message = text.trim();
        if (!message || busy) {
            return;
        }
        setBusy(true);
        setError('');
        sendTicketMessage(id, message)
            .then((sent) => {
                setText('');
                stick.current = true;
                append([sent]);
                setTicket((t) => (t ? { ...t, status: 'open' } : t));
            })
            .catch((e) => setError(httpErrorToHuman(e)))
            .then(() => setBusy(false));
    };

    const toggle = () => {
        if (!ticket) {
            return;
        }
        (ticket.status === 'closed' ? reopenTicket(id) : closeTicket(id))
            .then((updated) => {
                setTicket((t) => (t ? { ...t, status: updated.status, closedAt: updated.closedAt } : t));
                mutate('tickets');
            })
            .catch((e) => setError(httpErrorToHuman(e)));
    };

    if (missing) {
        return (
            <>
                <Header title={'Support'} onBack={onBack} onClose={onClose} />
                <p className={'text-center text-sm text-neutral-400 py-10'}>This ticket does not exist.</p>
            </>
        );
    }
    if (!ticket) {
        return (
            <>
                <Header title={'Support'} onBack={onBack} onClose={onClose} />
                <div className={'flex-1'}>
                    <Spinner size={'large'} centered />
                </div>
            </>
        );
    }

    const closed = ticket.status === 'closed';

    return (
        <>
            <Header
                title={
                    <span className={'flex flex-col leading-tight'}>
                        <span className={'truncate'}>{ticket.subject}</span>
                        <span className={'text-2xs font-normal text-neutral-400'}>#{ticket.id}</span>
                    </span>
                }
                onBack={onBack}
                onClose={onClose}
            >
                <IconButton label={'Transcript'} onClick={() => downloadTranscript(id, 'txt')}>
                    <FontAwesomeIcon icon={faDownload} />
                </IconButton>
                <IconButton label={closed ? 'Reopen' : 'Close the ticket'} onClick={toggle}>
                    <FontAwesomeIcon icon={closed ? faUnlock : faLock} />
                </IconButton>
            </Header>
            <div className={'flex items-center justify-between px-3 py-1.5 border-b border-white/5 bg-black/10'}>
                <StatusPill status={ticket.status} />
                <span className={'flex items-center gap-1.5 text-2xs text-neutral-400'}>
                    <span className={'w-1.5 h-1.5 rounded-full bg-green-400 animate-pulse'} />
                    Live
                </span>
            </div>
            <div
                ref={box}
                onScroll={(e) => {
                    const el = e.currentTarget;
                    stick.current = el.scrollHeight - el.scrollTop - el.clientHeight < 80;
                }}
                className={'flex-1 flex flex-col gap-2.5 overflow-y-auto px-3 py-3'}
            >
                {messages.map((m) => (
                    <div
                        key={m.id}
                        className={classNames(
                            'max-w-[88%] rounded-2xl border px-3 py-2',
                            m.mine
                                ? 'self-end rounded-br-md bg-gradient-to-br from-primary-500/30 to-primary-600/20 border-primary-500/40'
                                : 'self-start rounded-bl-md bg-white/[0.05] border-white/10'
                        )}
                    >
                        <div className={'flex items-center gap-2 mb-0.5 text-2xs text-neutral-400'}>
                            <img src={m.avatar} alt={''} className={'w-4 h-4 rounded-full object-cover'} />
                            <strong className={'text-neutral-100'}>{m.author}</strong>
                            {m.staff && (
                                <span
                                    className={
                                        'rounded-full bg-primary-500/25 px-1.5 uppercase tracking-wider text-primary-200'
                                    }
                                >
                                    Staff
                                </span>
                            )}
                            <time className={'ml-auto'}>{formatTime(m.at)}</time>
                        </div>
                        <p className={'text-sm text-neutral-100 whitespace-pre-wrap break-words'}>{m.body}</p>
                    </div>
                ))}
            </div>
            {closed ? (
                <p className={'px-3 py-3 text-xs text-neutral-400 border-t border-white/10 bg-black/20'}>
                    This ticket is closed. Reopen it to write in it.
                </p>
            ) : (
                <form onSubmit={send} className={'p-2.5 border-t border-white/10 bg-black/20'}>
                    {error && <p className={'mb-1.5 text-xs text-red-400'}>{error}</p>}
                    <div className={'flex items-end gap-2'}>
                        <Textarea
                            rows={2}
                            value={text}
                            maxLength={5000}
                            placeholder={'Write your message...'}
                            onChange={(e: React.ChangeEvent<HTMLTextAreaElement>) => setText(e.currentTarget.value)}
                            onKeyDown={(e: React.KeyboardEvent<HTMLTextAreaElement>) => {
                                if (e.key === 'Enter' && !e.shiftKey) {
                                    e.preventDefault();
                                    send();
                                }
                            }}
                        />
                        <button
                            type={'submit'}
                            disabled={busy || !text.trim()}
                            aria-label={'Send'}
                            className={
                                'flex-shrink-0 flex items-center justify-center h-10 w-10 rounded-lg bg-primary-500 hover:bg-primary-400 disabled:opacity-50 disabled:cursor-not-allowed text-white transition-colors duration-150'
                            }
                        >
                            <FontAwesomeIcon icon={faPaperPlane} />
                        </button>
                    </div>
                </form>
            )}
        </>
    );
};

// The way to the support: a round bubble in the corner of every page. It opens a small window where the tickets are
// read and written without leaving the page, and it carries the number of answers that were not read yet.
export default () => {
    const [open, setOpen] = useState(false);
    const [view, setView] = useState<View>({ name: 'list' });
    // The answers of the staff that were not read yet, looked at again every half minute.
    const { data: unread } = useSWR('tickets:unread', getUnreadTickets, {
        refreshInterval: 30000,
        revalidateOnFocus: true,
        shouldRetryOnError: false,
    });

    useEffect(() => {
        if (!open) {
            return;
        }
        const onKey = (e: KeyboardEvent) => e.key === 'Escape' && setOpen(false);
        window.addEventListener('keydown', onKey);

        return () => window.removeEventListener('keydown', onKey);
    }, [open]);

    const close = () => setOpen(false);
    const list = () => setView({ name: 'list' });

    return (
        <div className={'fixed bottom-5 right-5 z-50 flex flex-col items-end gap-3'}>
            {open && (
                <div
                    role={'dialog'}
                    aria-label={'Support'}
                    className={
                        'flex flex-col w-[24rem] max-w-[calc(100vw-2.5rem)] h-[34rem] max-h-[calc(100vh-7.5rem)] rounded-2xl border border-white/10 bg-neutral-800 shadow-card overflow-hidden'
                    }
                >
                    {view.name === 'list' && (
                        <List
                            onOpen={(id) => setView({ name: 'ticket', id })}
                            onNew={() => setView({ name: 'new' })}
                            onClose={close}
                        />
                    )}
                    {view.name === 'new' && (
                        <NewTicket
                            onBack={list}
                            onClose={close}
                            onDone={(ticket) => setView({ name: 'ticket', id: ticket.id })}
                        />
                    )}
                    {view.name === 'ticket' && <Thread id={view.id} onBack={list} onClose={close} />}
                </div>
            )}
            <Tooltip placement={'left'} content={open ? 'Close' : 'Support'} disabled={open}>
                <button
                    type={'button'}
                    aria-label={'Support'}
                    aria-expanded={open}
                    onClick={() => setOpen(!open)}
                    className={
                        'relative flex items-center justify-center h-14 w-14 rounded-full text-white text-xl bg-gradient-to-br from-primary-400 to-primary-600 shadow-glow border border-white/20 transition-transform duration-150 hover:scale-110 active:scale-95'
                    }
                >
                    {!!unread && !open && (
                        <span className={'absolute inset-0 rounded-full bg-primary-400/40 animate-ping'} />
                    )}
                    <FontAwesomeIcon icon={open ? faTimes : faLifeRing} className={'relative'} />
                    {!!unread && (
                        <span
                            className={
                                'absolute -top-1 -right-1 min-w-[1.25rem] h-5 px-1 rounded-full bg-red-500 border-2 border-neutral-900 text-white text-2xs leading-4 text-center font-semibold'
                            }
                        >
                            {unread}
                        </span>
                    )}
                </button>
            </Tooltip>
        </div>
    );
};
