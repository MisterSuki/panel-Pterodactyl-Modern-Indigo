import React, { useEffect, useRef, useState } from 'react';
import tw from 'twin.macro';
import { Link } from 'react-router-dom';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faExpand, faExternalLinkAlt, faKeyboard, faPlug, faPowerOff, faRedo } from '@fortawesome/free-solid-svg-icons';
import { Button } from '@/components/elements/button/index';
import { httpErrorToHuman } from '@/api/http';
import { openVpsConsole } from '@/api/vps';

// noVNC, the VNC client of Proxmox itself, loaded from its published files when the console opens (only then).
const NOVNC = 'https://cdn.jsdelivr.net/npm/@novnc/novnc@1.4.0/core/rfb.js';

type State = 'off' | 'connecting' | 'connected' | 'closed' | 'error';

// "fit": the whole screen of the machine in the frame; a number: its size times that, with scroll bars when larger.
type Zoom = 'fit' | number;

const ZOOMS: Zoom[] = ['fit', 1, 1.25, 1.5, 2];

const ZOOM_KEY = 'pd:vps-zoom';

const savedZoom = (): Zoom => {
    try {
        const value = window.localStorage.getItem(ZOOM_KEY);
        const number = value ? parseFloat(value) : NaN;

        return ZOOMS.includes(number) ? number : 'fit';
    } catch {
        return 'fit';
    }
};

// The keysym of a character, for typing a text into the machine (Latin-1 as it is, the rest as Unicode).
const keysym = (char: string): number => {
    const code = char.codePointAt(0) || 0;
    if (char === '\n') return 0xff0d;
    if (char === '\t') return 0xff09;

    return code < 0x100 ? code : 0x01000000 | code;
};

const ToolButton = ({
    label,
    disabled,
    onClick,
    children,
}: {
    label: string;
    disabled?: boolean;
    onClick: () => void;
    children: React.ReactNode;
}) => (
    <button
        type={'button'}
        title={label}
        aria-label={label}
        disabled={disabled}
        onClick={onClick}
        css={tw`inline-flex items-center gap-1.5 h-7 px-2 rounded-md text-xs text-neutral-300 hover:text-neutral-50 hover:bg-neutral-700 disabled:opacity-40 disabled:cursor-not-allowed`}
    >
        {children}
    </button>
);

interface Props {
    uuid: string;
    // False while the machine is off: nothing is opened, the screen says so.
    active: boolean;
    // Height of the screen (any CSS length).
    height: string;
    // On the page of the VPS: the keyboard is only taken once the screen is clicked, and a link opens it large.
    embedded?: boolean;
}

// The screen of a VPS in the browser (noVNC), through the console relay of the panel.
export default ({ uuid, active, height, embedded }: Props) => {
    const screen = useRef<HTMLDivElement>(null);
    const frame = useRef<HTMLDivElement>(null);
    const rfb = useRef<any>(null);
    const failures = useRef(0);
    const [state, setState] = useState<State>(active ? 'connecting' : 'off');
    const [message, setMessage] = useState<string | null>(null);
    const [attempt, setAttempt] = useState(0);
    const [typing, setTyping] = useState(false);
    const [text, setText] = useState('');
    const [zoom, setZoom] = useState<Zoom>(savedZoom);
    const zoomRef = useRef<Zoom>(zoom);

    // noVNC only knows "fit" or the real size: a chosen zoom is applied each time it sizes the screen again.
    const applyZoom = (client: any) => {
        if (!client) return;
        client.scaleViewport = zoomRef.current === 'fit';
        if (zoomRef.current !== 'fit' && client._display) {
            client._display.scale = zoomRef.current;
        }
    };

    useEffect(() => {
        zoomRef.current = zoom;
        try {
            window.localStorage.setItem(ZOOM_KEY, String(zoom));
        } catch {
            // Not kept: the default comes back next time.
        }
        applyZoom(rfb.current);
    }, [zoom]);

    useEffect(() => {
        if (!active) {
            setState('off');
            setMessage(null);

            return;
        }

        let cancelled = false;
        let retry: number | undefined;
        setState('connecting');
        setMessage(null);

        // Tries again by itself a few times (the machine restarting, a short cut of the network), then waits for a click.
        const again = (seconds: number) => {
            if (failures.current >= 3) return;
            failures.current += 1;
            retry = window.setTimeout(() => setAttempt((a) => a + 1), seconds * 1000);
        };

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
                const updateScale = client._updateScale?.bind(client);
                if (updateScale) {
                    client._updateScale = () => {
                        if (zoomRef.current === 'fit' || !client._display) return updateScale();
                        client._display.scale = zoomRef.current;
                    };
                }
                applyZoom(client);
                client.resizeSession = false;
                client.background = '#000000';
                // A small dot where the mouse is when the machine draws no pointer (a text console has none).
                client.showDotCursor = true;
                client.addEventListener('connect', () => {
                    failures.current = 0;
                    setState('connected');
                    if (!embedded) client.focus();
                });
                client.addEventListener('disconnect', (e: any) => {
                    if (cancelled) return;
                    const clean = !!(e.detail && e.detail.clean);
                    setState(clean ? 'closed' : 'error');
                    if (!clean) {
                        setMessage('The connection to the console was lost.');
                        again(4);
                    }
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
                if (error && error.response && error.response.status === 429) {
                    // Too many tries in a short time: try again by itself once the limit lets it.
                    const seconds = parseInt(String(error.response.headers?.['retry-after'] || ''), 10);
                    setMessage('Too many tries in a short time: the console opens again by itself in a few seconds.');
                    retry = window.setTimeout(
                        () => setAttempt((a) => a + 1),
                        Math.min(60, Math.max(3, isNaN(seconds) ? 10 : seconds)) * 1000
                    );

                    return;
                }
                setMessage(
                    error && error.response ? httpErrorToHuman(error) : 'The console could not be loaded. Try again.'
                );
            });

        return () => {
            cancelled = true;
            window.clearTimeout(retry);
            try {
                rfb.current?.disconnect();
            } catch {
                // Already closed.
            }
            rfb.current = null;
        };
    }, [uuid, active, attempt]);

    const reconnect = () => {
        failures.current = 0;
        setAttempt((a) => a + 1);
    };

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
        const element = frame.current;
        if (!element) return;
        if (document.fullscreenElement) {
            document.exitFullscreen().catch(() => undefined);
        } else {
            element.requestFullscreen().catch(() => undefined);
        }
    };

    const connected = state === 'connected';

    return (
        <div
            ref={frame}
            className={'pd-neon'}
            css={tw`relative flex flex-col overflow-hidden rounded-xl bg-neutral-900`}
        >
            <div
                css={tw`relative flex flex-wrap items-center justify-between gap-2 px-3 py-2 border-b border-neutral-700 bg-neutral-800`}
            >
                <span css={tw`inline-flex items-center gap-2 text-xs text-neutral-300 min-w-0`}>
                    <span
                        css={tw`w-2 h-2 rounded-full flex-shrink-0`}
                        className={state === 'connecting' ? 'animate-pulse' : undefined}
                        style={{
                            background:
                                state === 'connected'
                                    ? '#3ecf8e'
                                    : state === 'connecting'
                                    ? '#f5a524'
                                    : state === 'off'
                                    ? '#737373'
                                    : '#e5484d',
                        }}
                    />
                    <span css={tw`font-medium text-neutral-200`}>Console</span>
                    <span css={tw`text-neutral-500 truncate`}>
                        {state === 'connected'
                            ? 'Connected'
                            : state === 'connecting'
                            ? 'Connecting...'
                            : state === 'off'
                            ? 'The machine is off'
                            : state === 'closed'
                            ? 'Disconnected'
                            : 'Not connected'}
                    </span>
                </span>
                <div css={tw`flex flex-wrap items-center gap-1`}>
                    <ToolButton
                        label={'Ctrl+Alt+Del'}
                        disabled={!connected}
                        onClick={() => rfb.current?.sendCtrlAltDel()}
                    >
                        <FontAwesomeIcon icon={faKeyboard} css={tw`text-xs`} />
                        <span css={tw`hidden sm:inline`}>Ctrl+Alt+Del</span>
                    </ToolButton>
                    <ToolButton label={'Type a text'} disabled={!connected} onClick={() => setTyping((t) => !t)}>
                        <span>Type a text</span>
                    </ToolButton>
                    {(state === 'closed' || state === 'error') && (
                        <ToolButton label={'Reconnect'} onClick={reconnect}>
                            <FontAwesomeIcon icon={faRedo} css={tw`text-xs`} />
                            <span css={tw`hidden sm:inline`}>Reconnect</span>
                        </ToolButton>
                    )}
                    <select
                        value={String(zoom)}
                        onChange={(e) => setZoom(e.target.value === 'fit' ? 'fit' : parseFloat(e.target.value))}
                        title={'Zoom'}
                        aria-label={'Zoom'}
                        css={tw`h-7 rounded-md border border-neutral-600 bg-neutral-900 pl-2 pr-6 text-xs text-neutral-200 outline-none cursor-pointer hover:border-neutral-500`}
                    >
                        {ZOOMS.map((value) => (
                            <option key={String(value)} value={String(value)}>
                                {value === 'fit' ? 'Fit to the frame' : `${Math.round(value * 100)} %`}
                            </option>
                        ))}
                    </select>
                    <ToolButton label={'Full screen'} disabled={state === 'off'} onClick={fullscreen}>
                        <FontAwesomeIcon icon={faExpand} css={tw`text-xs`} />
                    </ToolButton>
                    {embedded && active && (
                        <Link
                            to={`/vps/${uuid}/console`}
                            title={'Open large'}
                            aria-label={'Open large'}
                            css={tw`inline-flex items-center justify-center h-7 px-2 rounded-md text-neutral-400 hover:text-neutral-50 hover:bg-neutral-700 no-underline`}
                        >
                            <FontAwesomeIcon icon={faExternalLinkAlt} css={tw`text-xs`} />
                        </Link>
                    )}
                </div>
            </div>

            {typing && (
                <div css={tw`flex gap-2 px-3 py-2 border-b border-neutral-700 bg-neutral-800`}>
                    <input
                        type={'text'}
                        value={text}
                        autoFocus
                        onChange={(e) => setText(e.target.value)}
                        onKeyDown={(e) => e.key === 'Enter' && typeText()}
                        placeholder={'The text is typed into the machine, key by key'}
                        css={tw`flex-1 min-w-0 h-8 rounded-md border border-neutral-600 bg-neutral-900 px-3 text-sm text-neutral-100 placeholder-neutral-500 outline-none focus:border-neutral-500`}
                    />
                    <Button size={Button.Sizes.Small} onClick={typeText} disabled={!text}>
                        <span>Type</span>
                    </Button>
                </div>
            )}

            <div css={tw`relative bg-black`} style={{ height, minHeight: 280 }}>
                <div ref={screen} css={tw`absolute inset-0`} />
                {!connected && (
                    <div css={tw`absolute inset-0 flex flex-col items-center justify-center gap-3 text-center px-6`}>
                        <FontAwesomeIcon
                            icon={state === 'off' ? faPowerOff : faPlug}
                            css={tw`text-2xl text-neutral-600`}
                            className={state === 'connecting' ? 'animate-pulse' : undefined}
                        />
                        <p css={tw`text-sm text-neutral-400 max-w-sm`}>
                            {state === 'off'
                                ? 'Start the machine to see its screen.'
                                : state === 'connecting'
                                ? 'Opening the console...'
                                : message || 'The console is closed.'}
                        </p>
                        {(state === 'closed' || state === 'error') && (
                            <Button size={Button.Sizes.Small} onClick={reconnect}>
                                <FontAwesomeIcon icon={faRedo} css={tw`mr-2 text-xs`} />
                                <span>Reconnect</span>
                            </Button>
                        )}
                    </div>
                )}
            </div>
        </div>
    );
};
