import http from '@/api/http';

export interface HostingDomain {
    id: number;
    domain: string;
    includeWww: boolean;
    isPrimary: boolean;
    status: 'pending' | 'verified';
}

export interface HostingSite {
    id: number;
    name: string;
    status: string;
    phpVersion: string | null;
    phpVersions: string[];
    server: { identifier: string; name: string } | null;
    domains: HostingDomain[];
}

export interface HostingAccount {
    id: number;
    plan: string;
    status: 'active' | 'suspended';
    maxSites: number;
    maxDomains: number;
    canCreateSite: boolean;
    sites: HostingSite[];
}

export interface HostingData {
    enabled: boolean;
    // Where a domain has to lead, and the domain under which every site gets a free name.
    ips: string[];
    baseDomain: string | null;
    accounts: HostingAccount[];
}

export const getHosting = async (): Promise<HostingData> => {
    const { data } = await http.get('/api/client/hosting');
    if (!data.enabled) {
        return { enabled: false, ips: [], baseDomain: null, accounts: [] };
    }

    return {
        enabled: true,
        ips: data.ips,
        baseDomain: data.base_domain,
        accounts: data.accounts.map((a: any) => ({
            id: a.id,
            plan: a.plan,
            status: a.status,
            maxSites: a.max_sites,
            maxDomains: a.max_domains,
            canCreateSite: a.can_create_site,
            sites: a.sites.map((s: any) => ({
                id: s.id,
                name: s.name,
                status: s.status,
                phpVersion: s.php_version,
                phpVersions: s.php_versions,
                server: s.server,
                domains: s.domains.map((d: any) => ({
                    id: d.id,
                    domain: d.domain,
                    includeWww: d.include_www,
                    isPrimary: d.is_primary,
                    status: d.status,
                })),
            })),
        })),
    };
};

export const createSite = async (accountId: number, name: string, domain: string): Promise<void> => {
    await http.post('/api/client/hosting/sites', { account_id: accountId, name, domain: domain || null });
};

export const addDomain = async (siteId: number, domain: string, includeWww: boolean): Promise<void> => {
    await http.post(`/api/client/hosting/sites/${siteId}/domains`, { domain, include_www: includeWww });
};

export const removeDomain = async (siteId: number, domainId: number): Promise<void> => {
    await http.delete(`/api/client/hosting/sites/${siteId}/domains/${domainId}`);
};

export const verifyDomain = async (siteId: number, domainId: number): Promise<boolean> => {
    const { data } = await http.post(`/api/client/hosting/sites/${siteId}/domains/${domainId}/verify`);

    return !!data.verified;
};

export const setPhpVersion = async (siteId: number, version: string): Promise<void> => {
    await http.put(`/api/client/hosting/sites/${siteId}/php`, { version });
};
