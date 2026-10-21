import http from '@/api/http';

export interface SubdomainRow {
    id: number;
    name: string;
    domain: string;
    fqdn: string;
    allocationId: number | null;
    notes: string | null;
    records: number;
}

export interface CustomDomainRow {
    id: number;
    hostname: string;
    allocationId: number | null;
    notes: string | null;
    verified: boolean;
    // What the user must create in their own DNS.
    records: { type: string; name: string; content: string }[];
}

export interface SubdomainPage {
    custom: { enabled: boolean; max: number; data: CustomDomainRow[] };
    manageUrl: string | null;
    limit: number;
    used: number;
    domains: { id: number; domain: string }[];
    allocations: { id: number; address: string; primary: boolean }[];
    data: SubdomainRow[];
}

/* eslint-disable camelcase */
const map = (data: any): SubdomainPage => ({
    manageUrl: data.manage_url || null,
    limit: data.limit,
    used: data.used,
    domains: data.domains || [],
    custom: {
        enabled: !!data.custom?.enabled,
        max: data.custom?.max || 0,
        data: (data.custom?.data || []).map((row: any) => ({
            id: row.id,
            hostname: row.hostname,
            allocationId: row.allocation_id,
            notes: row.notes,
            verified: !!row.verified,
            records: row.records || [],
        })),
    },
    allocations: data.allocations || [],
    data: (data.data || []).map((row: any) => ({
        id: row.id,
        name: row.name,
        domain: row.domain,
        fqdn: row.fqdn,
        allocationId: row.allocation_id,
        notes: row.notes,
        records: row.records,
    })),
});

export const getSubdomains = (uuid: string): Promise<SubdomainPage> =>
    http.get(`/api/client/servers/${uuid}/subdomains`).then(({ data }) => map(data));

export const createSubdomain = (
    uuid: string,
    values: { name: string; domainId: number; allocationId: number | null; notes: string }
): Promise<SubdomainPage> =>
    http
        .post(`/api/client/servers/${uuid}/subdomains`, {
            name: values.name,
            domain_id: values.domainId,
            allocation_id: values.allocationId,
            notes: values.notes || null,
        })
        .then(({ data }) => map(data));

export const updateSubdomain = (
    uuid: string,
    id: number,
    values: { allocationId?: number | null; notes?: string | null }
): Promise<SubdomainPage> => {
    const body: Record<string, unknown> = {};
    if (values.allocationId !== undefined) body.allocation_id = values.allocationId;
    if (values.notes !== undefined) body.notes = values.notes;

    return http.post(`/api/client/servers/${uuid}/subdomains/${id}`, body).then(({ data }) => map(data));
};

export const deleteSubdomain = (uuid: string, id: number): Promise<SubdomainPage> =>
    http.delete(`/api/client/servers/${uuid}/subdomains/${id}`).then(({ data }) => map(data));

export const addCustomDomain = (
    uuid: string,
    values: { hostname: string; allocationId: number | null; notes: string }
): Promise<SubdomainPage> =>
    http
        .post(`/api/client/servers/${uuid}/subdomains/custom`, {
            hostname: values.hostname,
            allocation_id: values.allocationId,
            notes: values.notes || null,
        })
        .then(({ data }) => map(data));

export const updateCustomDomain = (
    uuid: string,
    id: number,
    values: { allocationId?: number | null; notes?: string | null }
): Promise<SubdomainPage> => {
    const body: Record<string, unknown> = {};
    if (values.allocationId !== undefined) body.allocation_id = values.allocationId;
    if (values.notes !== undefined) body.notes = values.notes;

    return http.post(`/api/client/servers/${uuid}/subdomains/custom/${id}`, body).then(({ data }) => map(data));
};

export const verifyCustomDomain = (uuid: string, id: number): Promise<SubdomainPage> =>
    http.post(`/api/client/servers/${uuid}/subdomains/custom/${id}/verify`).then(({ data }) => map(data));

export const deleteCustomDomain = (uuid: string, id: number): Promise<SubdomainPage> =>
    http.delete(`/api/client/servers/${uuid}/subdomains/custom/${id}`).then(({ data }) => map(data));
