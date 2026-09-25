import React, { useState } from 'react';
import { Link, useHistory, useLocation } from 'react-router-dom';
import useSWR from 'swr';
import tw from 'twin.macro';
import classNames from 'classnames';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faCoins, faHdd, faMemory, faMicrochip, faServer, faStore } from '@fortawesome/free-solid-svg-icons';
import { useStoreState } from '@/state/hooks';
import PageContentBlock from '@/components/elements/PageContentBlock';
import Spinner from '@/components/elements/Spinner';
import { httpErrorToHuman } from '@/api/http';
import { bytesToString, mbToBytes } from '@/lib/formatters';
import {
    buyOffer,
    cancelOrder,
    createCustomServer,
    getShop,
    OrderStatus,
    renewOrder,
    ShopData,
    ShopOffer,
    ShopOrder,
    startPayment,
} from '@/api/shop';

type Tab = 'offers' | 'custom' | 'orders' | 'credit';

const useMoney = (currency: string) => (cents: number) =>
    new Intl.NumberFormat(undefined, { style: 'currency', currency }).format(cents / 100);

// The first of next month: when the monthly invoice for a custom server is made.
const nextFirstOfMonth = (): Date => {
    const now = new Date();

    return new Date(now.getFullYear(), now.getMonth() + 1, 1);
};

// A date, written in the language chosen by the person.
const useDate = (withTime = false) => {
    const language = useStoreState((state) => state.user.data?.language) || 'en';

    return (date: Date) =>
        new Intl.DateTimeFormat(language, {
            day: 'numeric',
            month: 'short',
            year: 'numeric',
            ...(withTime ? { hour: '2-digit', minute: '2-digit' } : {}),
        }).format(date);
};

const buttonStyle =
    'rounded-lg bg-primary-500 hover:bg-primary-400 px-4 py-2 text-sm font-semibold text-white transition-colors duration-150 disabled:opacity-50 disabled:cursor-not-allowed';
const quietButtonStyle =
    'rounded-lg border border-white/10 bg-white/5 hover:bg-white/10 px-4 py-2 text-sm font-semibold text-neutral-200 transition-colors duration-150 disabled:opacity-50';

const statusLabels: Record<OrderStatus, { label: string; className: string }> = {
    provisioning: { label: 'Being made', className: 'bg-blue-500/10 border-blue-500/30 text-blue-300' },
    active: { label: 'Paid', className: 'bg-green-500/10 border-green-500/30 text-green-300' },
    expired: { label: 'Ran out', className: 'bg-red-500/10 border-red-500/30 text-red-300' },
    failed: { label: 'Not delivered', className: 'bg-gray-500/10 border-gray-500/30 text-gray-300' },
    cancelled: { label: 'Cancelled', className: 'bg-gray-500/10 border-gray-500/30 text-gray-300' },
};

// The ways to pay. Picking one opens the payment at the provider and sends the person there.
const Providers = ({
    providers,
    onPick,
    busy,
}: {
    providers: ShopData['providers'];
    onPick: (code: string) => void;
    busy: boolean;
}) => (
    <div css={tw`flex flex-wrap gap-2`}>
        {providers.map((provider) => (
            <button
                key={provider.code}
                type={'button'}
                disabled={busy}
                onClick={() => onPick(provider.code)}
                className={quietButtonStyle}
            >
                <span>Pay with</span> {provider.label}
            </button>
        ))}
    </div>
);

const Spec = ({ icon, children }: { icon: any; children: React.ReactNode }) => (
    <li css={tw`flex items-center gap-2 text-sm text-neutral-300`}>
        <FontAwesomeIcon icon={icon} css={tw`w-4 text-primary-300`} />
        {children}
    </li>
);

const OfferCard = ({
    offer,
    shop,
    money,
    onDone,
    onError,
}: {
    offer: ShopOffer;
    shop: ShopData;
    money: (cents: number) => string;
    onDone: () => void;
    onError: (message: string) => void;
}) => {
    const [confirming, setConfirming] = useState(false);
    const [busy, setBusy] = useState(false);
    const enough = shop.balanceCents >= offer.priceCents;
    const missing = Math.max(50, offer.priceCents - shop.balanceCents);

    const buy = () => {
        setBusy(true);
        buyOffer(offer.id)
            .then(onDone)
            .catch((e) => onError(httpErrorToHuman(e)))
            .then(() => {
                setBusy(false);
                setConfirming(false);
            });
    };

    const pay = (provider: string) => {
        setBusy(true);
        startPayment(provider, { offerId: offer.id })
            .then((url) => {
                window.location.href = url;
            })
            .catch((e) => {
                onError(httpErrorToHuman(e));
                setBusy(false);
            });
    };

    return (
        <div css={tw`flex flex-col rounded-2xl border border-white/5 bg-neutral-800 p-5 shadow-card`}>
            <h3 css={tw`text-lg font-semibold text-neutral-50`}>{offer.name}</h3>
            <p css={tw`mt-1`}>
                <span css={tw`text-2xl font-bold text-primary-300`}>{money(offer.priceCents)}</span>
                <span css={tw`ml-1 text-sm text-neutral-400`}>/ {offer.durationDays} days</span>
            </p>
            {offer.description && (
                <p css={tw`mt-2 text-sm text-neutral-400 whitespace-pre-wrap`}>{offer.description}</p>
            )}
            <ul css={tw`mt-4 flex flex-col gap-1.5`}>
                <Spec icon={faMemory}>
                    {bytesToString(mbToBytes(offer.memory))} <span>of memory</span>
                </Spec>
                <Spec icon={faHdd}>
                    {bytesToString(mbToBytes(offer.disk))} <span>of disk</span>
                </Spec>
                <Spec icon={faMicrochip}>
                    {offer.cpu > 0 ? (
                        <>
                            {offer.cpu}% <span>of CPU</span>
                        </>
                    ) : (
                        'CPU not limited'
                    )}
                </Spec>
                {offer.location && <Spec icon={faServer}>{offer.location}</Spec>}
            </ul>
            <div css={tw`mt-auto pt-5`}>
                {!offer.available ? (
                    <p css={tw`text-sm text-red-300`}>Sold out</p>
                ) : confirming ? (
                    <div css={tw`flex flex-col gap-3`}>
                        <p css={tw`text-sm text-neutral-300`}>
                            <strong>{money(offer.priceCents)}</strong> <span>will be taken from your credit.</span>
                        </p>
                        <div css={tw`flex gap-2`}>
                            <button type={'button'} onClick={buy} disabled={busy} className={buttonStyle}>
                                Confirm
                            </button>
                            <button
                                type={'button'}
                                onClick={() => setConfirming(false)}
                                disabled={busy}
                                className={quietButtonStyle}
                            >
                                Cancel
                            </button>
                        </div>
                    </div>
                ) : enough ? (
                    <button
                        type={'button'}
                        onClick={() => setConfirming(true)}
                        className={classNames(buttonStyle, 'w-full')}
                    >
                        Buy
                    </button>
                ) : (
                    <div css={tw`flex flex-col gap-2`}>
                        <p css={tw`text-xs text-neutral-400`}>
                            <span>Missing</span> <strong>{money(missing)}</strong>
                        </p>
                        <Providers providers={shop.providers} onPick={pay} busy={busy} />
                    </div>
                )}
            </div>
        </div>
    );
};

const OrderRow = ({
    order,
    shop,
    money,
    onDone,
    onError,
}: {
    order: ShopOrder;
    shop: ShopData;
    money: (cents: number) => string;
    onDone: () => void;
    onError: (message: string) => void;
}) => {
    const [busy, setBusy] = useState(false);
    const [paying, setPaying] = useState(false);
    const enough = shop.balanceCents >= order.priceCents;
    // A custom server is billed monthly, so it is not renewed; the others can be renewed while they have a server.
    const canRenew = !order.custom && (order.status === 'active' || order.status === 'expired') && !!order.server;
    const canCancel = (order.status === 'active' || order.status === 'expired') && !!order.server;
    const status = statusLabels[order.status];
    const date = useDate();

    const renew = () => {
        setBusy(true);
        renewOrder(order.id)
            .then(onDone)
            .catch((e) => onError(httpErrorToHuman(e)))
            .then(() => setBusy(false));
    };

    const cancel = () => {
        if (
            !window.confirm('Cancel this server? It is stopped right away and stops being billed. Nothing is deleted.')
        ) {
            return;
        }
        setBusy(true);
        cancelOrder(order.id)
            .then(onDone)
            .catch((e) => onError(httpErrorToHuman(e)))
            .then(() => setBusy(false));
    };

    const pay = (provider: string) => {
        setBusy(true);
        startPayment(provider, { orderId: order.id })
            .then((url) => {
                window.location.href = url;
            })
            .catch((e) => {
                onError(httpErrorToHuman(e));
                setBusy(false);
            });
    };

    return (
        <div css={tw`mb-2 rounded-xl border border-white/5 bg-neutral-800 px-4 py-3 shadow-card`}>
            <div css={tw`flex flex-wrap items-center gap-3`}>
                <div css={tw`min-w-0 flex-1`}>
                    <p css={tw`font-medium text-neutral-50`}>
                        {order.server ? (
                            <Link
                                to={`/server/${order.server.identifier}`}
                                css={tw`text-neutral-50 hover:text-primary-300`}
                            >
                                {order.name}
                            </Link>
                        ) : (
                            order.name
                        )}
                    </p>
                    <p css={tw`text-xs text-neutral-400 mt-0.5`}>
                        {order.custom ? (
                            <>
                                {money(order.monthlyCents)} <span>/ month</span> · <span>Billed monthly</span>
                                {order.status === 'active' && (
                                    <>
                                        {' · '}
                                        <span>Next invoice on</span> {date(nextFirstOfMonth())}
                                    </>
                                )}
                            </>
                        ) : (
                            <>
                                {money(order.priceCents)} / {order.durationDays} days
                                {order.expiresAt && (
                                    <>
                                        {' · '}
                                        <span>{order.status === 'expired' ? 'Ran out on' : 'Paid until'}</span>{' '}
                                        {date(order.expiresAt)}
                                    </>
                                )}
                            </>
                        )}
                    </p>
                </div>
                <span
                    className={classNames(
                        'inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-medium',
                        status.className
                    )}
                >
                    {status.label}
                </span>
                {canRenew &&
                    (enough ? (
                        <button type={'button'} onClick={renew} disabled={busy} className={buttonStyle}>
                            Renew
                        </button>
                    ) : (
                        <button type={'button'} onClick={() => setPaying(!paying)} className={quietButtonStyle}>
                            Renew
                        </button>
                    ))}
                {canCancel && (
                    <button
                        type={'button'}
                        onClick={cancel}
                        disabled={busy}
                        className={classNames(quietButtonStyle, 'text-red-300')}
                    >
                        Cancel
                    </button>
                )}
            </div>
            {paying && !enough && (
                <div css={tw`mt-3`}>
                    <p css={tw`text-xs text-neutral-400 mb-2`}>
                        <span>Missing</span>{' '}
                        <strong>{money(Math.max(50, order.priceCents - shop.balanceCents))}</strong>
                    </p>
                    <Providers providers={shop.providers} onPick={pay} busy={busy} />
                </div>
            )}
            {order.status === 'expired' && (
                <p css={tw`mt-2 text-xs text-red-300`}>
                    {order.custom
                        ? 'The server is suspended for an unpaid invoice. It comes back once the invoice is paid.'
                        : 'The server is suspended until you renew it. Nothing was deleted.'}
                </p>
            )}
            {order.status === 'cancelled' && (
                <p css={tw`mt-2 text-xs text-neutral-400`}>
                    This order was cancelled. The server is suspended and no longer billed.
                </p>
            )}
        </div>
    );
};

const CreditTab = ({
    shop,
    money,
    onError,
}: {
    shop: ShopData;
    money: (cents: number) => string;
    onError: (message: string) => void;
}) => {
    const [amount, setAmount] = useState('10');
    const [busy, setBusy] = useState(false);
    const cents = Math.round(parseFloat(amount.replace(',', '.')) * 100);
    const valid = Number.isFinite(cents) && cents >= shop.minTopupCents && cents <= shop.maxTopupCents;
    const date = useDate(true);
    const presets = [500, 1000, 2000, 5000].filter((c) => c >= shop.minTopupCents && c <= shop.maxTopupCents);

    const pay = (provider: string) => {
        setBusy(true);
        startPayment(provider, { amountCents: cents })
            .then((url) => {
                window.location.href = url;
            })
            .catch((e) => {
                onError(httpErrorToHuman(e));
                setBusy(false);
            });
    };

    const labels: Record<string, string> = {
        topup: 'Credit added',
        purchase: 'Purchase',
        renewal: 'Renewal',
        refund: 'Paid back',
        adjust: 'Correction',
    };

    return (
        <>
            <div css={tw`mb-6 rounded-2xl border border-white/5 bg-neutral-800 p-5 shadow-card`}>
                <h3 css={tw`text-lg font-semibold text-neutral-50 mb-3`}>Add credit</h3>
                {shop.providers.length === 0 ? (
                    <p css={tw`text-sm text-neutral-400`}>No way to pay is available for the moment.</p>
                ) : (
                    <>
                        <div css={tw`flex flex-wrap items-center gap-2 mb-3`}>
                            {presets.map((preset) => (
                                <button
                                    key={preset}
                                    type={'button'}
                                    onClick={() => setAmount(String(preset / 100))}
                                    className={quietButtonStyle}
                                >
                                    {money(preset)}
                                </button>
                            ))}
                            <input
                                value={amount}
                                inputMode={'decimal'}
                                onChange={(e: React.ChangeEvent<HTMLInputElement>) => setAmount(e.currentTarget.value)}
                                css={tw`w-28 rounded-lg border border-white/10 bg-neutral-900 px-3 py-2 text-sm text-neutral-50`}
                                aria-label={'Amount'}
                            />
                        </div>
                        <p css={tw`text-xs text-neutral-400 mb-3`}>
                            Between {money(shop.minTopupCents)} and {money(shop.maxTopupCents)}.
                        </p>
                        {valid ? (
                            <Providers providers={shop.providers} onPick={pay} busy={busy} />
                        ) : (
                            <p css={tw`text-sm text-red-300`}>This amount is not possible.</p>
                        )}
                    </>
                )}
            </div>
            <h3 css={tw`text-lg font-semibold text-neutral-50 mb-2`}>History</h3>
            {shop.transactions.length === 0 ? (
                <p css={tw`text-sm text-neutral-400`}>Nothing yet.</p>
            ) : (
                shop.transactions.map((t) => (
                    <div
                        key={t.id}
                        css={tw`mb-1.5 flex items-center gap-3 rounded-lg border border-white/5 bg-neutral-800 px-4 py-2`}
                    >
                        <div css={tw`min-w-0 flex-1`}>
                            <p css={tw`text-sm text-neutral-100`}>{labels[t.type] || t.type}</p>
                            {t.note && <p css={tw`text-xs text-neutral-400 truncate`}>{t.note}</p>}
                        </div>
                        <span css={tw`text-xs text-neutral-500`}>{t.at && date(t.at)}</span>
                        <strong className={t.amountCents < 0 ? 'text-red-300' : 'text-green-300'}>
                            {t.amountCents > 0 ? '+' : ''}
                            {money(t.amountCents)}
                        </strong>
                    </div>
                ))
            )}
        </>
    );
};

// Build-your-own: the client picks a game, a location and the resources; the monthly price is worked out live, and the
// server is made right away (the rest of the month is taken from the credit, then it is billed every month).
// One resource as a slider: label + value on top, the slider below, and the monthly price of what is chosen.
const SliderRow = ({
    label,
    unitLabel,
    min,
    max,
    value,
    lineTotal,
    onChange,
}: {
    label: string;
    unitLabel: string;
    min: number;
    max: number;
    value: number;
    lineTotal: string;
    onChange: (value: number) => void;
}) => (
    <div css={tw`py-3 border-b border-white/5 last:border-0`}>
        <div css={tw`flex items-baseline justify-between gap-3`}>
            <p css={tw`text-sm font-medium text-neutral-100`}>{label}</p>
            <p css={tw`text-sm font-semibold text-neutral-50 tabular-nums`}>
                {value}
                <span css={tw`ml-2 text-xs font-normal text-neutral-400`}>{lineTotal}</span>
            </p>
        </div>
        <input
            type={'range'}
            min={min}
            max={max}
            step={1}
            value={value}
            onChange={(e: React.ChangeEvent<HTMLInputElement>) => onChange(Number(e.currentTarget.value))}
            css={tw`mt-2 w-full h-1.5 rounded-full appearance-none bg-neutral-700 cursor-pointer`}
            style={{ accentColor: '#6366f1' } as React.CSSProperties}
        />
        <p css={tw`mt-1 text-2xs text-neutral-500`}>{unitLabel}</p>
    </div>
);

const CustomTab = ({
    shop,
    money,
    onError,
    onDone,
}: {
    shop: ShopData;
    money: (cents: number) => string;
    onError: (message: string) => void;
    onDone: (identifier: string | null) => void;
}) => {
    const c = shop.custom;
    const [name, setName] = useState('');
    const [eggId, setEggId] = useState<number>(c.eggs[0]?.id ?? 0);
    const [locationId, setLocationId] = useState<number | null>(c.locations[0]?.id ?? null);
    const [values, setValues] = useState<Record<string, number>>(
        Object.fromEntries(c.items.map((i) => [i.key, i.default]))
    );
    // What the client typed for the egg's own variables (a FiveM licence...).
    const [vars, setVars] = useState<Record<string, string>>({});
    const [busy, setBusy] = useState(false);

    const egg = c.eggs.find((e) => e.id === eggId);
    const eggVars = egg?.variables ?? [];
    const monthly = c.items.reduce((sum, i) => sum + (values[i.key] ?? i.min) * i.priceCents, 0);
    const atLimit = c.maxPerUser > 0 && c.owned >= c.maxPerUser;
    const varsFilled = eggVars.every((v) => (vars[v.env] ?? '').trim() !== '');
    const ready =
        name.trim() !== '' &&
        eggId > 0 &&
        monthly > 0 &&
        (c.locations.length === 0 || locationId !== null) &&
        varsFilled;

    const field = tw`w-full rounded-lg border border-white/10 bg-neutral-900 px-3 py-2 text-sm text-neutral-50`;

    const create = () => {
        setBusy(true);
        onError('');
        createCustomServer({
            name,
            eggId,
            locationId: c.locations.length ? locationId : null,
            resources: values,
            variables: vars,
        })
            .then((r) => onDone(r.server))
            .catch((e) => onError(httpErrorToHuman(e)))
            .then(() => setBusy(false));
    };

    if (!c.enabled) {
        return <p css={tw`text-center text-neutral-400 py-10`}>Building a server is not available.</p>;
    }

    return (
        <div css={tw`grid grid-cols-1 lg:grid-cols-3 gap-4`}>
            <div css={tw`lg:col-span-2 rounded-2xl border border-white/5 bg-neutral-800 p-5`}>
                <div css={tw`grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4`}>
                    <div>
                        <label css={tw`mb-1 block text-xs text-neutral-400`}>Name of your server</label>
                        <input
                            css={field}
                            value={name}
                            maxLength={80}
                            onChange={(e) => setName(e.currentTarget.value)}
                        />
                    </div>
                    <div>
                        <label css={tw`mb-1 block text-xs text-neutral-400`}>Game</label>
                        <select css={field} value={eggId} onChange={(e) => setEggId(Number(e.currentTarget.value))}>
                            {c.eggs.map((egg) => (
                                <option key={egg.id} value={egg.id}>
                                    {egg.name}
                                </option>
                            ))}
                        </select>
                    </div>
                    {c.locations.length > 0 && (
                        <div>
                            <label css={tw`mb-1 block text-xs text-neutral-400`}>Location</label>
                            <select
                                css={field}
                                value={locationId ?? ''}
                                onChange={(e) => setLocationId(Number(e.currentTarget.value))}
                            >
                                {c.locations.map((loc) => (
                                    <option key={loc.id} value={loc.id}>
                                        {loc.name}
                                    </option>
                                ))}
                            </select>
                        </div>
                    )}
                </div>
                {eggVars.length > 0 && (
                    <div css={tw`mb-4 rounded-lg border border-white/5 bg-black/20 p-3`}>
                        <p css={tw`mb-2 text-xs font-semibold uppercase tracking-wider text-neutral-400`}>
                            <span>This game needs</span>
                        </p>
                        {eggVars.map((v) => (
                            <div key={v.env} css={tw`mb-2 last:mb-0`}>
                                <label css={tw`mb-1 block text-xs text-neutral-300`}>{v.name}</label>
                                <input
                                    css={field}
                                    value={vars[v.env] ?? ''}
                                    onChange={(e) => setVars((s) => ({ ...s, [v.env]: e.currentTarget.value }))}
                                />
                                {v.description && <p css={tw`mt-1 text-2xs text-neutral-500`}>{v.description}</p>}
                            </div>
                        ))}
                    </div>
                )}
                <div>
                    {c.items.map((item) => {
                        const value = values[item.key] ?? item.min;

                        return (
                            <SliderRow
                                key={item.key}
                                label={item.label}
                                unitLabel={`${money(item.priceCents)} / mois`}
                                min={item.min}
                                max={item.max}
                                value={value}
                                lineTotal={value > 0 ? money(value * item.priceCents) : '—'}
                                onChange={(v) => setValues((s) => ({ ...s, [item.key]: v }))}
                            />
                        );
                    })}
                </div>
            </div>
            <div css={tw`rounded-2xl border border-white/5 bg-neutral-800 p-5 flex flex-col`}>
                <h3 css={tw`text-lg font-semibold text-neutral-50`}>Your server</h3>
                <p css={tw`mt-3`}>
                    <span css={tw`text-2xl font-bold text-primary-300`}>{money(monthly)}</span>
                    <span css={tw`ml-1 text-sm text-neutral-400`}>/ month</span>
                </p>
                <p css={tw`mt-2 text-xs text-neutral-400`}>
                    The rest of this month is taken from your credit now, then it is on your monthly invoice. Your
                    credit: {money(shop.balanceCents)}.
                </p>
                {atLimit && (
                    <p css={tw`mt-3 text-xs text-yellow-300`}>
                        You have reached the number of custom servers you may have.
                    </p>
                )}
                <div css={tw`mt-auto pt-5`}>
                    <button
                        type={'button'}
                        onClick={create}
                        disabled={busy || !ready || atLimit}
                        className={classNames(buttonStyle, 'w-full')}
                    >
                        Create my server
                    </button>
                </div>
            </div>
        </div>
    );
};

export default () => {
    const query = new URLSearchParams(useLocation().search);
    const history = useHistory();
    const [tab, setTab] = useState<Tab>('offers');
    // The category whose offers are shown, or null for all of them.
    const [category, setCategory] = useState<number | null>(null);
    const [error, setError] = useState('');
    const [notice, setNotice] = useState('');
    const { data: shop, mutate } = useSWR<ShopData>('shop', getShop, {
        revalidateOnFocus: true,
        refreshInterval: 20000,
        // Do not hammer the server if it answers with an error (avoids piling up "Too Many Attempts").
        shouldRetryOnError: false,
    });

    // Move between tabs and forget any old error/notice so a stale message does not stay on screen.
    const goTo = (next: Tab) => {
        setError('');
        setNotice('');
        setTab(next);
    };
    const money = useMoney(shop?.currency || 'EUR');

    const result = query.get('payment');
    const banner =
        result === 'paid'
            ? {
                  text: 'Your payment was received. Thank you!',
                  className: 'border-green-500/30 bg-green-500/10 text-green-200',
              }
            : result === 'pending'
            ? {
                  text: 'Your payment is not confirmed yet. The credit is added as soon as the provider confirms it.',
                  className: 'border-yellow-500/30 bg-yellow-500/10 text-yellow-200',
              }
            : result === 'failed'
            ? {
                  text: 'The payment did not go through. You were not charged.',
                  className: 'border-red-500/30 bg-red-500/10 text-red-200',
              }
            : result === 'cancelled'
            ? { text: 'The payment was cancelled.', className: 'border-white/10 bg-white/5 text-neutral-200' }
            : null;

    const done = (message: string, next?: Tab) => () => {
        setError('');
        setNotice(message);
        if (next) {
            setTab(next);
        }
        mutate();
    };

    if (!shop) {
        return (
            <PageContentBlock title={'Shop'}>
                <Spinner size={'large'} centered />
            </PageContentBlock>
        );
    }

    if (!shop.enabled) {
        return (
            <PageContentBlock title={'Shop'}>
                <p css={tw`text-center text-neutral-400 py-10`}>The shop is closed.</p>
            </PageContentBlock>
        );
    }

    const tabs: { id: Tab; label: string }[] = [
        { id: 'offers', label: 'Offers' },
        ...(shop.custom.enabled ? [{ id: 'custom' as Tab, label: 'Build your own' }] : []),
        { id: 'orders', label: 'My orders' },
        { id: 'credit', label: 'Credit' },
    ];

    return (
        <PageContentBlock title={'Shop'}>
            <div css={tw`mb-5 flex flex-wrap items-end justify-between gap-3`}>
                <div>
                    <h1 css={tw`text-2xl font-semibold text-neutral-50 flex items-center gap-3`}>
                        <FontAwesomeIcon icon={faStore} css={tw`text-primary-400`} />
                        Shop
                    </h1>
                    <p css={tw`text-sm text-neutral-400 mt-1`}>Buy a server with your credit.</p>
                </div>
                <button
                    type={'button'}
                    onClick={() => goTo('credit')}
                    css={tw`flex items-center gap-3 rounded-xl border border-white/10 bg-neutral-800 px-4 py-2 shadow-card`}
                >
                    <FontAwesomeIcon icon={faCoins} css={tw`text-yellow-300`} />
                    <span css={tw`text-left`}>
                        <span css={tw`block text-2xs uppercase tracking-wider text-neutral-400`}>Your credit</span>
                        <strong css={tw`text-lg text-neutral-50`}>{money(shop.balanceCents)}</strong>
                    </span>
                </button>
            </div>

            {banner && (
                <p className={classNames('mb-4 rounded-lg border px-4 py-3 text-sm', banner.className)}>
                    {banner.text}
                </p>
            )}
            {notice && (
                <p
                    css={tw`mb-4 rounded-lg border border-green-500/30 bg-green-500/10 px-4 py-3 text-sm text-green-200`}
                >
                    {notice}
                </p>
            )}
            {error && (
                <p css={tw`mb-4 rounded-lg border border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-200`}>
                    {error}
                </p>
            )}

            <div css={tw`mb-5 flex gap-1 border-b border-white/10`}>
                {tabs.map(({ id, label }) => (
                    <button
                        key={id}
                        type={'button'}
                        onClick={() => goTo(id)}
                        className={classNames(
                            'px-4 py-2 text-sm font-medium border-b-2 -mb-px transition-colors duration-150',
                            tab === id
                                ? 'border-primary-400 text-neutral-50'
                                : 'border-transparent text-neutral-400 hover:text-neutral-200'
                        )}
                    >
                        {label}
                    </button>
                ))}
            </div>

            {tab === 'offers' && shop.categories.length > 0 && (
                <div css={tw`mb-4 flex flex-wrap gap-2`}>
                    {[{ id: null, name: 'All' }, ...shop.categories].map((item) => (
                        <button
                            key={String(item.id)}
                            type={'button'}
                            onClick={() => setCategory(item.id)}
                            className={classNames(
                                'rounded-full border px-4 py-1.5 text-sm font-medium transition-colors duration-150',
                                category === item.id
                                    ? 'border-primary-400 bg-primary-500/20 text-primary-100'
                                    : 'border-white/10 bg-white/5 text-neutral-300 hover:bg-white/10'
                            )}
                        >
                            {item.id === null ? <span>All</span> : item.name}
                        </button>
                    ))}
                </div>
            )}

            {tab === 'offers' &&
                (shop.offers.length === 0 ? (
                    <p css={tw`text-center text-neutral-400 py-10`}>Nothing is for sale for the moment.</p>
                ) : (
                    <div css={tw`grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4`}>
                        {shop.offers
                            .filter((offer) => category === null || offer.categoryId === category)
                            .map((offer) => (
                                <OfferCard
                                    key={offer.id}
                                    offer={offer}
                                    shop={shop}
                                    money={money}
                                    onDone={done(
                                        'Your server is being made. You find it in "My orders" and on your dashboard.',
                                        'orders'
                                    )}
                                    onError={setError}
                                />
                            ))}
                    </div>
                ))}

            {tab === 'custom' && (
                <CustomTab
                    shop={shop}
                    money={money}
                    onError={setError}
                    onDone={(identifier) => {
                        mutate();
                        if (identifier) {
                            history.push(`/server/${identifier}`);
                        } else {
                            done('Your server is being made. You find it on your dashboard.', 'orders')();
                        }
                    }}
                />
            )}

            {tab === 'orders' &&
                (shop.orders.length === 0 ? (
                    <p css={tw`text-center text-neutral-400 py-10`}>You have not bought anything yet.</p>
                ) : (
                    shop.orders.map((order) => (
                        <OrderRow
                            key={order.id}
                            order={order}
                            shop={shop}
                            money={money}
                            onDone={done('The order was renewed.')}
                            onError={setError}
                        />
                    ))
                ))}

            {tab === 'credit' && <CreditTab shop={shop} money={money} onError={setError} />}
        </PageContentBlock>
    );
};
