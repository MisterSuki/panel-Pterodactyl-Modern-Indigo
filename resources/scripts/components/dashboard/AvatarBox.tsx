import React, { useRef, useState } from 'react';
import tw from 'twin.macro';
import { State, useStoreActions, useStoreState } from 'easy-peasy';
import { ApplicationStore } from '@/state';
import ContentBox from '@/components/elements/ContentBox';
import Button from '@/components/elements/Button';
import useFlash from '@/plugins/useFlash';
import { removeAvatar, uploadAvatar } from '@/api/account/avatar';

const FLASH_KEY = 'account:avatar';
const DEFAULT_AVATAR = '/assets/svgs/pterodactyl.svg';
const MAX_BYTES = 2 * 1024 * 1024;

// The profile picture: the logo of the panel until the person chooses another one.
export default ({ className }: { className?: string }) => {
    const avatar = useStoreState((state: State<ApplicationStore>) => state.user.data!.avatar);
    const updateUserData = useStoreActions((actions) => actions.user.updateUserData);
    const { clearFlashes, addFlash, clearAndAddHttpError } = useFlash();
    const [busy, setBusy] = useState(false);
    const input = useRef<HTMLInputElement>(null);
    const custom = !!avatar && !avatar.startsWith(DEFAULT_AVATAR);

    const choose = (event: React.ChangeEvent<HTMLInputElement>) => {
        const file = event.currentTarget.files?.[0];
        event.currentTarget.value = '';
        if (!file) {
            return;
        }
        clearFlashes(FLASH_KEY);
        if (!/^image\/(png|jpe?g|webp)$/.test(file.type)) {
            addFlash({ key: FLASH_KEY, type: 'error', message: 'The file must be a png, jpeg or webp picture.' });

            return;
        }
        if (file.size > MAX_BYTES) {
            addFlash({ key: FLASH_KEY, type: 'error', message: 'The picture is too big (2 MB at most).' });

            return;
        }

        setBusy(true);
        uploadAvatar(file)
            .then((url) => updateUserData({ avatar: url }))
            .catch((error) => clearAndAddHttpError({ key: FLASH_KEY, error }))
            .then(() => setBusy(false));
    };

    const remove = () => {
        clearFlashes(FLASH_KEY);
        setBusy(true);
        removeAvatar()
            .then((url) => updateUserData({ avatar: url }))
            .catch((error) => clearAndAddHttpError({ key: FLASH_KEY, error }))
            .then(() => setBusy(false));
    };

    return (
        <ContentBox title={'Profile picture'} showFlashes={FLASH_KEY} className={className}>
            <div css={tw`flex items-center gap-5`}>
                <img
                    src={avatar || DEFAULT_AVATAR}
                    alt={''}
                    css={tw`w-24 h-24 rounded-full object-cover flex-shrink-0 border border-white/10 bg-neutral-900 p-0.5`}
                />
                <div css={tw`min-w-0`}>
                    <div css={tw`flex flex-wrap gap-2`}>
                        <Button size={'small'} disabled={busy} onClick={() => input.current?.click()}>
                            Choose a picture
                        </Button>
                        {custom && (
                            <Button size={'small'} isSecondary color={'grey'} disabled={busy} onClick={remove}>
                                Remove
                            </Button>
                        )}
                    </div>
                    <p css={tw`text-xs text-neutral-400 mt-3`}>
                        A png, jpeg or webp picture, 2 MB at most. The team sees it when you write to the support.
                    </p>
                </div>
            </div>
            <input ref={input} type={'file'} accept={'image/png,image/jpeg,image/webp'} hidden onChange={choose} />
        </ContentBox>
    );
};
