import http from '@/api/http';

export type TicketStatus = 'open' | 'answered' | 'closed';
export type TicketPriority = 'low' | 'normal' | 'high' | 'urgent';
export type TicketCategory = 'general' | 'technical' | 'billing' | 'other';

export interface Ticket {
    id: number;
    subject: string;
    category: TicketCategory;
    priority: TicketPriority;
    status: TicketStatus;
    server: { id: number; name: string | null } | null;
    // An answer of the staff that has not been read yet.
    unread: boolean;
    createdAt: Date;
    lastMessageAt: Date;
    closedAt: Date | null;
}

export interface TicketMessage {
    id: number;
    body: string;
    staff: boolean;
    author: string;
    // Where the picture of the author is.
    avatar: string;
    // Written by the person who is looking.
    mine: boolean;
    at: Date;
}

const toTicket = (a: any): Ticket => ({
    id: a.id,
    subject: a.subject,
    category: a.category,
    priority: a.priority,
    status: a.status,
    server: a.server,
    unread: !!a.unread,
    createdAt: new Date(a.created_at),
    lastMessageAt: new Date(a.last_message_at),
    closedAt: a.closed_at ? new Date(a.closed_at) : null,
});

const toMessage = (m: any): TicketMessage => ({
    id: m.id,
    body: m.body,
    staff: !!m.staff,
    author: m.author,
    avatar: m.avatar || '/assets/svgs/pterodactyl.svg',
    mine: !!m.mine,
    at: new Date(m.at),
});

export const getTickets = async (): Promise<Ticket[]> => {
    const { data } = await http.get('/api/client/tickets');

    return (data.data || []).map(toTicket);
};

export const getTicket = async (id: number): Promise<{ ticket: Ticket; messages: TicketMessage[] }> => {
    const { data } = await http.get(`/api/client/tickets/${id}`);

    return { ticket: toTicket(data.attributes), messages: (data.messages || []).map(toMessage) };
};

// What is new after the message with the given id, and the state of the ticket.
export const getTicketMessages = async (
    id: number,
    after: number
): Promise<{ status: TicketStatus; messages: TicketMessage[] }> => {
    const { data } = await http.get(`/api/client/tickets/${id}/messages`, { params: { after } });

    return { status: data.status, messages: (data.data || []).map(toMessage) };
};

export interface NewTicket {
    subject: string;
    category: TicketCategory;
    priority: TicketPriority;
    serverId: number | null;
    message: string;
}

export const createTicket = async (values: NewTicket): Promise<Ticket> => {
    const { data } = await http.post('/api/client/tickets', {
        subject: values.subject,
        category: values.category,
        priority: values.priority,
        server_id: values.serverId,
        message: values.message,
    });

    return toTicket(data.attributes);
};

export const sendTicketMessage = async (id: number, message: string): Promise<TicketMessage> => {
    const { data } = await http.post(`/api/client/tickets/${id}/messages`, { message });

    return toMessage(data.attributes);
};

export const closeTicket = async (id: number): Promise<Ticket> =>
    toTicket((await http.post(`/api/client/tickets/${id}/close`)).data.attributes);

export const reopenTicket = async (id: number): Promise<Ticket> =>
    toTicket((await http.post(`/api/client/tickets/${id}/reopen`)).data.attributes);

export const getUnreadTickets = async (): Promise<number> => {
    const { data } = await http.get('/api/client/tickets/unread');

    return data.count || 0;
};

// The transcript is a file: it is fetched with the session that is signed in and handed to the browser to save.
export const downloadTranscript = async (id: number, format: 'txt' | 'html'): Promise<void> => {
    const { data } = await http.get(`/api/client/tickets/${id}/transcript`, {
        params: format === 'html' ? { format } : {},
        responseType: 'blob',
    });

    const url = window.URL.createObjectURL(data as Blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `ticket-${id}-transcript.${format}`;
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.setTimeout(() => window.URL.revokeObjectURL(url), 1000);
};
