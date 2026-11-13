import React, { useEffect, useState } from 'react';
import tw from 'twin.macro';
import { format, formatDistanceToNow } from 'date-fns';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faCircleNotch, faFingerprint, faPlus, faTrashAlt } from '@fortawesome/free-solid-svg-icons';
import ContentBox from '@/components/elements/ContentBox';
import { Button } from '@/components/elements/button/index';
import { Dialog } from '@/components/elements/dialog';
import useFlash from '@/plugins/useFlash';
import { dateLocale } from '@/components/elements/activity/kinds';
import {
    addPasskey,
    deletePasskey,
    deviceName,
    getPasskeys,
    Passkey,
    passkeyError,
    passkeysSupported,
} from '@/api/passkeys';

const FLASH_KEY = 'account:passkeys';

// The passkeys of the account: Face ID, Touch ID, Windows Hello or a security key instead of the password.
export default ({ className }: { className?: string }) => {
    const { addFlash, clearFlashes, clearAndAddHttpError } = useFlash();
    const [list, setList] = useState<Passkey[] | null>(null);
    const [busy, setBusy] = useState(false);
    const [removing, setRemoving] = useState<Passkey | null>(null);
    const supported = passkeysSupported();

    const fail = (error: any) => {
        const message = passkeyError(error);
        if (message) {
            addFlash({ key: FLASH_KEY, type: 'error', title: 'Error', message });
        } else {
            clearAndAddHttpError({ key: FLASH_KEY, error });
        }
    };

    useEffect(() => {
        getPasskeys()
            .then(setList)
            .catch((error) => {
                setList([]);
                fail(error);
            });
    }, []);

    const add = () => {
        clearFlashes(FLASH_KEY);
        setBusy(true);
        addPasskey(deviceName())
            .then((passkeys) => {
                setList(passkeys);
                addFlash({
                    key: FLASH_KEY,
                    type: 'success',
                    title: 'Success',
                    message: 'The passkey is ready: use it on the sign-in page.',
                });
            })
            .catch(fail)
            .then(() => setBusy(false));
    };

    const remove = () => {
        if (!removing) return;
        clearFlashes(FLASH_KEY);
        setBusy(true);
        deletePasskey(removing.id)
            .then(setList)
            .catch(fail)
            .then(() => {
                setBusy(false);
                setRemoving(null);
            });
    };

    return (
        <ContentBox title={'Passkeys'} showFlashes={FLASH_KEY} className={className}>
            <div css={tw`flex flex-wrap items-center gap-4`}>
                <span
                    css={tw`flex items-center justify-center w-12 h-12 rounded-2xl text-white text-xl flex-shrink-0`}
                    style={{
                        background: 'linear-gradient(180deg, #34c759 0%, #248a3d 100%)',
                        boxShadow: 'inset 0 1px 0 rgba(255,255,255,0.3)',
                    }}
                >
                    <FontAwesomeIcon icon={faFingerprint} />
                </span>
                <p css={tw`flex-1 min-w-[14rem] text-sm text-neutral-300`}>
                    Sign in with Face ID, Touch ID, Windows Hello or a security key, without typing your password. The
                    device keeps the key: the panel never sees your face or your finger.
                </p>
                <Button type={'button'} onClick={add} disabled={busy || !supported || list === null}>
                    <FontAwesomeIcon icon={busy ? faCircleNotch : faPlus} spin={busy} css={tw`mr-2`} />
                    <span>Add a passkey</span>
                </Button>
            </div>
            {!supported && (
                <p css={tw`mt-3 text-xs text-yellow-300`}>
                    This browser cannot use passkeys here. Use a recent browser, on the HTTPS address of the panel.
                </p>
            )}

            {list === null ? (
                <div css={tw`flex justify-center py-6 text-neutral-400`}>
                    <FontAwesomeIcon icon={faCircleNotch} spin />
                </div>
            ) : (
                list.length > 0 && (
                    <div css={tw`mt-5 rounded-xl overflow-hidden border border-white/[0.06]`}>
                        {list.map((p, i) => (
                            <div
                                key={p.id}
                                css={[
                                    tw`flex items-center gap-3 px-4 py-3 bg-white/[0.03]`,
                                    i > 0 && tw`border-t border-white/[0.06]`,
                                ]}
                            >
                                <FontAwesomeIcon icon={faFingerprint} css={tw`text-green-400`} />
                                <div css={tw`flex-1 min-w-0`}>
                                    <p css={tw`text-sm font-medium text-neutral-50 truncate`}>{p.name}</p>
                                    <p css={tw`text-xs text-neutral-400 truncate`}>
                                        {p.createdAt && (
                                            <span>
                                                <span>Added</span>{' '}
                                                {format(p.createdAt, 'd MMM yyyy', { locale: dateLocale() })}
                                            </span>
                                        )}
                                        {' · '}
                                        {p.lastUsedAt ? (
                                            <span>
                                                <span>Last used</span>{' '}
                                                {formatDistanceToNow(p.lastUsedAt, {
                                                    addSuffix: true,
                                                    locale: dateLocale(),
                                                })}
                                            </span>
                                        ) : (
                                            <span>Never used</span>
                                        )}
                                    </p>
                                </div>
                                <button
                                    type={'button'}
                                    title={'Remove'}
                                    aria-label={'Remove'}
                                    onClick={() => setRemoving(p)}
                                    disabled={busy}
                                    css={tw`flex items-center justify-center w-8 h-8 rounded-full text-neutral-400 hover:text-red-300 hover:bg-white/10`}
                                >
                                    <FontAwesomeIcon icon={faTrashAlt} css={tw`text-sm`} />
                                </button>
                            </div>
                        ))}
                    </div>
                )
            )}

            <Dialog.Confirm
                open={!!removing}
                onClose={() => setRemoving(null)}
                title={'Remove this passkey?'}
                confirm={'Remove'}
                onConfirmed={remove}
            >
                <span>The device will no longer open your account. Remove it from the device too.</span>
            </Dialog.Confirm>
        </ContentBox>
    );
};
