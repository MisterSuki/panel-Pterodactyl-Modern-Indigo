import http from '@/api/http';

export type VpsStatus = 'running' | 'stopped' | 'paused' | 'busy' | 'unknown';

export type VpsAction = 'start' | 'shutdown' | 'reboot' | 'stop';

// "creating" while the panel makes the machine on Proxmox, "deleting" while it removes it, "failed" when that went wrong.
export type VpsState = 'ready' | 'creating' | 'deleting' | 'failed';

// A virtual machine of Proxmox given to the person by the administration.
export interface Vps {
    uuid: string;
    name: string;
    state: VpsState;
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
    // False when the person is a sub-user of the VPS.
    owner: boolean;
}

// What a sub-user may do: start, stop and restart it; open its console; see the root password.
export type VpsPermission = 'power' | 'console' | 'password';

export const VPS_PERMISSIONS: { value: VpsPermission; label: string; description: string }[] = [
    { value: 'power', label: 'Power', description: 'Start, shut down, restart and stop the machine' },
    { value: 'console', label: 'Console', description: 'Open the screen of the machine and use it' },
    { value: 'password', label: 'Root password', description: 'See the root password set when the VPS was created' },
];

export interface VpsSubuser {
    id: number;
    username: string;
    email: string;
    permissions: VpsPermission[];
    createdAt: Date | null;
}

export interface VpsDetails extends Vps {
    // Set when Proxmox could not be reached: the sentence to show.
    error: string | null;
    addresses: string[];
    os: string | null;
    agent: boolean;
    // The root password set when the panel created the machine, null for a machine made on Proxmox.
    rootPassword: string | null;
    // True when the console relay runs on the panel, so the console (noVNC) can be opened.
    console: boolean;
    // "owner" plus every permission for the owner, the permissions given to a sub-user.
    permissions: string[];
    // The sub-user line of the person, so they can leave the VPS (null for the owner).
    subuserId: number | null;
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
    state: data.state || 'ready',
    status: data.status,
    cpu: data.cpu ?? null,
    cores: data.cores ?? null,
    memory: data.memory ?? null,
    maxMemory: data.max_memory ?? null,
    maxDisk: data.max_disk ?? null,
    netIn: data.net_in ?? null,
    netOut: data.net_out ?? null,
    uptime: data.uptime ?? null,
    owner: data.owner !== false,
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
        rootPassword: data.data.root_password || null,
        console: !!data.data.console,
        permissions: data.data.permissions || [],
        subuserId: data.data.subuser_id ?? null,
    }));

const mapSubuser = (data: any): VpsSubuser => ({
    id: data.id,
    username: data.username,
    email: data.email,
    permissions: data.permissions || [],
    createdAt: data.created_at ? new Date(data.created_at) : null,
});

export const getVpsUsers = (uuid: string): Promise<VpsSubuser[]> =>
    http.get(`/api/client/vps/${uuid}/users`).then(({ data }) => (data.data || []).map(mapSubuser));

export const addVpsUser = (uuid: string, email: string, permissions: VpsPermission[]): Promise<VpsSubuser> =>
    http.post(`/api/client/vps/${uuid}/users`, { email, permissions }).then(({ data }) => mapSubuser(data.data));

export const updateVpsUser = (uuid: string, id: number, permissions: VpsPermission[]): Promise<VpsSubuser> =>
    http.put(`/api/client/vps/${uuid}/users/${id}`, { permissions }).then(({ data }) => mapSubuser(data.data));

export const removeVpsUser = (uuid: string, id: number): Promise<void> =>
    http.delete(`/api/client/vps/${uuid}/users/${id}`).then(() => undefined);

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

// A one-time session of the console: the path of the relay to open as a websocket, and the VNC password.
export const openVpsConsole = (uuid: string): Promise<{ path: string; password: string }> =>
    http.post(`/api/client/vps/${uuid}/console`).then(({ data }) => data.data);

export const sendVpsPower = (uuid: string, action: VpsAction): Promise<void> =>
    http.post(`/api/client/vps/${uuid}/power`, { action }).then(() => undefined);
