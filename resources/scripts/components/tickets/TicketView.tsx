import React, { useCallback, useEffect, useRef, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import tw from 'twin.macro';
import classNames from 'classnames';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faArrowLeft, faDownload, faLock, faPaperPlane, faUnlock } from '@fortawesome/free-solid-svg-icons';
import PageContentBlock from '@/components/elements/PageContentBlock';
import Button from '@/components/elements/Button';
import Spinner from '@/components/elements/Spinner';
import { Textarea } from '@/components/elements/Input';
import { httpErrorToHuman } from '@/api/http';
import { PriorityPill, StatusPill } from '@/components/tickets/TicketPills';
import {
    closeTicket,
    downloadTranscript,
    getTicket,
    getTicketMessages,
    reopenTicket,
    sendTicketMessage,
    Ticket,
    TicketMessage,
} from '@/api/tickets';

// How often the conversation asks whether something new was written, while the page is on screen.
const POLL_MS = 2000;

const formatTime = (date: Date): string =>
    new Intl.DateTimeFormat(undefined, { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' }).format(
        date
    );

const Bubble = ({ message }: { message: TicketMessage }) => (
    <div
        className={classNames(
            'max-w-[85%] rounded-2xl border px-4 py-2.5',
            message.mine
                ? 'self-end rounded-br-md bg-gradient-to-br from-primary-500/30 to-primary-600/20 border-primary-500/40'
                : 'self-start rounded-bl-md bg-white/[0.04] border-white/10'
        )}
        style={{ animation: 'none' }}
    >
        <div css={tw`flex items-center gap-2 mb-1 text-xs text-neutral-400`}>
            <img src={message.avatar} alt={''} css={tw`w-5 h-5 rounded-full object-cover`} />
            <strong css={tw`text-neutral-100`}>{message.author}</strong>
            {message.staff && (
                <span css={tw`rounded-full bg-primary-500/25 px-2 text-2xs uppercase tracking-wider text-primary-200`}>
                    Staff
                </span>
            )}
            <time css={tw`ml-auto text-2xs`}>{formatTime(message.at)}</time>
        </div>
        <p css={tw`text-sm text-neutral-100 whitespace-pre-wrap break-words`}>{message.body}</p>
    </div>
);

export default () => {
    const id = Number(useParams<{ id: string }>().id);
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
    }, [messages]);

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
            .then((updated) => setTicket((t) => (t ? { ...t, status: updated.status, closedAt: updated.closedAt } : t)))
            .catch((e) => setError(httpErrorToHuman(e)));
    };

    if (missing) {
        return (
            <PageContentBlock title={'Support'}>
                <p css={tw`text-center text-neutral-400 py-10`}>This ticket does not exist.</p>
            </PageContentBlock>
        );
    }
    if (!ticket) {
        return (
            <PageContentBlock title={'Support'}>
                <Spinner size={'large'} centered />
            </PageContentBlock>
        );
    }

    const closed = ticket.status === 'closed';

    return (
        <PageContentBlock title={`Ticket #${ticket.id}`}>
            <Link
                to={'/tickets'}
                css={tw`inline-flex items-center gap-2 text-sm text-neutral-400 hover:text-neutral-100 mb-3`}
            >
                <FontAwesomeIcon icon={faArrowLeft} />
                All my tickets
            </Link>
            <div css={tw`mb-4 flex flex-wrap items-start justify-between gap-3`}>
                <div css={tw`min-w-0`}>
                    <h1 css={tw`text-xl font-semibold text-neutral-50 break-words`}>{ticket.subject}</h1>
                    <div css={tw`mt-2 flex flex-wrap items-center gap-2`}>
                        <StatusPill status={ticket.status} />
                        <PriorityPill priority={ticket.priority} />
                        {ticket.server && <span css={tw`text-xs text-neutral-400`}>{ticket.server.name}</span>}
                    </div>
                </div>
                <div css={tw`flex flex-wrap items-center gap-2`}>
                    <Button size={'xsmall'} isSecondary color={'grey'} onClick={() => downloadTranscript(id, 'txt')}>
                        <FontAwesomeIcon icon={faDownload} css={tw`mr-2`} />
                        Transcript
                    </Button>
                    <Button size={'xsmall'} isSecondary color={'grey'} onClick={() => downloadTranscript(id, 'html')}>
                        <FontAwesomeIcon icon={faDownload} css={tw`mr-2`} />
                        Printable
                    </Button>
                    <Button size={'xsmall'} isSecondary color={closed ? 'green' : 'red'} onClick={toggle}>
                        <FontAwesomeIcon icon={closed ? faUnlock : faLock} css={tw`mr-2`} />
                        {closed ? 'Reopen' : 'Close the ticket'}
                    </Button>
                </div>
            </div>

            <div css={tw`rounded-2xl border border-white/5 bg-neutral-800 shadow-card overflow-hidden`}>
                <div css={tw`flex items-center justify-between px-4 py-2.5 border-b border-white/5 bg-white/[0.03]`}>
                    <span css={tw`text-xs uppercase tracking-wide text-neutral-300`}>Conversation</span>
                    <span css={tw`flex items-center gap-2 text-xs text-neutral-400`}>
                        <span css={tw`w-2 h-2 rounded-full bg-green-400 animate-pulse`} />
                        Live
                    </span>
                </div>
                <div
                    ref={box}
                    onScroll={(e) => {
                        const el = e.currentTarget;
                        stick.current = el.scrollHeight - el.scrollTop - el.clientHeight < 80;
                    }}
                    css={tw`flex flex-col gap-3 px-4 py-4 overflow-y-auto`}
                    style={{ height: '28rem' }}
                >
                    {messages.map((m) => (
                        <Bubble key={m.id} message={m} />
                    ))}
                </div>
                {closed ? (
                    <p css={tw`px-4 py-4 text-sm text-neutral-400 border-t border-white/5 bg-black/20`}>
                        This ticket is closed. Reopen it to write in it.
                    </p>
                ) : (
                    <form onSubmit={send} css={tw`px-4 py-3 border-t border-white/5 bg-black/20`}>
                        <Textarea
                            rows={3}
                            value={text}
                            maxLength={5000}
                            placeholder={'Write your message...'}
                            onChange={(e: React.ChangeEvent<HTMLTextAreaElement>) => setText(e.currentTarget.value)}
                            onKeyDown={(e: React.KeyboardEvent<HTMLTextAreaElement>) => {
                                if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) {
                                    send();
                                }
                            }}
                        />
                        <div css={tw`mt-2 flex items-center justify-between gap-3`}>
                            <span css={tw`text-xs text-red-400`}>{error}</span>
                            <Button type={'submit'} size={'small'} disabled={busy || !text.trim()}>
                                <FontAwesomeIcon icon={faPaperPlane} css={tw`mr-2`} />
                                Send
                            </Button>
                        </div>
                    </form>
                )}
            </div>
        </PageContentBlock>
    );
};
