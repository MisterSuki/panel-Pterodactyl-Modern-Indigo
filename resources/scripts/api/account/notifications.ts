import http from '@/api/http';

export interface PanelNotification {
    id: number;
    kind: 'info' | 'success' | 'warning' | 'danger' | 'maintenance' | 'update';
    title: string;
    body: string | null;
    url: string | null;
    everyone: boolean;
    read: boolean;
    createdAt: Date | null;
}

export interface NotificationList {
    unread: number;
    data: PanelNotification[];
}

/* eslint-disable camelcase */
const map = (data: any): NotificationList => ({
    unread: data.unread || 0,
    data: (data.data || []).map((n: any) => ({
        id: n.id,
        kind: n.kind,
        title: n.title,
        body: n.body,
        url: n.url,
        everyone: !!n.everyone,
        read: !!n.read,
        createdAt: n.created_at ? new Date(n.created_at) : null,
    })),
});

export const getNotifications = (): Promise<NotificationList> =>
    http.get('/api/client/account/notifications').then(({ data }) => map(data));

export const readNotifications = (ids?: number[]): Promise<NotificationList> =>
    http.post('/api/client/account/notifications/read', ids ? { ids } : {}).then(({ data }) => map(data));
