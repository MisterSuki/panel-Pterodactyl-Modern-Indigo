import React, { useEffect, useState } from 'react';
import tw from 'twin.macro';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faBell, faBellSlash, faCheck, faDownload, faMobileAlt, faPaperPlane } from '@fortawesome/free-solid-svg-icons';
import ContentBox from '@/components/elements/ContentBox';
import { Button } from '@/components/elements/button/index';
import { httpErrorToHuman } from '@/api/http';
import {
    canInstall,
    currentSubscription,
    disablePush,
    enablePush,
    install,
    isIos,
    onPwaChange,
    pushSupported,
    runningAsApp,
    testPush,
} from '@/lib/pwa';

// Account page: install the panel as an app on this device, and get its notifications here (a server crashed,
// restarted, a website is down...).
export default () => {
    const [installable, setInstallable] = useState(canInstall());
    const [subscribed, setSubscribed] = useState<boolean | null>(null);
    const [busy, setBusy] = useState(false);
    const [message, setMessage] = useState('');
    const [error, setError] = useState('');
    const supported = pushSupported();
    const app = runningAsApp();

    useEffect(() => onPwaChange(() => setInstallable(canInstall())), []);
    useEffect(() => {
        currentSubscription()
            .then((s) => setSubscribed(!!s))
            .catch(() => setSubscribed(false));
    }, []);

    const run = (action: () => Promise<void>, done: string) => {
        setBusy(true);
        setError('');
        setMessage('');
        action()
            .then(() => setMessage(done))
            .catch((e) =>
                setError(
                    e?.message === 'blocked'
                        ? 'The notifications are blocked for this site: allow them in the settings of the browser (the padlock next to the address).'
                        : httpErrorToHuman(e)
                )
            )
            .then(() => {
                setBusy(false);
                currentSubscription().then((s) => setSubscribed(!!s));
            });
    };

    return (
        <ContentBox title={'App and notifications'}>
            <div css={tw`flex items-start gap-3`}>
                <FontAwesomeIcon icon={faMobileAlt} css={tw`mt-1 text-neutral-400`} fixedWidth />
                <div css={tw`flex-1 min-w-0`}>
                    <p css={tw`text-sm font-semibold text-neutral-100`}>The panel as an app</p>
                    {app ? (
                        <p css={tw`text-xs text-green-300 mt-0.5`}>
                            <FontAwesomeIcon icon={faCheck} css={tw`mr-1`} />
                            <span>You are using the app.</span>
                        </p>
                    ) : installable ? (
                        <>
                            <p css={tw`text-xs text-neutral-400 mt-0.5`}>
                                Install it on this phone or computer: it opens like an app, with its own icon.
                            </p>
                            <Button size={Button.Sizes.Small} css={tw`mt-2`} onClick={() => install()}>
                                <FontAwesomeIcon icon={faDownload} css={tw`mr-2`} />
                                <span>Install the app</span>
                            </Button>
                        </>
                    ) : isIos() ? (
                        <p css={tw`text-xs text-neutral-400 mt-0.5`}>
                            On iPhone and iPad: in Safari, tap Share, then Add to Home Screen. The notifications work
                            from the app on the home screen.
                        </p>
                    ) : (
                        <p css={tw`text-xs text-neutral-400 mt-0.5`}>
                            In Chrome or Edge: menu, then Install the app (or the icon at the end of the address bar).
                            On Android: menu, then Add to home screen.
                        </p>
                    )}
                </div>
            </div>

            <div css={tw`mt-5 pt-4 border-t border-neutral-700 flex items-start gap-3`}>
                <FontAwesomeIcon icon={subscribed ? faBell : faBellSlash} css={tw`mt-1 text-neutral-400`} fixedWidth />
                <div css={tw`flex-1 min-w-0`}>
                    <p css={tw`text-sm font-semibold text-neutral-100`}>Notifications on this device</p>
                    <p css={tw`text-xs text-neutral-400 mt-0.5`}>
                        A server crashes or restarts, a disk is almost full, a website is down: you are told here, even
                        when the panel is closed. The same messages as the bell.
                    </p>
                    {!supported ? (
                        <p css={tw`text-xs text-yellow-300 mt-2`}>
                            {isIos()
                                ? 'Add the panel to the home screen first, then open it from there to turn them on.'
                                : 'This browser cannot get notifications (or the panel is not in https).'}
                        </p>
                    ) : (
                        <div css={tw`mt-2 flex flex-wrap gap-2`}>
                            {subscribed ? (
                                <>
                                    <Button.Text
                                        size={Button.Sizes.Small}
                                        disabled={busy}
                                        onClick={() => run(testPush, 'A test notification was sent.')}
                                    >
                                        <FontAwesomeIcon icon={faPaperPlane} css={tw`mr-2`} />
                                        <span>Send a test</span>
                                    </Button.Text>
                                    <Button.Text
                                        size={Button.Sizes.Small}
                                        disabled={busy}
                                        onClick={() => run(disablePush, 'The notifications are off on this device.')}
                                    >
                                        <FontAwesomeIcon icon={faBellSlash} css={tw`mr-2`} />
                                        <span>Turn them off</span>
                                    </Button.Text>
                                </>
                            ) : (
                                <Button
                                    size={Button.Sizes.Small}
                                    disabled={busy || subscribed === null}
                                    onClick={() => run(enablePush, 'The notifications are on on this device.')}
                                >
                                    <FontAwesomeIcon icon={faBell} css={tw`mr-2`} />
                                    <span>Turn the notifications on</span>
                                </Button>
                            )}
                        </div>
                    )}
                    {message && <p css={tw`mt-2 text-xs text-green-300`}>{message}</p>}
                    {error && <p css={tw`mt-2 text-xs text-red-400`}>{error}</p>}
                </div>
            </div>
        </ContentBox>
    );
};
