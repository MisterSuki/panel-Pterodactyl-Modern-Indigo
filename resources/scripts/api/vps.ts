import http from '@/api/http';

export type VpsStatus = 'running' | 'stopped' | 'paused' | 'busy' | 'unknown';

export type VpsAction = 'start' | 'shutdown' | 'reboot' | 'stop';

// A virtual machine of Proxmox given to the person by the administration.
export interface Vps {
    uuid: string;
    name: string;
    status: VpsStatus;
    // In percent of the whole machine (all of its cores).
    cpu: number | null;
    cores: number | null;
    memory: number | null;
    maxMemory: number | null;
    maxDisk: number | null;
    netIn: number | null;
    netOut: number | null;
    // Seconds since it started.
    uptime: number | null;
}

export interface VpsDetails extends Vps {
    // Set when Proxmox could not be reached: the sentence to show.
    error: string | null;
    addresses: string[];
    os: string | null;
    agent: boolean;
}

export interface VpsPoint {
    at: Date;
    cpu: number | null;
    memory: number | null;
    netIn: number | null;
    netOut: number | null;
}

/* eslint-disable camelcase */
const map = (data: any): Vps => ({
    uuid: data.uuid,
    name: data.name,
    status: data.status,
    cpu: data.cpu ?? null,
    cores: data.cores ?? null,
    memory: data.memory ?? null,
    maxMemory: data.max_memory ?? null,
    maxDisk: data.max_disk ?? null,
    netIn: data.net_in ?? null,
    netOut: data.net_out ?? null,
    uptime: data.uptime ?? null,
});
/* eslint-enable camelcase */

export const getVpsList = (): Promise<Vps[]> =>
    http.get('/api/client/vps').then(({ data }) => (data.data || []).map(map));

export const getVps = (uuid: string): Promise<VpsDetails> =>
    http.get(`/api/client/vps/${uuid}`).then(({ data }) => ({
        ...map(data.data),
        error: data.data.error || null,
        addresses: data.data.addresses || [],
        os: data.data.os || null,
        agent: !!data.data.agent,
    }));

export const getVpsHistory = (uuid: string, range: 'hour' | 'day' | 'week'): Promise<VpsPoint[]> =>
    http.get(`/api/client/vps/${uuid}/history`, { params: { range } }).then(({ data }) =>
        (data.data || []).map((point: any) => ({
            at: new Date(point.at * 1000),
            cpu: point.cpu ?? null,
            memory: point.memory ?? null,
            netIn: point.net_in ?? null,
            netOut: point.net_out ?? null,
        }))
    );

export const sendVpsPower = (uuid: string, action: VpsAction): Promise<void> =>
    http.post(`/api/client/vps/${uuid}/power`, { action }).then(() => undefined);
