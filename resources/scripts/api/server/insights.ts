import http from '@/api/http';

export interface AlertSettings {
    events: { crash: boolean; start: boolean; stop: boolean; memory: boolean; disk: boolean; bell: boolean };
    webhookSet: boolean;
    webhookHint: string | null;
}

export interface PlayerHistory {
    range: '24h' | '7d';
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

export const getPlayerHistory = (uuid: string, range: '24h' | '7d'): Promise<PlayerHistory> =>
    http.get(`/api/client/servers/${uuid}/players-history`, { params: { range } }).then(({ data }) => ({
        range: data.range,
        samples: data.samples,
        peak: data.peak,
        average: data.average,
        maxPlayers: data.max_players,
        points: (data.points || []).map((p: any) => ({ at: new Date(p.at), players: p.players, peak: p.peak })),
        hours: data.hours || [],
    }));
