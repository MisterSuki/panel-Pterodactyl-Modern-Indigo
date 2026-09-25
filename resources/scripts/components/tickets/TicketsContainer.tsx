import React, { useState } from 'react';
import { Link, useHistory } from 'react-router-dom';
import useSWR from 'swr';
import tw from 'twin.macro';
import { formatDistanceToNow } from 'date-fns';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faLifeRing, faPlus, faServer } from '@fortawesome/free-solid-svg-icons';
import PageContentBlock from '@/components/elements/PageContentBlock';
import Button from '@/components/elements/Button';
import Select from '@/components/elements/Select';
import Input, { Textarea } from '@/components/elements/Input';
import Label from '@/components/elements/Label';
import Spinner from '@/components/elements/Spinner';
import { httpErrorToHuman } from '@/api/http';
import getServers from '@/api/getServers';
import { StatusPill } from '@/components/tickets/TicketPills';
import { createTicket, getTickets, NewTicket, Ticket, TicketCategory, TicketPriority } from '@/api/tickets';

const categories: { value: TicketCategory; label: string }[] = [
    { value: 'general', label: 'General' },
    { value: 'technical', label: 'Technical' },
    { value: 'billing', label: 'Billing' },
    { value: 'other', label: 'Other' },
];

const priorities: { value: TicketPriority; label: string }[] = [
    { value: 'low', label: 'Low' },
    { value: 'normal', label: 'Normal' },
    { value: 'high', label: 'High' },
];

const NewTicketForm = ({ onDone }: { onDone: (ticket: Ticket) => void }) => {
    const [values, setValues] = useState<NewTicket>({
        subject: '',
        category: 'general',
        priority: 'normal',
        serverId: null,
        message: '',
    });
    const [error, setError] = useState('');
    const [busy, setBusy] = useState(false);
    const { data: servers } = useSWR('tickets:servers', () => getServers({ page: 1 }), { revalidateOnFocus: false });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        setBusy(true);
        setError('');
        createTicket(values)
            .then(onDone)
            .catch((e) => {
                setError(httpErrorToHuman(e));
                setBusy(false);
            });
    };

    return (
        <form onSubmit={submit} css={tw`rounded-2xl border border-white/5 bg-neutral-800 shadow-card p-5 mb-6`}>
            <h2 css={tw`text-lg font-semibold text-neutral-50 mb-4`}>New ticket</h2>
            <div css={tw`grid grid-cols-1 sm:grid-cols-3 gap-4`}>
                <div css={tw`sm:col-span-3`}>
                    <Label htmlFor={'ticket-subject'}>Subject</Label>
                    <Input
                        id={'ticket-subject'}
                        value={values.subject}
                        maxLength={120}
                        required
                        placeholder={'What is it about?'}
                        onChange={(e: React.ChangeEvent<HTMLInputElement>) =>
                            setValues({ ...values, subject: e.currentTarget.value })
                        }
                    />
                </div>
                <div>
                    <Label htmlFor={'ticket-category'}>Category</Label>
                    <Select
                        id={'ticket-category'}
                        value={values.category}
                        onChange={(e: React.ChangeEvent<HTMLSelectElement>) =>
                            setValues({ ...values, category: e.currentTarget.value as TicketCategory })
                        }
                    >
                        {categories.map((c) => (
                            <option key={c.value} value={c.value}>
                                {c.label}
                            </option>
                        ))}
                    </Select>
                </div>
                <div>
                    <Label htmlFor={'ticket-priority'}>Priority</Label>
                    <Select
                        id={'ticket-priority'}
                        value={values.priority}
                        onChange={(e: React.ChangeEvent<HTMLSelectElement>) =>
                            setValues({ ...values, priority: e.currentTarget.value as TicketPriority })
                        }
                    >
                        {priorities.map((p) => (
                            <option key={p.value} value={p.value}>
                                {p.label}
                            </option>
                        ))}
                    </Select>
                </div>
                <div>
                    <Label htmlFor={'ticket-server'}>Server</Label>
                    <Select
                        id={'ticket-server'}
                        value={values.serverId ?? ''}
                        onChange={(e: React.ChangeEvent<HTMLSelectElement>) =>
                            setValues({
                                ...values,
                                serverId: e.currentTarget.value ? Number(e.currentTarget.value) : null,
                            })
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
                <div css={tw`sm:col-span-3`}>
                    <Label htmlFor={'ticket-message'}>Message</Label>
                    <Textarea
                        id={'ticket-message'}
                        rows={6}
                        value={values.message}
                        maxLength={5000}
                        required
                        placeholder={'Explain the problem as precisely as you can.'}
                        onChange={(e: React.ChangeEvent<HTMLTextAreaElement>) =>
                            setValues({ ...values, message: e.currentTarget.value })
                        }
                    />
                </div>
            </div>
            {error && <p css={tw`mt-3 text-sm text-red-400`}>{error}</p>}
            <div css={tw`mt-4 flex justify-end`}>
                <Button type={'submit'} disabled={busy || values.subject.trim().length < 3 || !values.message.trim()}>
                    Open the ticket
                </Button>
            </div>
        </form>
    );
};

export default () => {
    const history = useHistory();
    const [creating, setCreating] = useState(false);
    const { data: tickets, mutate } = useSWR<Ticket[]>('tickets', getTickets, {
        refreshInterval: 10000,
        revalidateOnFocus: true,
    });

    return (
        <PageContentBlock title={'Support'}>
            <div css={tw`mb-5 flex flex-wrap items-end justify-between gap-3`}>
                <div>
                    <h1 css={tw`text-2xl font-semibold text-neutral-50 flex items-center gap-3`}>
                        <FontAwesomeIcon icon={faLifeRing} css={tw`text-primary-400`} />
                        Support
                    </h1>
                    <p css={tw`text-sm text-neutral-400 mt-1`}>
                        Ask the team for help. You are told here as soon as they answer.
                    </p>
                </div>
                {!creating && (
                    <Button onClick={() => setCreating(true)}>
                        <FontAwesomeIcon icon={faPlus} css={tw`mr-2`} />
                        New ticket
                    </Button>
                )}
            </div>
            {creating && (
                <NewTicketForm
                    onDone={(ticket) => {
                        mutate();
                        history.push(`/tickets/${ticket.id}`);
                    }}
                />
            )}
            {!tickets ? (
                <Spinner size={'large'} centered />
            ) : tickets.length === 0 ? (
                <p css={tw`text-center text-sm text-neutral-400 py-10`}>You have no ticket yet.</p>
            ) : (
                tickets.map((ticket) => (
                    <Link
                        key={ticket.id}
                        to={`/tickets/${ticket.id}`}
                        css={tw`flex items-center gap-4 mb-2 rounded-xl border border-white/5 bg-neutral-800 px-4 py-3 no-underline shadow-card transition-all duration-150 hover:border-primary-500/40`}
                    >
                        <span css={tw`text-xs text-neutral-500 tabular-nums w-10 flex-shrink-0`}>#{ticket.id}</span>
                        <div css={tw`min-w-0 flex-1`}>
                            <p css={tw`text-neutral-50 font-medium truncate flex items-center gap-2`}>
                                {ticket.unread && <span css={tw`w-2 h-2 rounded-full bg-primary-400 flex-shrink-0`} />}
                                {ticket.subject}
                            </p>
                            <p css={tw`text-xs text-neutral-400 mt-0.5 flex items-center gap-2`}>
                                {ticket.server && (
                                    <span>
                                        <FontAwesomeIcon icon={faServer} css={tw`mr-1`} />
                                        {ticket.server.name}
                                    </span>
                                )}
                                <span>{formatDistanceToNow(ticket.lastMessageAt, { addSuffix: true })}</span>
                            </p>
                        </div>
                        <StatusPill status={ticket.status} />
                    </Link>
                ))
            )}
        </PageContentBlock>
    );
};
