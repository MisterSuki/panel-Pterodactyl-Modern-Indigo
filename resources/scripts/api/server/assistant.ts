import http from '@/api/http';

export interface AssistantMessage {
    role: 'user' | 'assistant';
    content: string;
}

export interface AssistantInfo {
    enabled: boolean;
    // Questions left today (null: no limit).
    remaining: number | null;
}

// A change of a file prepared by the assistant: nothing is written until it is applied, and it can be put back.
export interface FileChange {
    id: number;
    path: string;
    summary: string;
    state: 'proposed' | 'applied' | 'reverted' | 'dismissed';
    // The change creates the file.
    created: boolean;
    hunks: { removed: string; added: string }[];
    createdAt: Date | null;
    appliedAt: Date | null;
    revertedAt: Date | null;
}

/* eslint-disable camelcase */
const mapChange = (data: any): FileChange => ({
    id: data.id,
    path: data.path,
    summary: data.summary || '',
    state: data.state,
    created: !!data.created,
    hunks: data.hunks || [],
    createdAt: data.created_at ? new Date(data.created_at) : null,
    appliedAt: data.applied_at ? new Date(data.applied_at) : null,
    revertedAt: data.reverted_at ? new Date(data.reverted_at) : null,
});
/* eslint-enable camelcase */

export const getAssistant = (uuid: string): Promise<AssistantInfo> =>
    http.get(`/api/client/servers/${uuid}/assistant`).then(({ data }) => ({
        enabled: !!data.enabled,
        remaining: data.remaining ?? null,
    }));

export const askAssistant = (
    uuid: string,
    messages: AssistantMessage[],
    console: string,
    status: string | null
): Promise<{ answer: string; changes: FileChange[]; remaining: number | null }> =>
    http
        .post(`/api/client/servers/${uuid}/assistant`, { messages, console, status }, { timeout: 180000 })
        .then(({ data }) => ({
            answer: data.answer,
            changes: (data.changes || []).map(mapChange),
            remaining: data.remaining ?? null,
        }));

export const getFileChanges = (uuid: string): Promise<FileChange[]> =>
    http.get(`/api/client/servers/${uuid}/assistant/changes`).then(({ data }) => (data.data || []).map(mapChange));

export const applyFileChange = (uuid: string, id: number): Promise<FileChange> =>
    http.post(`/api/client/servers/${uuid}/assistant/changes/${id}/apply`).then(({ data }) => mapChange(data.data));

// force: put it back even if the file was changed again since (the panel answers 409 first).
export const revertFileChange = (uuid: string, id: number, force = false): Promise<FileChange> =>
    http
        .post(`/api/client/servers/${uuid}/assistant/changes/${id}/revert`, { force })
        .then(({ data }) => mapChange(data.data));

export const dismissFileChange = (uuid: string, id: number): Promise<FileChange> =>
    http.post(`/api/client/servers/${uuid}/assistant/changes/${id}/dismiss`).then(({ data }) => mapChange(data.data));
