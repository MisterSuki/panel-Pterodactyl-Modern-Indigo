import React, { useEffect, useState } from 'react';
import tw from 'twin.macro';
import useSWR from 'swr';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faCheck, faPen, faTrashAlt, faUserPlus, faUsers } from '@fortawesome/free-solid-svg-icons';
import { Button } from '@/components/elements/button/index';
import { Dialog } from '@/components/elements/dialog';
import { Input } from '@/components/elements/inputs';
import FlashMessageRender from '@/components/FlashMessageRender';
import {
    addVpsUser,
    getVpsUsers,
    removeVpsUser,
    updateVpsUser,
    VPS_PERMISSIONS,
    VpsPermission,
    VpsSubuser,
} from '@/api/vps';
import useFlash from '@/plugins/useFlash';

const label = (permission: VpsPermission) => VPS_PERMISSIONS.find((p) => p.value === permission)?.label || permission;

// The sub-users of a VPS, for its owner: other people of the panel who see the machine, with what they may do on it.
export default ({ uuid }: { uuid: string }) => {
    const { addFlash, clearFlashes, clearAndAddHttpError } = useFlash();
    const {
        data: users,
        error,
        mutate,
    } = useSWR<VpsSubuser[]>(['vps-users', uuid], () => getVpsUsers(uuid), {
        revalidateOnFocus: false,
    });
    // null: closed; "new": adding someone; a sub-user: changing what they may do.
    const [editing, setEditing] = useState<VpsSubuser | 'new' | null>(null);
    const [removing, setRemoving] = useState<VpsSubuser | null>(null);
    const [email, setEmail] = useState('');
    const [permissions, setPermissions] = useState<VpsPermission[]>([]);
    const [saving, setSaving] = useState(false);

    useEffect(() => {
        if (error) clearAndAddHttpError({ key: 'vps:view', error });
    }, [error]);

    const open = (subuser: VpsSubuser | 'new') => {
        clearFlashes('vps:users');
        setEmail(subuser === 'new' ? '' : subuser.email);
        setPermissions(subuser === 'new' ? ['console'] : subuser.permissions);
        setEditing(subuser);
    };

    const toggle = (permission: VpsPermission) =>
        setPermissions((list) =>
            list.includes(permission) ? list.filter((p) => p !== permission) : [...list, permission]
        );

    const save = (e?: React.FormEvent) => {
        e?.preventDefault();
        if (!editing || saving) return;
        setSaving(true);
        clearFlashes('vps:users');
        const request =
            editing === 'new'
                ? addVpsUser(uuid, email.trim(), permissions)
                : updateVpsUser(uuid, editing.id, permissions);
        request
            .then((saved) => {
                mutate(
                    (list) =>
                        editing === 'new'
                            ? [...(list || []), saved]
                            : (list || []).map((item) => (item.id === saved.id ? saved : item)),
                    false
                );
                addFlash({
                    key: 'vps:view',
                    type: 'success',
                    message: editing === 'new' ? 'The sub-user was added.' : 'The permissions were saved.',
                });
                setEditing(null);
            })
            .catch((e) => clearAndAddHttpError({ key: 'vps:users', error: e }))
            .then(() => setSaving(false));
    };

    const remove = () => {
        if (!removing) return;
        const target = removing;
        setRemoving(null);
        removeVpsUser(uuid, target.id)
            .then(() => {
                mutate((list) => (list || []).filter((item) => item.id !== target.id), false);
                addFlash({ key: 'vps:view', type: 'success', message: 'The sub-user was removed.' });
            })
            .catch((e) => clearAndAddHttpError({ key: 'vps:view', error: e }));
    };

    return (
        <div className={'pd-neon'} css={tw`relative rounded-xl bg-neutral-800 p-4`}>
            <div css={tw`relative flex flex-wrap items-start justify-between gap-3`}>
                <div css={tw`min-w-0`}>
                    <p css={tw`flex items-center gap-2 text-sm font-medium text-neutral-200`}>
                        <FontAwesomeIcon icon={faUsers} css={tw`text-neutral-500 text-xs`} />
                        <span>Sub-users</span>
                    </p>
                    <p css={tw`mt-1 text-xs text-neutral-500`}>
                        Give other people of the panel access to this VPS, with only what they need.
                    </p>
                </div>
                <Button size={Button.Sizes.Small} onClick={() => open('new')}>
                    <FontAwesomeIcon icon={faUserPlus} css={tw`mr-2 text-xs`} />
                    <span>Add a sub-user</span>
                </Button>
            </div>

            <div css={tw`relative mt-4`}>
                {!users ? (
                    <div className={'pd-skeleton'} css={tw`h-14 rounded-lg`} />
                ) : users.length === 0 ? (
                    <p
                        css={tw`rounded-lg border border-dashed border-neutral-600 px-4 py-5 text-center text-sm text-neutral-400`}
                    >
                        Nobody else has access to this VPS.
                    </p>
                ) : (
                    <div css={tw`flex flex-col divide-y divide-neutral-700`}>
                        {users.map((subuser) => (
                            <div key={subuser.id} css={tw`flex flex-wrap items-center gap-3 py-3 first:pt-0 last:pb-0`}>
                                <div
                                    css={tw`flex-shrink-0 flex items-center justify-center w-9 h-9 rounded-full bg-neutral-700 text-sm font-semibold text-neutral-100 uppercase`}
                                >
                                    {subuser.username.charAt(0)}
                                </div>
                                <div css={tw`min-w-0 flex-1`}>
                                    <p css={tw`text-sm font-medium text-neutral-100 truncate`}>{subuser.username}</p>
                                    <p css={tw`text-xs text-neutral-500 truncate`}>{subuser.email}</p>
                                </div>
                                <div css={tw`flex flex-wrap gap-1.5`}>
                                    {subuser.permissions.length === 0 ? (
                                        <span css={tw`text-xs text-neutral-500`}>See only</span>
                                    ) : (
                                        subuser.permissions.map((permission) => (
                                            <span
                                                key={permission}
                                                css={tw`rounded-full border border-neutral-600 px-2 py-0.5 text-xs text-neutral-300`}
                                            >
                                                {label(permission)}
                                            </span>
                                        ))
                                    )}
                                </div>
                                <div css={tw`flex gap-1`}>
                                    <button
                                        type={'button'}
                                        onClick={() => open(subuser)}
                                        title={'Change the permissions'}
                                        aria-label={'Change the permissions'}
                                        css={tw`flex items-center justify-center w-8 h-8 rounded-md text-neutral-400 hover:text-neutral-50 hover:bg-neutral-700`}
                                    >
                                        <FontAwesomeIcon icon={faPen} css={tw`text-xs`} />
                                    </button>
                                    <button
                                        type={'button'}
                                        onClick={() => setRemoving(subuser)}
                                        title={'Remove'}
                                        aria-label={'Remove'}
                                        css={tw`flex items-center justify-center w-8 h-8 rounded-md text-neutral-400 hover:text-red-400 hover:bg-neutral-700`}
                                    >
                                        <FontAwesomeIcon icon={faTrashAlt} css={tw`text-xs`} />
                                    </button>
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </div>

            <Dialog
                open={editing !== null}
                onClose={() => setEditing(null)}
                title={editing === 'new' ? 'Add a sub-user' : 'Permissions of the sub-user'}
            >
                <form id={'vps-subuser-form'} onSubmit={save} css={tw`mt-4`}>
                    <FlashMessageRender byKey={'vps:users'} className={'mb-4'} />
                    <label htmlFor={'vps-subuser-email'} css={tw`block pb-1 text-sm text-neutral-300`}>
                        Email of their account
                    </label>
                    <Input.Text
                        id={'vps-subuser-email'}
                        type={'email'}
                        value={email}
                        autoFocus={editing === 'new'}
                        disabled={editing !== 'new'}
                        placeholder={'name@example.com'}
                        onChange={(e: React.ChangeEvent<HTMLInputElement>) => setEmail(e.currentTarget.value)}
                    />
                    <p css={tw`mt-1 text-xs text-neutral-500`}>
                        The person needs an account on the panel. They always see the state, the figures and the
                        addresses of the VPS.
                    </p>
                    <div css={tw`mt-5 flex flex-col gap-2`}>
                        {VPS_PERMISSIONS.map((permission) => {
                            const checked = permissions.includes(permission.value);

                            return (
                                <label
                                    key={permission.value}
                                    css={[
                                        tw`flex items-start gap-3 rounded-lg border px-3 py-2.5 cursor-pointer transition-colors duration-150`,
                                        checked
                                            ? tw`border-primary-500 bg-neutral-700`
                                            : tw`border-neutral-600 hover:border-neutral-500`,
                                    ]}
                                >
                                    <input
                                        type={'checkbox'}
                                        checked={checked}
                                        onChange={() => toggle(permission.value)}
                                        css={tw`sr-only`}
                                    />
                                    <span
                                        css={[
                                            tw`flex-shrink-0 mt-0.5 flex items-center justify-center w-4 h-4 rounded border text-white`,
                                            checked ? tw`border-primary-500 bg-primary-500` : tw`border-neutral-500`,
                                        ]}
                                    >
                                        {checked && <FontAwesomeIcon icon={faCheck} css={tw`text-xs`} />}
                                    </span>
                                    <span css={tw`min-w-0`}>
                                        <span css={tw`block text-sm font-medium text-neutral-100`}>
                                            {permission.label}
                                        </span>
                                        <span css={tw`block text-xs text-neutral-400`}>{permission.description}</span>
                                    </span>
                                </label>
                            );
                        })}
                    </div>
                </form>
                <Dialog.Footer>
                    <Button.Text onClick={() => setEditing(null)}>Cancel</Button.Text>
                    <Button
                        type={'submit'}
                        form={'vps-subuser-form'}
                        disabled={saving || (editing === 'new' && !email.trim())}
                    >
                        {editing === 'new' ? 'Add' : 'Save'}
                    </Button>
                </Dialog.Footer>
            </Dialog>

            <Dialog.Confirm
                open={removing !== null}
                title={'Remove this sub-user?'}
                confirm={'Remove'}
                onClose={() => setRemoving(null)}
                onConfirmed={remove}
            >
                This person will no longer see this VPS.
            </Dialog.Confirm>
        </div>
    );
};
