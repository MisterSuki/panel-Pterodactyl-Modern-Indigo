import http from '@/api/http';

export type RecordStatus = 'ok' | 'wrong' | 'missing' | 'pending' | 'unknown' | null;

export interface MailRecord {
    type: 'MX' | 'TXT';
    name: string;
    value: string;
    priority: number | null;
    status: RecordStatus;
}

export interface MailBox {
    id: number;
    address: string;
    quotaMb: number;
    usedBytes: number | null;
    createdAt: Date | null;
}

export interface MailPage {
    available: boolean;
    online: boolean;
    hostname: string;
    webmail: string | null;
    max: number;
    quotaMb: number;
    candidate: string | null;
    domain: { hostname: string; dkimReady: boolean; records: MailRecord[] } | null;
    boxes: MailBox[];
}

/* eslint-disable camelcase */
const map = (data: any): MailPage => ({
    available: !!data.available,
    online: !!data.online,
    hostname: data.hostname || '',
    webmail: data.webmail || null,
    max: data.max,
    quotaMb: data.quota_mb,
    candidate: data.candidate || null,
    domain: data.domain
        ? { hostname: data.domain.hostname, dkimReady: !!data.domain.dkim_ready, records: data.domain.records || [] }
        : null,
    boxes: (data.boxes || []).map((b: any) => ({
        id: b.id,
        address: b.address,
        quotaMb: b.quota_mb,
        usedBytes: b.used_bytes ?? null,
        createdAt: b.created_at ? new Date(b.created_at) : null,
    })),
});
/* eslint-enable camelcase */

const base = (uuid: string) => `/api/client/servers/${uuid}/mail`;

export const getMail = (uuid: string): Promise<MailPage> => http.get(base(uuid)).then(({ data }) => map(data));

export const checkMail = (uuid: string): Promise<MailPage> =>
    http.post(`${base(uuid)}/check`).then(({ data }) => map(data));

export const enableMail = (uuid: string): Promise<MailPage> =>
    http.post(`${base(uuid)}/enable`).then(({ data }) => map(data));

export const disableMail = (uuid: string): Promise<MailPage> =>
    http.post(`${base(uuid)}/disable`).then(({ data }) => map(data));

export const createMailBox = (uuid: string, local: string, password: string): Promise<MailPage> =>
    http.post(`${base(uuid)}/boxes`, { local, password }).then(({ data }) => map(data));

export const setMailPassword = (uuid: string, id: number, password: string): Promise<MailPage> =>
    http.post(`${base(uuid)}/boxes/${id}/password`, { password }).then(({ data }) => map(data));

export const deleteMailBox = (uuid: string, id: number): Promise<MailPage> =>
    http.delete(`${base(uuid)}/boxes/${id}`).then(({ data }) => map(data));
