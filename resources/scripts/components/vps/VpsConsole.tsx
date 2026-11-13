import React, { useEffect, useRef, useState } from 'react';
import tw from 'twin.macro';
import { Link, useParams } from 'react-router-dom';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faArrowLeft, faExpand, faKeyboard, faPlug, faRedo } from '@fortawesome/free-solid-svg-icons';
import PageContentBlock from '@/components/elements/PageContentBlock';
import { Button } from '@/components/elements/button/index';
import { httpErrorToHuman } from '@/api/http';
import { openVpsConsole } from '@/api/vps';

// noVNC, the VNC client of Proxmox itself, loaded from its published files when the console opens (only then).
const NOVNC = 'https://cdn.jsdelivr.net/npm/@novnc/novnc@1.4.0/core/rfb.js';

type State = 'connecting' | 'connected' | 'closed' | 'error';

// The keysym of a character, for typing a text into the machine (Latin-1 as it is, the rest as Unicode).
const keysym = (char: string): number => {
    const code = char.codePointAt(0) || 0;
    if (char === '\n') return 0xff0d;
    if (char === '\t') return 0xff09;

    return code < 0x100 ? code : 0x01000000 | code;
};

// The screen of a VPS in the browser (noVNC), through the console relay of the panel.
export default () => {
    const { uuid } = useParams<{ uuid: string }>();
    const screen = useRef<HTMLDivElement>(null);
    const rfb = useRef<any>(null);
    const [state, setState] = useState<State>('connecting');
    const [message, setMessage] = useState<string | null>(null);
    const [attempt, setAttempt] = useState(0);
    const [typing, setTyping] = useState(false);
    const [text, setText] = useState('');

    useEffect(() => {
        let cancelled = false;
        setState('connecting');
        setMessage(null);

        Promise.all([openVpsConsole(uuid), import(/* webpackIgnore: true */ NOVNC)])
            .then(([session, module]) => {
                if (cancelled || !screen.current) return;
                const RFB = module.default;
                const url = `${window.location.protocol === 'https:' ? 'wss' : 'ws'}://${window.location.host}${
                    session.path
                }`;
                const client = new RFB(screen.current, url, {
                    credentials: { password: session.password },
                    wsProtocols: ['binary'],
                });
                client.scaleViewport = true;
                client.resizeSession = false;
                client.background = '#000000';
                client.addEventListener('connect', () => {
                    setState('connected');
                    client.focus();
                });
                client.addEventListener('disconnect', (e: any) => {
                    setState(e.detail && e.detail.clean ? 'closed' : 'error');
                    if (!(e.detail && e.detail.clean)) setMessage('The connection to the console was lost.');
                });
                client.addEventListener('credentialsrequired', () =>
                    client.sendCredentials({ password: session.password })
                );
                client.addEventListener('securityfailure', () => {
                    setState('error');
                    setMessage('The console refused the connection.');
                });
                rfb.current = client;
            })
            .catch((error) => {
                if (cancelled) return;
                setState('error');
                setMessage(
                    error && error.response ? httpErrorToHuman(error) : 'The console could not be loaded. Try again.'
                );
            });

        return () => {
            cancelled = true;
            try {
                rfb.current?.disconnect();
            } catch {
                // Already closed.
            }
            rfb.current = null;
        };
    }, [uuid, attempt]);

    const typeText = () => {
        const client = rfb.current;
        if (!client || !text) return;
        for (const char of Array.from(text)) {
            client.sendKey(keysym(char));
        }
        setText('');
        setTyping(false);
        client.focus();
    };

    const fullscreen = () => {
        const element = screen.current?.parentElement;
        if (!element) return;
        if (document.fullscreenElement) {
            document.exitFullscreen().catch(() => undefined);
        } else {
            element.requestFullscreen().catch(() => undefined);
        }
    };

    return (
        <PageContentBlock title={'Console'}>
            <div css={tw`flex flex-wrap items-center justify-between gap-3 mb-4`}>
                <div css={tw`flex items-center gap-4 min-w-0`}>
                    <Link
                        to={`/vps/${uuid}`}
                        css={tw`inline-flex items-center gap-2 text-sm text-neutral-400 hover:text-neutral-100 no-underline`}
                    >
                        <FontAwesomeIcon icon={faArrowLeft} css={tw`text-xs`} />
                        <span>Back</span>
                    </Link>
                    <span css={tw`inline-flex items-center gap-2 text-sm text-neutral-300`}>
                        <span
                            css={tw`w-2 h-2 rounded-full`}
                            className={state === 'connecting' ? 'animate-pulse' : undefined}
                            style={{
                                background:
                                    state === 'connected' ? '#3ecf8e' : state === 'connecting' ? '#f5a524' : '#e5484d',
                            }}
                        />
                        <span>
                            {state === 'connected'
                                ? 'Connected'
                                : state === 'connecting'
                                ? 'Connecting...'
                                : state === 'closed'
                                ? 'Disconnected'
                                : 'Not connected'}
                        </span>
                    </span>
                </div>
                <div css={tw`flex flex-wrap gap-2`}>
                    <Button.Text
                        size={Button.Sizes.Small}
                        disabled={state !== 'connected'}
                        onClick={() => rfb.current?.sendCtrlAltDel()}
                    >
                        <FontAwesomeIcon icon={faKeyboard} css={tw`mr-2 text-xs`} />
                        <span>Ctrl+Alt+Del</span>
                    </Button.Text>
                    <Button.Text
                        size={Button.Sizes.Small}
                        disabled={state !== 'connected'}
                        onClick={() => setTyping((t) => !t)}
                    >
                        <span>Type a text</span>
                    </Button.Text>
                    <Button.Text size={Button.Sizes.Small} onClick={fullscreen}>
                        <FontAwesomeIcon icon={faExpand} css={tw`mr-2 text-xs`} />
                        <span>Full screen</span>
                    </Button.Text>
                    {state !== 'connecting' && state !== 'connected' && (
                        <Button size={Button.Sizes.Small} onClick={() => setAttempt((a) => a + 1)}>
                            <FontAwesomeIcon icon={faRedo} css={tw`mr-2 text-xs`} />
                            <span>Reconnect</span>
                        </Button>
                    )}
                </div>
            </div>

            {typing && (
                <div css={tw`flex gap-2 mb-4`}>
                    <input
                        type={'text'}
                        value={text}
                        autoFocus
                        onChange={(e) => setText(e.target.value)}
                        onKeyDown={(e) => e.key === 'Enter' && typeText()}
                        placeholder={'The text is typed into the machine, key by key'}
                        css={tw`flex-1 h-9 rounded-md border border-neutral-600 bg-neutral-900 px-3 text-sm text-neutral-100 placeholder-neutral-500 outline-none focus:border-neutral-500`}
                    />
                    <Button size={Button.Sizes.Small} onClick={typeText} disabled={!text}>
                        <span>Type</span>
                    </Button>
                </div>
            )}

            <div
                css={tw`relative overflow-hidden rounded-xl border border-neutral-700 bg-black`}
                style={{ height: 'calc(100vh - 220px)', minHeight: 360 }}
            >
                <div ref={screen} css={tw`absolute inset-0`} />
                {state !== 'connected' && (
                    <div css={tw`absolute inset-0 flex flex-col items-center justify-center gap-3 text-center px-6`}>
                        <FontAwesomeIcon
                            icon={faPlug}
                            css={tw`text-2xl text-neutral-500`}
                            className={state === 'connecting' ? 'animate-pulse' : undefined}
                        />
                        <p css={tw`text-sm text-neutral-300`}>
                            {state === 'connecting' ? 'Opening the console...' : message || 'The console is closed.'}
                        </p>
                    </div>
                )}
            </div>
        </PageContentBlock>
    );
};
