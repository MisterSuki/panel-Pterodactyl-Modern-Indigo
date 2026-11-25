import http from '@/api/http';

// A link that connects the Claude app to the panel (a connector).
export interface ClaudeLink {
    id: number;
    name: string;
    readOnly: boolean;
    lastUsedAt: Date | null;
    createdAt: Date | null;
}

/* eslint-disable camelcase */
const map = (data: any): ClaudeLink => ({
    id: data.id,
    name: data.name,
    readOnly: !!data.read_only,
    lastUsedAt: data.last_used_at ? new Date(data.last_used_at) : null,
    createdAt: data.created_at ? new Date(data.created_at) : null,
});

export const getClaudeLinks = (): Promise<ClaudeLink[]> =>
    http.get('/api/client/account/claude').then(({ data }) => (data.data || []).map(map));

// The address of the link is only given here, once.
export const createClaudeLink = (name: string, readOnly: boolean): Promise<{ link: ClaudeLink; url: string }> =>
    http
        .post('/api/client/account/claude', { name, read_only: readOnly })
        .then(({ data }) => ({ link: map(data.data), url: data.data.url }));
/* eslint-enable camelcase */

export const deleteClaudeLink = (id: number): Promise<void> =>
    http.delete(`/api/client/account/claude/${id}`).then(() => undefined);
