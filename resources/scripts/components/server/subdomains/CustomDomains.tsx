import React, { useState } from 'react';
import tw from 'twin.macro';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import {
    faCheckCircle,
    faCircleNotch,
    faCopy,
    faHourglassHalf,
    faPlus,
    faSyncAlt,
    faTrashAlt,
} from '@fortawesome/free-solid-svg-icons';
import { Button } from '@/components/elements/button/index';
import { Dialog } from '@/components/elements/dialog';
import Can from '@/components/elements/Can';
import {
    addCustomDomain,
    CustomDomainRow,
    deleteCustomDomain,
    SubdomainPage,
    updateCustomDomain,
    verifyCustomDomain,
} from '@/api/server/subdomains';

const field = tw`w-full rounded-xl bg-black/30 border border-white/10 px-3 py-2.5 text-sm text-neutral-100 focus:outline-none focus:border-primary-500`;
const label = tw`block text-xs uppercase tracking-wide font-semibold text-neutral-300 mb-1.5`;

const Copy = ({ value }: { value: string }) => {
    const [done, setDone] = useState(false);

    return (
        <button
            type={'button'}
            css={tw`ml-2 text-neutral-400 hover:text-white focus:outline-none`}
            onClick={() => {
                navigator.clipboard?.writeText(value).then(() => {
                    setDone(true);
                    setTimeout(() => setDone(false), 1500);
                });
            }}
        >
            <FontAwesomeIcon icon={done ? faCheckCircle : faCopy} />
        </button>
    );
};

// The records the user creates in their own DNS, ready to copy.
const RecordsTable = ({ domain }: { domain: CustomDomainRow }) =>
    domain.records.length === 0 ? (
        <p css={tw`text-xs text-yellow-200`}>Pick an allocation to see what to create in your DNS.</p>
    ) : (
        <div css={tw`overflow-x-auto rounded-xl border border-white/10 bg-black/30`}>
            <table css={tw`min-w-full text-sm`}>
                <thead>
                    <tr css={tw`text-xs uppercase tracking-wide text-neutral-400`}>
                        <th css={tw`text-left px-3 py-2`}>Type</th>
                        <th css={tw`text-left px-3 py-2`}>Name</th>
                        <th css={tw`text-left px-3 py-2`}>Value</th>
                    </tr>
                </thead>
                <tbody>
                    {domain.records.map((r, i) => (
                        <tr key={i} css={tw`border-t border-white/5`}>
                            <td css={tw`px-3 py-2 font-mono text-primary-300`}>{r.type}</td>
                            <td css={tw`px-3 py-2 font-mono text-neutral-100 whitespace-nowrap`}>
                                {r.name}
                                <Copy value={r.name} />
                            </td>
                            <td css={tw`px-3 py-2 font-mono text-neutral-100 whitespace-nowrap`}>
                                {r.content}
                                <Copy value={r.content} />
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );

interface Props {
    uuid: string;
    page: SubdomainPage;
    busy: boolean;
    run: (action: Promise<SubdomainPage>, done?: () => void) => void;
}

export default ({ uuid, page, busy, run }: Props) => {
    const [adding, setAdding] = useState(false);
    const [removing, setRemoving] = useState<CustomDomainRow | null>(null);
    const [form, setForm] = useState({ hostname: '', allocationId: null as number | null, notes: '' });

    if (!page.custom.enabled && page.custom.data.length === 0) {
        return null;
    }

    const open = () => {
        const primary = page.allocations.find((a) => a.primary) || page.allocations[0];
        setForm({ hostname: '', allocationId: primary ? primary.id : null, notes: '' });
        setAdding(true);
    };

    return (
        <div css={tw`rounded-2xl bg-neutral-800 border border-white/10 shadow-card overflow-hidden mt-6`}>
            <div css={tw`flex flex-wrap items-center gap-3 px-4 py-3 border-b border-white/10`}>
                <div css={tw`flex-1 min-w-0`}>
                    <h2 css={tw`text-base text-neutral-50 font-semibold`}>My domains</h2>
                    <p css={tw`text-xs text-neutral-400`}>
                        Use your own domain: add its name here, then create the records shown in your DNS (at your
                        registrar or Cloudflare). The panel checks that it leads to the server.
                    </p>
                </div>
                {page.custom.enabled && (
                    <Can action={'allocation.create'}>
                        <Button.Text
                            type={'button'}
                            size={Button.Sizes.Small}
                            onClick={open}
                            disabled={busy || page.custom.data.length >= page.custom.max}
                        >
                            <FontAwesomeIcon icon={faPlus} css={tw`mr-1.5`} /> <span>Add my domain</span>
                        </Button.Text>
                    </Can>
                )}
            </div>

            {page.custom.data.length === 0 ? (
                <p css={tw`px-4 py-6 text-center text-sm text-neutral-400`}>No domain of yours yet.</p>
            ) : (
                page.custom.data.map((d) => (
                    <div key={d.id} css={tw`px-4 py-4 border-t border-white/5 first:border-t-0 space-y-3`}>
                        <div css={tw`flex flex-wrap items-center gap-3`}>
                            <span css={tw`font-mono text-sm text-neutral-50 truncate`}>{d.hostname}</span>
                            {d.verified ? (
                                <span
                                    css={tw`inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold bg-green-500/20 text-green-300`}
                                >
                                    <FontAwesomeIcon icon={faCheckCircle} /> <span>Points at the server</span>
                                </span>
                            ) : (
                                <span
                                    css={tw`inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold bg-yellow-500/20 text-yellow-300`}
                                >
                                    <FontAwesomeIcon icon={faHourglassHalf} /> <span>Waiting for your DNS</span>
                                </span>
                            )}
                            <div css={tw`ml-auto flex items-center gap-2`}>
                                <Button.Text
                                    type={'button'}
                                    size={Button.Sizes.Small}
                                    onClick={() => run(verifyCustomDomain(uuid, d.id))}
                                    disabled={busy}
                                >
                                    <FontAwesomeIcon icon={faSyncAlt} css={tw`mr-1.5`} /> <span>Check</span>
                                </Button.Text>
                                <Can action={'allocation.delete'}>
                                    <Button.Danger
                                        variant={Button.Variants.Secondary}
                                        size={Button.Sizes.Small}
                                        shape={Button.Shapes.IconSquare}
                                        type={'button'}
                                        onClick={() => setRemoving(d)}
                                        disabled={busy}
                                    >
                                        <FontAwesomeIcon icon={faTrashAlt} />
                                    </Button.Danger>
                                </Can>
                            </div>
                        </div>
                        <div css={tw`grid grid-cols-1 md:grid-cols-2 gap-3`}>
                            <div>
                                <label css={label}>Points at</label>
                                <select
                                    css={field}
                                    value={d.allocationId === null ? '' : String(d.allocationId)}
                                    disabled={busy}
                                    onChange={(e) =>
                                        run(
                                            updateCustomDomain(uuid, d.id, {
                                                allocationId: e.currentTarget.value === '' ? null : Number(e.currentTarget.value),
                                            })
                                        )
                                    }
                                >
                                    <option value={''}>Unassigned. Pick an allocation</option>
                                    {page.allocations.map((a) => (
                                        <option key={a.id} value={a.id}>
                                            {a.address}
                                            {a.primary ? ' (primary)' : ''}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <label css={label}>Notes</label>
                                <input
                                    css={field}
                                    defaultValue={d.notes || ''}
                                    maxLength={191}
                                    disabled={busy}
                                    onBlur={(e) =>
                                        e.currentTarget.value !== (d.notes || '') &&
                                        run(updateCustomDomain(uuid, d.id, { notes: e.currentTarget.value || null }))
                                    }
                                />
                            </div>
                        </div>
                        {!d.verified && (
                            <div>
                                <p css={tw`text-xs text-neutral-300 mb-2`}>
                                    Create these records in the DNS of your domain, then press Check. It can take a few
                                    minutes to work.
                                </p>
                                <RecordsTable domain={d} />
                            </div>
                        )}
                    </div>
                ))
            )}

            <Dialog open={adding} onClose={() => setAdding(false)} title={'Add my domain'}>
                <div css={tw`space-y-4`}>
                    <div>
                        <label css={label}>Domain name</label>
                        <input
                            css={field}
                            value={form.hostname}
                            placeholder={'play.example.com'}
                            autoFocus
                            onChange={(e) =>
                                setForm({ ...form, hostname: e.currentTarget.value.trim().toLowerCase() })
                            }
                        />
                        <p css={tw`text-xs text-neutral-400 mt-1.5`}>
                            The full name players will type, on a domain you own.
                        </p>
                    </div>
                    <div>
                        <label css={label}>Allocation</label>
                        <select
                            css={field}
                            value={form.allocationId === null ? '' : String(form.allocationId)}
                            onChange={(e) =>
                                setForm({
                                    ...form,
                                    allocationId: e.currentTarget.value === '' ? null : Number(e.currentTarget.value),
                                })
                            }
                        >
                            <option value={''}>Unassigned. Pick an allocation</option>
                            {page.allocations.map((a) => (
                                <option key={a.id} value={a.id}>
                                    {a.address}
                                    {a.primary ? ' (primary)' : ''}
                                </option>
                            ))}
                        </select>
                    </div>
                    <div>
                        <label css={label}>Notes</label>
                        <input
                            css={field}
                            value={form.notes}
                            maxLength={191}
                            onChange={(e) => setForm({ ...form, notes: e.currentTarget.value })}
                        />
                    </div>
                    <p css={tw`text-xs text-neutral-400`}>
                        No key is needed: you create the records yourself, the panel only shows them and checks them.
                    </p>
                </div>
                <Dialog.Footer>
                    <Button.Text type={'button'} onClick={() => setAdding(false)}>
                        Cancel
                    </Button.Text>
                    <Button
                        type={'button'}
                        disabled={busy || !form.hostname}
                        onClick={() => run(addCustomDomain(uuid, form), () => setAdding(false))}
                    >
                        {busy ? <FontAwesomeIcon icon={faCircleNotch} spin /> : <span>Add my domain</span>}
                    </Button>
                </Dialog.Footer>
            </Dialog>

            <Dialog.Confirm
                open={!!removing}
                onClose={() => setRemoving(null)}
                title={'Remove my domain'}
                confirm={'Remove'}
                onConfirmed={() => {
                    const target = removing;
                    setRemoving(null);
                    target && run(deleteCustomDomain(uuid, target.id));
                }}
            >
                <span>The domain is removed from this server. Remember to remove its records from your DNS:</span>{' '}
                <code>{removing?.hostname}</code>
            </Dialog.Confirm>
        </div>
    );
};
