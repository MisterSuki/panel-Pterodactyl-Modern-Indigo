import http from '@/api/http';

export interface ShopCategory {
    id: number;
    name: string;
}

export interface ShopOffer {
    id: number;
    categoryId: number | null;
    name: string;
    description: string | null;
    priceCents: number;
    durationDays: number;
    memory: number;
    disk: number;
    cpu: number;
    databases: number;
    backups: number;
    location: string | null;
    available: boolean;
    stock: number | null;
}

export type OrderStatus = 'provisioning' | 'active' | 'expired' | 'failed' | 'cancelled';

export interface ShopOrder {
    id: number;
    name: string;
    status: OrderStatus;
    priceCents: number;
    durationDays: number;
    expiresAt: Date | null;
    // A custom server built by the client: billed monthly, no fixed expiry, cannot be renewed.
    custom: boolean;
    monthlyCents: number;
    server: { identifier: string; name: string } | null;
}

export interface ShopTransaction {
    id: number;
    type: 'topup' | 'purchase' | 'renewal' | 'refund' | 'adjust';
    amountCents: number;
    note: string | null;
    at: Date | null;
}

// What is offered for building a custom server, and its resources.
export interface CustomItem {
    key: string;
    label: string;
    unit: number;
    min: number;
    max: number;
    default: number;
    priceCents: number;
}

export interface CustomConfig {
    enabled: boolean;
    eggs: { id: number; name: string }[];
    locations: { id: number; name: string }[];
    items: CustomItem[];
    maxPerUser: number;
    owned: number;
}

export interface ShopData {
    enabled: boolean;
    currency: string;
    balanceCents: number;
    minTopupCents: number;
    maxTopupCents: number;
    providers: { code: string; label: string }[];
    categories: ShopCategory[];
    offers: ShopOffer[];
    orders: ShopOrder[];
    transactions: ShopTransaction[];
    custom: CustomConfig;
}

const emptyCustom: CustomConfig = { enabled: false, eggs: [], locations: [], items: [], maxPerUser: 0, owned: 0 };

const mapCustom = (c: any): CustomConfig =>
    c
        ? {
              enabled: !!c.enabled,
              eggs: c.eggs || [],
              locations: c.locations || [],
              items: (c.items || []).map((i: any) => ({
                  key: i.key,
                  label: i.label,
                  unit: i.unit,
                  min: i.min,
                  max: i.max,
                  default: i.default,
                  priceCents: i.price,
              })),
              maxPerUser: c.max_per_user ?? c.maxPerUser ?? 0,
              owned: c.owned ?? 0,
          }
        : emptyCustom;

export const getShop = async (): Promise<ShopData> => {
    const { data } = await http.get('/api/client/shop');
    if (!data.enabled) {
        return {
            enabled: false,
            currency: 'EUR',
            balanceCents: 0,
            minTopupCents: 0,
            maxTopupCents: 0,
            providers: [],
            categories: [],
            offers: [],
            orders: [],
            transactions: [],
            custom: emptyCustom,
        };
    }

    return {
        enabled: true,
        currency: data.currency,
        balanceCents: data.balance_cents,
        minTopupCents: data.min_topup_cents,
        maxTopupCents: data.max_topup_cents,
        providers: data.providers,
        categories: data.categories || [],
        offers: data.offers.map((o: any) => ({
            id: o.id,
            categoryId: o.category_id ?? null,
            name: o.name,
            description: o.description,
            priceCents: o.price_cents,
            durationDays: o.duration_days,
            memory: o.memory,
            disk: o.disk,
            cpu: o.cpu,
            databases: o.databases,
            backups: o.backups,
            location: o.location,
            available: o.available,
            stock: o.stock,
        })),
        orders: data.orders.map((o: any) => ({
            id: o.id,
            name: o.name,
            status: o.status,
            priceCents: o.price_cents,
            durationDays: o.duration_days,
            expiresAt: o.expires_at ? new Date(o.expires_at) : null,
            custom: !!o.custom,
            monthlyCents: o.monthly_cents ?? 0,
            server: o.server,
        })),
        transactions: data.transactions.map((t: any) => ({
            id: t.id,
            type: t.type,
            amountCents: t.amount_cents,
            note: t.note,
            at: t.at ? new Date(t.at) : null,
        })),
        custom: mapCustom(data.custom),
    };
};

// Builds a custom server; gives back the identifier of the new server so the person can be sent to it.
export const createCustomServer = async (what: {
    name: string;
    eggId: number;
    locationId: number | null;
    resources: Record<string, number>;
}): Promise<{ server: string | null; balanceCents: number }> => {
    const { data } = await http.post('/api/client/shop/custom', {
        name: what.name,
        egg_id: what.eggId,
        location_id: what.locationId,
        resources: what.resources,
    });

    return { server: data.server ?? null, balanceCents: data.balance_cents };
};

export const buyOffer = async (offerId: number): Promise<void> => {
    await http.post('/api/client/shop/buy', { offer_id: offerId });
};

export const renewOrder = async (orderId: number): Promise<void> => {
    await http.post('/api/client/shop/renew', { order_id: orderId });
};

export const cancelOrder = async (orderId: number): Promise<void> => {
    await http.post('/api/client/shop/cancel', { order_id: orderId });
};

// One resource a client can raise or lower on a server bought in the shop, billed monthly.
export interface ResourceItem {
    key: string;
    label: string;
    unit: number;
    min: number;
    max: number;
    current: number;
    priceCents: number;
}

export interface ServerResources {
    currency: string;
    balanceCents: number;
    monthlyCents: number;
    items: ResourceItem[];
}

// The servers the person may change the resources of (to show the little settings button on the dashboard).
export const getUpgradeableServers = async (): Promise<string[]> => {
    const { data } = await http.get('/api/client/shop/upgradeable');

    return data.servers || [];
};

export const getServerResources = async (server: string): Promise<ServerResources> => {
    const { data } = await http.get(`/api/client/shop/servers/${server}/resources`);

    return {
        currency: data.currency,
        balanceCents: data.balance_cents,
        monthlyCents: data.monthly_cents,
        items: (data.items || []).map((i: any) => ({
            key: i.key,
            label: i.label,
            unit: i.unit,
            min: i.min,
            max: i.max,
            current: i.current,
            priceCents: i.price_cents,
        })),
    };
};

export const updateServerResources = async (
    server: string,
    resources: Record<string, number>
): Promise<{ monthlyCents: number; balanceCents: number }> => {
    const { data } = await http.put(`/api/client/shop/servers/${server}/resources`, { resources });

    return { monthlyCents: data.monthly_cents, balanceCents: data.balance_cents };
};

// Opens a payment at a provider and gives back the address to send the person to.
export const startPayment = async (
    provider: string,
    what: { amountCents?: number; offerId?: number; orderId?: number }
): Promise<string> => {
    const { data } = await http.post('/api/client/shop/topup', {
        provider,
        amount_cents: what.amountCents,
        offer_id: what.offerId,
        order_id: what.orderId,
    });

    return data.url;
};
