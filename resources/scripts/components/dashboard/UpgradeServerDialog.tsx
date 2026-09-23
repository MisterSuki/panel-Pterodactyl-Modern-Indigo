import React, { useEffect, useMemo, useState } from 'react';
import { Dialog } from '@/components/elements/dialog';
import { Button } from '@/components/elements/button/index';
import Spinner from '@/components/elements/Spinner';
import tw from 'twin.macro';
import { getServerResources, updateServerResources, ResourceItem, ServerResources } from '@/api/shop';
import useFlash from '@/plugins/useFlash';

interface Props {
    server: string;
    serverName: string;
    open: boolean;
    onClose: () => void;
    onSaved?: () => void;
}

const money = (currency: string) => (cents: number) =>
    new Intl.NumberFormat(undefined, { style: 'currency', currency }).format(cents / 100);

// One resource line: a label, a stepper (− / value / +) and the monthly price of what is chosen above the offer.
const Row = ({
    item,
    value,
    fmt,
    onChange,
}: {
    item: ResourceItem;
    value: number;
    fmt: (cents: number) => string;
    onChange: (value: number) => void;
}) => {
    const extra = Math.max(0, value - item.min);
    const step = (delta: number) => onChange(Math.min(item.max, Math.max(item.min, value + delta)));

    return (
        <div css={tw`flex items-center justify-between gap-3 py-2.5 border-b border-white/5 last:border-0`}>
            <div css={tw`min-w-0`}>
                <p css={tw`text-sm font-medium text-neutral-100`}>{item.label}</p>
                <p css={tw`text-xs text-neutral-400`}>
                    {fmt(item.priceCents)} <span>each / month</span> &middot; <span>included</span> {item.min}
                </p>
            </div>
            <div css={tw`flex items-center gap-2 flex-shrink-0`}>
                <button
                    type={'button'}
                    onClick={() => step(-1)}
                    disabled={value <= item.min}
                    css={tw`w-8 h-8 rounded-lg border border-white/10 bg-white/5 text-neutral-200 disabled:opacity-40 hover:bg-white/10`}
                >
                    −
                </button>
                <span css={tw`w-12 text-center tabular-nums text-neutral-50 font-semibold`}>{value}</span>
                <button
                    type={'button'}
                    onClick={() => step(1)}
                    disabled={value >= item.max}
                    css={tw`w-8 h-8 rounded-lg border border-white/10 bg-white/5 text-neutral-200 disabled:opacity-40 hover:bg-white/10`}
                >
                    +
                </button>
                <span css={tw`w-16 text-right text-xs text-neutral-400 tabular-nums`}>
                    {extra > 0 ? '+' + fmt(extra * item.priceCents) : '—'}
                </span>
            </div>
        </div>
    );
};

// The popup opened from the little settings button on a server card: raise or lower the resources of a server bought in
// the shop. What is added above the offer is billed monthly (a change now is only charged for the days left in the month).
export default ({ server, serverName, open, onClose, onSaved }: Props) => {
    const { clearAndAddHttpError, clearFlashes } = useFlash();
    const [data, setData] = useState<ServerResources | null>(null);
    const [values, setValues] = useState<Record<string, number>>({});
    const [loading, setLoading] = useState(false);
    const [saving, setSaving] = useState(false);

    useEffect(() => {
        if (!open) {
            return;
        }
        setLoading(true);
        setData(null);
        getServerResources(server)
            .then((res) => {
                setData(res);
                setValues(Object.fromEntries(res.items.map((i) => [i.key, i.current])));
            })
            .catch((error) => clearAndAddHttpError({ key: 'dashboard', error }))
            .then(() => setLoading(false));
    }, [open, server]);

    const fmt = money(data?.currency || 'EUR');

    // The new monthly total for the resources above the offer, from what is chosen.
    const newMonthly = useMemo(() => {
        if (!data) return 0;

        return data.items.reduce((sum, i) => sum + Math.max(0, (values[i.key] ?? i.min) - i.min) * i.priceCents, 0);
    }, [data, values]);

    const changed = !!data && data.items.some((i) => (values[i.key] ?? i.min) !== i.current);

    const save = () => {
        setSaving(true);
        clearFlashes('dashboard');
        updateServerResources(server, values)
            .then(() => {
                onSaved?.();
                onClose();
            })
            .catch((error) => clearAndAddHttpError({ key: 'dashboard', error }))
            .then(() => setSaving(false));
    };

    return (
        <Dialog open={open} onClose={onClose} title={'Server resources'} description={serverName}>
            {loading || !data ? (
                <div css={tw`py-8`}>
                    <Spinner centered />
                </div>
            ) : data.items.length === 0 ? (
                <p css={tw`text-sm text-neutral-400`}>No resource can be changed on this server for now.</p>
            ) : (
                <>
                    <div css={tw`mt-2`}>
                        {data.items.map((item) => (
                            <Row
                                key={item.key}
                                item={item}
                                fmt={fmt}
                                value={values[item.key] ?? item.current}
                                onChange={(v) => setValues((s) => ({ ...s, [item.key]: v }))}
                            />
                        ))}
                    </div>
                    <div css={tw`mt-4 rounded-lg bg-black/20 border border-white/5 p-3 text-sm`}>
                        <div css={tw`flex items-center justify-between`}>
                            <span css={tw`text-neutral-300`}>New monthly cost for the extra resources</span>
                            <strong css={tw`text-primary-300`}>{fmt(newMonthly)}</strong>
                        </div>
                        <p css={tw`text-xs text-neutral-400 mt-1`}>
                            <span>
                                A change is billed only for the days left in the month, on your next invoice, and paid
                                from your credit.
                            </span>{' '}
                            <span>Your credit:</span> {fmt(data.balanceCents)}
                        </p>
                    </div>
                    <Dialog.Footer>
                        <Button.Text onClick={onClose} disabled={saving}>
                            Cancel
                        </Button.Text>
                        <Button onClick={save} disabled={saving || !changed}>
                            Apply
                        </Button>
                    </Dialog.Footer>
                </>
            )}
        </Dialog>
    );
};
