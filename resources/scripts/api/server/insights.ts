import http from '@/api/http';

export interface AlertSettings {
    events: {
        crash: boolean;
        start: boolean;
        stop: boolean;
        memory: boolean;
        disk: boolean;
        bell: boolean;
        dm: boolean;
    };
    webhookSet: boolean;
    webhookHint: string | null;
}

export type StatsRange = '24h' | '7d' | '30d';

export interface PlayerHistory {
    range: StatsRange;
    samples: number;
    peak: number;
    average: number;
    maxPlayers: number | null;
    points: { at: Date; players: number | null; peak: number | null }[];
    hours: (number | null)[];
}

/* eslint-disable camelcase */
const mapAlerts = (data: any): AlertSettings => ({
    events: data.events,
    webhookSet: !!data.webhook_set,
    webhookHint: data.webhook_hint || null,
});

export const getAlerts = (uuid: string): Promise<AlertSettings> =>
    http.get(`/api/client/servers/${uuid}/alerts`).then(({ data }) => mapAlerts(data));

export const saveAlerts = (
    uuid: string,
    values: { events: AlertSettings['events']; webhook: string; keepWebhook: boolean }
): Promise<AlertSettings> =>
    http
        .put(`/api/client/servers/${uuid}/alerts`, {
            events: values.events,
            webhook: values.webhook || null,
            keep_webhook: values.keepWebhook,
        })
        .then(({ data }) => mapAlerts(data));

export const testAlert = (uuid: string): Promise<void> =>
    http.post(`/api/client/servers/${uuid}/alerts/test`).then(() => undefined);

export const getPlayerHistory = (uuid: string, range: StatsRange): Promise<PlayerHistory> =>
    http.get(`/api/client/servers/${uuid}/players-history`, { params: { range } }).then(({ data }) => ({
        range: data.range,
        samples: data.samples,
        peak: data.peak,
        average: data.average,
        maxPlayers: data.max_players,
        points: (data.points || []).map((p: any) => ({ at: new Date(p.at), players: p.players, peak: p.peak })),
        hours: data.hours || [],
    }));

// What the server used: processor in % (100 per core), memory and disk in MB, uptime in % of the range.
export interface ResourceHistory {
    range: StatsRange;
    samples: number;
    cpu: { average: number; peak: number; limit: number };
    memory: { average: number; peak: number; limit: number };
    disk: { used: number; limit: number };
    uptime: number;
    points: {
        at: Date;
        cpu: number | null;
        cpuPeak: number | null;
        memory: number | null;
        memoryPeak: number | null;
    }[];
}

export const getResourceHistory = (uuid: string, range: StatsRange): Promise<ResourceHistory> =>
    http.get(`/api/client/servers/${uuid}/resources-history`, { params: { range } }).then(({ data }) => ({
        range: data.range,
        samples: data.samples,
        cpu: data.cpu,
        memory: data.memory,
        disk: data.disk,
        uptime: data.uptime,
        points: (data.points || []).map((p: any) => ({
            at: new Date(p.at),
            cpu: p.cpu,
            cpuPeak: p.cpu_peak,
            memory: p.memory,
            memoryPeak: p.memory_peak,
        })),
    }));

export interface PlayersLive {
    online: boolean;
    players: number | null;
    maxPlayers: number | null;
    via: 'steam' | 'minecraft' | 'bedrock' | 'fivem' | null;
    at: Date;
}

export const getPlayersLive = (uuid: string): Promise<PlayersLive> =>
    http.get(`/api/client/servers/${uuid}/players-live`).then(({ data }) => ({
        online: !!data.online,
        players: data.players ?? null,
        maxPlayers: data.max_players ?? null,
        via: data.via ?? null,
        at: new Date(data.at),
    }));
