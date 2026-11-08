import http from '@/api/http';

export type SslStatus = 'pending' | 'active' | 'failed';

export interface WebDomain {
    id: number;
    hostname: string;
    own: boolean;
    www: boolean;
    ready: boolean;
    ssl: SslStatus;
    sslError: string | null;
    url: string | null;
    // What the client must create in their own DNS.
    records: { type: string; name: string; content: string }[];
}

export interface WebApp {
    id: string;
    name: string;
    description: string;
    account: boolean;
}

export interface WebInstallStatus {
    state: 'running' | 'done' | 'failed';
    app: string;
    step: string;
    url: string | null;
    adminUrl: string | null;
}

export interface WebPage {
    php: boolean;
    agent: boolean;
    url: string | null;
    // The temporary address of the site, while no domain leads to it.
    preview: string | null;
    max: number;
    zones: { id: number; domain: string }[];
    domains: WebDomain[];
    apps: WebApp[];
    install: WebInstallStatus | null;
}

export interface WebInstallValues {
    app: string;
    title: string;
    adminUser: string;
    adminPassword: string;
    adminEmail: string;
    locale: string;
}

/* eslint-disable camelcase */
const mapInstall = (data: any): WebInstallStatus | null =>
    data
        ? { state: data.state, app: data.app, step: data.step, url: data.url || null, adminUrl: data.admin_url || null }
        : null;

const map = (data: any): WebPage => ({
    php: !!data.php,
    agent: !!data.agent,
    url: data.url || null,
    preview: data.preview || null,
    max: data.max || 0,
    zones: data.zones || [],
    domains: (data.domains || []).map((row: any) => ({
        id: row.id,
        hostname: row.hostname,
        own: !!row.own,
        www: !!row.www,
        ready: !!row.ready,
        ssl: row.ssl,
        sslError: row.ssl_error || null,
        url: row.url || null,
        records: row.records || [],
    })),
    apps: data.apps || [],
    install: mapInstall(data.install),
});

const base = (uuid: string) => `/api/client/servers/${uuid}/web`;

export const getWeb = (uuid: string): Promise<WebPage> => http.get(base(uuid)).then(({ data }) => map(data));

export const getWebInstall = (uuid: string): Promise<WebInstallStatus | null> =>
    http.get(`${base(uuid)}/status`).then(({ data }) => mapInstall(data.install));

export const addWebDomain = (uuid: string, hostname: string): Promise<WebPage> =>
    http.post(`${base(uuid)}/domains`, { hostname }).then(({ data }) => map(data));

export const addWebSubdomain = (uuid: string, name: string, domainId: number): Promise<WebPage> =>
    http.post(`${base(uuid)}/subdomains`, { name, domain_id: domainId }).then(({ data }) => map(data));

export const checkWebDomain = (uuid: string, id: number): Promise<WebPage> =>
    http.post(`${base(uuid)}/domains/${id}/check`).then(({ data }) => map(data));

export const deleteWebDomain = (uuid: string, id: number): Promise<WebPage> =>
    http.delete(`${base(uuid)}/domains/${id}`).then(({ data }) => map(data));

export const uninstallWebApp = (uuid: string): Promise<WebPage> =>
    http.post(`${base(uuid)}/uninstall`).then(({ data }) => map(data));

export const installWebApp = (uuid: string, values: WebInstallValues): Promise<WebPage> =>
    http
        .post(`${base(uuid)}/install`, {
            app: values.app,
            title: values.title || null,
            admin_user: values.adminUser || null,
            admin_password: values.adminPassword || null,
            admin_email: values.adminEmail || null,
            locale: values.locale,
        })
        .then(({ data }) => map(data));
