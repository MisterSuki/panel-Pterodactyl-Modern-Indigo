import http from '@/api/http';

export interface ShopCategory {
    id: number;
    name: string;
}

// What a person chooses for the site they buy.
export interface SiteChoice {
    siteName: string;
    // The domain of their own, or the name they picked under the domain of the hosting.
    domain: string;
    subdomain: string;
}

export interface ShopOffer {
    id: number;
    // Set when the offer is a web hosting plan.
    web: { sites: number; domains: number; versions: string[] } | null;
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

export type OrderStatus = 'provisioning' | 'active' | 'expired' | 'failed';

export interface ShopOrder {
    id: number;
    name: string;
    status: OrderStatus;
    priceCents: number;
    durationDays: number;
    web: boolean;
    expiresAt: Date | null;
    server: { identifier: string; name: string } | null;
}

export interface ShopTransaction {
    id: number;
    type: 'topup' | 'purchase' | 'renewal' | 'refund' | 'adjust';
    amountCents: number;
    note: string | null;
    at: Date | null;
}

export interface ShopData {
    enabled: boolean;
    currency: string;
    balanceCents: number;
    minTopupCents: number;
    maxTopupCents: number;
    providers: { code: string; label: string }[];
    web: { freeSubdomain: boolean; baseDomain: string | null };
    categories: ShopCategory[];
    offers: ShopOffer[];
    orders: ShopOrder[];
    transactions: ShopTransaction[];
}

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
            web: { freeSubdomain: false, baseDomain: null },
            categories: [],
            offers: [],
            orders: [],
            transactions: [],
        };
    }

    return {
        enabled: true,
        currency: data.currency,
        balanceCents: data.balance_cents,
        minTopupCents: data.min_topup_cents,
        maxTopupCents: data.max_topup_cents,
        providers: data.providers,
        web: { freeSubdomain: !!data.web?.free_subdomain, baseDomain: data.web?.base_domain ?? null },
        categories: data.categories || [],
        offers: data.offers.map((o: any) => ({
            id: o.id,
            web: o.web ?? null,
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
            web: !!o.web,
            expiresAt: o.expires_at ? new Date(o.expires_at) : null,
            server: o.server,
        })),
        transactions: data.transactions.map((t: any) => ({
            id: t.id,
            type: t.type,
            amountCents: t.amount_cents,
            note: t.note,
            at: t.at ? new Date(t.at) : null,
        })),
    };
};

const choiceToBody = (choice?: SiteChoice) =>
    choice ? { site_name: choice.siteName, domain: choice.domain || null, subdomain: choice.subdomain || null } : {};

export const buyOffer = async (offerId: number, choice?: SiteChoice): Promise<void> => {
    await http.post('/api/client/shop/buy', { offer_id: offerId, ...choiceToBody(choice) });
};

export const renewOrder = async (orderId: number): Promise<void> => {
    await http.post('/api/client/shop/renew', { order_id: orderId });
};

// Opens a payment at a provider and gives back the address to send the person to.
export const startPayment = async (
    provider: string,
    what: { amountCents?: number; offerId?: number; orderId?: number; choice?: SiteChoice }
): Promise<string> => {
    const { data } = await http.post('/api/client/shop/topup', {
        provider,
        amount_cents: what.amountCents,
        offer_id: what.offerId,
        order_id: what.orderId,
        ...choiceToBody(what.choice),
    });

    return data.url;
};
