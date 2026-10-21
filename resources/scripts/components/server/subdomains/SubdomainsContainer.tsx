import React, { useEffect, useState } from 'react';
import tw from 'twin.macro';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faCircleNotch, faGlobe, faPlus, faTrashAlt } from '@fortawesome/free-solid-svg-icons';
import ServerContentBlock from '@/components/elements/ServerContentBlock';
import FlashMessageRender from '@/components/FlashMessageRender';
import { Button } from '@/components/elements/button/index';
import { Dialog } from '@/components/elements/dialog';
import Can from '@/components/elements/Can';
import { ServerContext } from '@/state/server';
import { useFlashKey } from '@/plugins/useFlash';
import {
    createSubdomain,
    deleteSubdomain,
    getSubdomains,
    SubdomainPage,
    SubdomainRow,
    updateSubdomain,
} from '@/api/server/subdomains';

const field = tw`w-full rounded-xl bg-black/30 border border-white/10 px-3 py-2.5 text-sm text-neutral-100 focus:outline-none focus:border-primary-500`;
const label = tw`block text-xs uppercase tracking-wide font-semibold text-neutral-300 mb-1.5`;

const AllocationSelect = ({
    page,
    value,
    onChange,
    disabled,
}: {
    page: SubdomainPage;
    value: number | null;
    onChange: (id: number | null) => void;
    disabled?: boolean;
}) => (
    <select
        css={[field, value === null && tw`text-neutral-400`]}
        value={value === null ? '' : String(value)}
        disabled={disabled}
        onChange={(e) => onChange(e.currentTarget.value === '' ? null : Number(e.currentTarget.value))}
    >
        <option value={''}>Unassigned. Pick an allocation</option>
        {page.allocations.map((a) => (
            <option key={a.id} value={a.id}>
                {a.address}
                {a.primary ? ' (primary)' : ''}
            </option>
        ))}
    </select>
);

const Row = ({
    row,
    page,
    busy,
    onChange,
    onDelete,
}: {
    row: SubdomainRow;
    page: SubdomainPage;
    busy: boolean;
    onChange: (values: { allocationId?: number | null; notes?: string | null }) => void;
    onDelete: () => void;
}) => {
    const [notes, setNotes] = useState(row.notes || '');
    useEffect(() => setNotes(row.notes || ''), [row.notes]);

    return (
        <div
            css={tw`grid grid-cols-1 md:grid-cols-12 gap-3 items-center px-4 py-3 border-t border-white/5 first:border-t-0`}
        >
            <div css={tw`md:col-span-4 min-w-0`}>
                <div css={tw`rounded-xl bg-black/30 border border-white/10 px-3 py-2.5 text-sm text-neutral-100 truncate`}>
                    {row.fqdn}
                </div>
            </div>
            <div css={tw`md:col-span-4`}>
                <Can action={'allocation.update'} renderOnError={<AllocationSelect page={page} value={row.allocationId} onChange={() => undefined} disabled />}>
                    <AllocationSelect page={page} value={row.allocationId} onChange={(id) => onChange({ allocationId: id })} disabled={busy} />
                </Can>
            </div>
            <div css={tw`md:col-span-3`}>
                <input
                    css={field}
                    value={notes}
                    maxLength={191}
                    placeholder={'Notes'}
                    onChange={(e) => setNotes(e.currentTarget.value)}
                    onBlur={() => notes !== (row.notes || '') && onChange({ notes: notes || null })}
                    disabled={busy}
                />
            </div>
            <div css={tw`md:col-span-1 flex justify-end`}>
                <Can action={'allocation.delete'}>
                    <Button.Danger
                        variant={Button.Variants.Secondary}
                        size={Button.Sizes.Small}
                        shape={Button.Shapes.IconSquare}
                        type={'button'}
                        onClick={onDelete}
                        disabled={busy}
                    >
                        <FontAwesomeIcon icon={faTrashAlt} />
                    </Button.Danger>
                </Can>
            </div>
        </div>
    );
};

export default () => {
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const { clearFlashes, clearAndAddHttpError } = useFlashKey('server:subdomains');
    const [page, setPage] = useState<SubdomainPage | null>(null);
    const [busy, setBusy] = useState(false);
    const [creating, setCreating] = useState(false);
    const [removing, setRemoving] = useState<SubdomainRow | null>(null);
    const [form, setForm] = useState({ name: '', domainId: 0, allocationId: null as number | null, notes: '' });

    useEffect(() => {
        getSubdomains(uuid)
            .then(setPage)
            .catch((error) => clearAndAddHttpError(error));
    }, [uuid]);

    const run = (action: Promise<SubdomainPage>, done?: () => void) => {
        clearFlashes();
        setBusy(true);
        action
            .then((next) => {
                setPage(next);
                done && done();
            })
            .catch((error) => clearAndAddHttpError(error))
            .then(() => setBusy(false));
    };

    const openCreate = () => {
        if (!page) return;
        const primary = page.allocations.find((a) => a.primary) || page.allocations[0];
        setForm({ name: '', domainId: page.domains[0]?.id || 0, allocationId: primary ? primary.id : null, notes: '' });
        setCreating(true);
    };

    const canCreate = !!page && page.used < page.limit && page.domains.length > 0;

    return (
        <ServerContentBlock title={'Subdomains'}>
            <FlashMessageRender byKey={'server:subdomains'} css={tw`mb-4`} />
            {!page ? (
                <div css={tw`flex justify-center py-16 text-neutral-400`}>
                    <FontAwesomeIcon icon={faCircleNotch} spin size={'2x'} />
                </div>
            ) : (
                <>
                    <div css={tw`flex flex-wrap items-center gap-3 mb-4`}>
                        <span
                            css={tw`flex items-center justify-center w-11 h-11 rounded-2xl bg-primary-500 bg-opacity-20 text-primary-300 text-lg flex-shrink-0`}
                        >
                            <FontAwesomeIcon icon={faGlobe} />
                        </span>
                        <div css={tw`flex-1 min-w-0`}>
                            <h1 css={tw`text-xl text-neutral-50 font-semibold`}>Subdomains</h1>
                            <p css={tw`text-sm text-neutral-400`}>
                                {page.used} / {page.limit} <span>subdomains used.</span>
                            </p>
                        </div>
                        <Can action={'allocation.create'}>
                            <Button type={'button'} onClick={openCreate} disabled={!canCreate || busy}>
                                <FontAwesomeIcon icon={faPlus} css={tw`mr-1.5`} /> <span>Create subdomain</span>
                            </Button>
                        </Can>
                    </div>

                    {page.limit === 0 && (
                        <p css={tw`text-sm text-neutral-400 mb-4`}>This server cannot make subdomains.</p>
                    )}
                    {page.limit > 0 && page.domains.length === 0 && (
                        <p css={tw`text-sm text-neutral-400 mb-4`}>No domain is available for subdomains right now.</p>
                    )}

                    <div css={tw`rounded-2xl bg-neutral-800 border border-white/10 shadow-card overflow-hidden`}>
                        <div
                            css={tw`hidden md:grid grid-cols-12 gap-3 px-4 py-3 text-xs uppercase tracking-wide font-semibold text-neutral-400 border-b border-white/10`}
                        >
                            <span css={tw`col-span-4`}>Subdomain</span>
                            <span css={tw`col-span-4`}>Points at</span>
                            <span css={tw`col-span-3`}>Notes</span>
                        </div>
                        {page.data.length === 0 ? (
                            <p css={tw`px-4 py-8 text-center text-sm text-neutral-400`}>No subdomain yet.</p>
                        ) : (
                            page.data.map((row) => (
                                <Row
                                    key={row.id}
                                    row={row}
                                    page={page}
                                    busy={busy}
                                    onChange={(values) => run(updateSubdomain(uuid, row.id, values))}
                                    onDelete={() => setRemoving(row)}
                                />
                            ))
                        )}
                    </div>

                    <Dialog open={creating} onClose={() => setCreating(false)} title={'Create subdomain'}>
                        <div css={tw`space-y-4`}>
                            <div>
                                <label css={label}>Name</label>
                                <input
                                    css={field}
                                    value={form.name}
                                    placeholder={'play'}
                                    autoFocus
                                    maxLength={63}
                                    onChange={(e) =>
                                        setForm({ ...form, name: e.currentTarget.value.toLowerCase().replace(/[^a-z0-9-]/g, '') })
                                    }
                                />
                                <p css={tw`text-xs text-neutral-400 mt-1.5`}>Lowercase letters, numbers and dashes.</p>
                            </div>
                            <div>
                                <label css={label}>Domain</label>
                                <select
                                    css={field}
                                    value={form.domainId}
                                    onChange={(e) => setForm({ ...form, domainId: Number(e.currentTarget.value) })}
                                >
                                    {page.domains.map((d) => (
                                        <option key={d.id} value={d.id}>
                                            {d.domain}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <label css={label}>Allocation</label>
                                <AllocationSelect
                                    page={page}
                                    value={form.allocationId}
                                    onChange={(id) => setForm({ ...form, allocationId: id })}
                                />
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
                            {form.name && page.domains.find((d) => d.id === form.domainId) && (
                                <p css={tw`text-sm text-neutral-300`}>
                                    <span>Address:</span>{' '}
                                    <code css={tw`text-primary-300`}>
                                        {form.name}.{page.domains.find((d) => d.id === form.domainId)!.domain}
                                    </code>
                                </p>
                            )}
                        </div>
                        <Dialog.Footer>
                            <Button.Text type={'button'} onClick={() => setCreating(false)}>
                                Cancel
                            </Button.Text>
                            <Button
                                type={'button'}
                                disabled={busy || !form.name || !form.domainId}
                                onClick={() => run(createSubdomain(uuid, form), () => setCreating(false))}
                            >
                                {busy ? <FontAwesomeIcon icon={faCircleNotch} spin /> : <span>Create subdomain</span>}
                            </Button>
                        </Dialog.Footer>
                    </Dialog>

                    <Dialog.Confirm
                        open={!!removing}
                        onClose={() => setRemoving(null)}
                        title={'Remove the subdomain'}
                        confirm={'Remove'}
                        onConfirmed={() => {
                            const target = removing;
                            setRemoving(null);
                            target && run(deleteSubdomain(uuid, target.id));
                        }}
                    >
                        <span>The subdomain and its DNS records will be removed:</span> <code>{removing?.fqdn}</code>
                    </Dialog.Confirm>
                </>
            )}
        </ServerContentBlock>
    );
};
