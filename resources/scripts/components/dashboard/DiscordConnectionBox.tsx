import React, { useEffect, useState } from 'react';
import { Actions, State, useStoreActions, useStoreState } from 'easy-peasy';
import tw from 'twin.macro';
import { ApplicationStore } from '@/state';
import ContentBox from '@/components/elements/ContentBox';
import Button from '@/components/elements/Button';
import DiscordButton from '@/components/auth/DiscordButton';
import getDiscordMessage from '@/components/auth/discordMessages';
import unlinkDiscord from '@/api/account/unlinkDiscord';
import useFlash from '@/plugins/useFlash';

const FLASH_KEY = 'account:discord';

export default ({ className }: { className?: string }) => {
    const user = useStoreState((state: State<ApplicationStore>) => state.user.data!);
    const updateUserData = useStoreActions((actions: Actions<ApplicationStore>) => actions.user.updateUserData);
    const { addFlash, clearFlashes, clearAndAddHttpError } = useFlash();
    const [isUnlinking, setIsUnlinking] = useState(false);

    // Show the result of coming back from Discord (linked, or why it did not work).
    useEffect(() => {
        const notice = getDiscordMessage(new URLSearchParams(window.location.search).get('discord'));
        if (notice) {
            addFlash({
                key: FLASH_KEY,
                type: notice.type,
                title: notice.type === 'success' ? 'Success' : 'Error',
                message: notice.message,
            });
        }
    }, []);

    const onUnlink = () => {
        clearFlashes(FLASH_KEY);
        setIsUnlinking(true);

        unlinkDiscord()
            .then(() => updateUserData({ discordLinked: false, discordUsername: null }))
            .catch((error) => clearAndAddHttpError({ key: FLASH_KEY, error }))
            .then(() => setIsUnlinking(false));
    };

    return (
        <ContentBox title={'Discord'} showFlashes={FLASH_KEY} className={className}>
            {user.discordLinked ? (
                <React.Fragment>
                    <p css={tw`text-sm`}>
                        Your account is linked to Discord
                        {user.discordUsername ? (
                            <React.Fragment>
                                {' '}
                                as <strong css={tw`text-neutral-50`}>{user.discordUsername}</strong>
                            </React.Fragment>
                        ) : null}
                        . You can use it to sign in.
                    </p>
                    <div css={tw`mt-6`}>
                        <Button
                            type={'button'}
                            size={'xlarge'}
                            color={'red'}
                            isSecondary
                            isLoading={isUnlinking}
                            disabled={isUnlinking}
                            onClick={onUnlink}
                        >
                            Unlink Discord
                        </Button>
                    </div>
                    <p css={tw`mt-3 text-xs text-neutral-400`}>
                        If you created your account with Discord and never set a password, use &quot;Forgot password?&quot;
                        on the login page to set one before unlinking.
                    </p>
                </React.Fragment>
            ) : (
                <React.Fragment>
                    <p css={tw`text-sm`}>
                        Link your Discord account to sign in with one click instead of typing your password.
                    </p>
                    <div css={tw`mt-6`}>
                        <DiscordButton>Link Discord</DiscordButton>
                    </div>
                </React.Fragment>
            )}
        </ContentBox>
    );
};
