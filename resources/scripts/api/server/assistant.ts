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
): Promise<{ answer: string; remaining: number | null }> =>
    http
        .post(`/api/client/servers/${uuid}/assistant`, { messages, console, status }, { timeout: 120000 })
        .then(({ data }) => ({ answer: data.answer, remaining: data.remaining ?? null }));
