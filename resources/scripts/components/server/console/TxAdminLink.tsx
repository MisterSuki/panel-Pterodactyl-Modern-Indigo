import React, { useEffect, useState } from 'react';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import {
    faCheck,
    faCopy,
    faExclamationTriangle,
    faExternalLinkAlt,
    faTachometerAlt,
} from '@fortawesome/free-solid-svg-icons';
import classNames from 'classnames';
import useFiveM from '@/components/server/console/useFiveM';
import { ServerContext } from '@/state/server';
import { SocketEvent } from '@/components/server/events';

// txAdmin prints a PIN in the console until its first account is made: "Use the PIN below to register:", then the PIN.
const PIN_INTRO = /PIN below to register/i;
const PIN = /\b(\d{4,6})\b/;
// eslint-disable-next-line no-control-regex
const strip = (line: string) => line.replace(/\u001b\[[0-9;]*m/g, '');

// The PIN txAdmin is waiting for, read in the console while the page is open; forgotten when the server stops.
const usePin = (enabled: boolean): string | null => {
    const instance = ServerContext.useStoreState((state) => state.socket.instance);
    const status = ServerContext.useStoreState((state) => state.status.value);
    const [pin, setPin] = useState<string | null>(null);

    useEffect(() => {
        if (status === 'offline') setPin(null);
    }, [status]);

    useEffect(() => {
        if (!enabled || !instance) return;
        let waiting = 0;
        const listener = (raw: string) => {
            const line = strip(raw);
            if (PIN_INTRO.test(line)) {
                waiting = 4;

                return;
            }
            if (waiting > 0) {
                waiting--;
                const found = PIN.exec(line);
                if (found) {
                    setPin(found[1]);
                    waiting = 0;
                }
            }
            // Once an administrator exists, txAdmin no longer asks for it.
            if (/admin account (created|registered)|master account/i.test(line)) setPin(null);
        };
        instance.addListener(SocketEvent.CONSOLE_OUTPUT, listener);

        return () => {
            instance.removeListener(SocketEvent.CONSOLE_OUTPUT, listener);
        };
    }, [enabled, instance]);

    return pin;
};

// The button that opens txAdmin, the web panel of a FiveM server, with the PIN of its first account when it asks for
// one. Nothing is shown for other servers.
export default ({ className }: { className?: string }) => {
    const { data } = useFiveM();
    const status = ServerContext.useStoreState((state) => state.status.value);
    const enabled = !!data?.txadmin.enabled && !!data.txadmin.url;
    const pin = usePin(enabled);
    const [copied, setCopied] = useState(false);

    if (!enabled || !data?.txadmin.url) {
        return null;
    }

    const unreachable = !data.txadmin.portAllocated;
    const up = status === 'running' || status === 'starting';
    const url = pin ? `${data.txadmin.url}/addMaster/pin` : data.txadmin.url;

    const copy = () => {
        if (!pin || !navigator.clipboard) return;
        navigator.clipboard.writeText(pin).then(() => {
            setCopied(true);
            window.setTimeout(() => setCopied(false), 1500);
        });
    };

    return (
        <span className={classNames('inline-flex items-center gap-1.5', className)}>
            <a
                href={url}
                target={'_blank'}
                rel={'noopener noreferrer'}
                title={
                    unreachable
                        ? `txAdmin listens on port ${data.txadmin.port}, which is not one of this server's allocations. Add it in the Network tab, or txAdmin cannot be reached from outside.`
                        : url
                }
                className={classNames(
                    'inline-flex items-center gap-2 h-8 rounded-full border pl-2.5 pr-3 text-xs font-semibold no-underline transition-all duration-150',
                    'shadow-[inset_0_1px_0_rgba(255,255,255,0.18)] hover:-translate-y-px',
                    unreachable
                        ? 'bg-yellow-500/10 border-yellow-500/30 text-yellow-200 hover:bg-yellow-500/20'
                        : 'bg-gradient-to-b from-white/[0.12] to-white/[0.04] border-white/15 text-gray-50 hover:from-white/[0.18] hover:to-white/[0.08]'
                )}
            >
                <span
                    className={'flex items-center justify-center w-5 h-5 rounded-full text-white'}
                    style={{ background: 'linear-gradient(135deg, #f97316, #ea580c)' }}
                >
                    <FontAwesomeIcon icon={faTachometerAlt} className={'w-2.5 h-2.5'} />
                </span>
                <span>txAdmin</span>
                <span
                    className={classNames('w-1.5 h-1.5 rounded-full', up ? 'bg-green-400' : 'bg-gray-500')}
                    title={up ? 'Running' : 'Offline'}
                />
                <FontAwesomeIcon
                    icon={unreachable ? faExclamationTriangle : faExternalLinkAlt}
                    className={'w-3 h-3 opacity-60'}
                />
            </a>
            {pin && (
                <button
                    type={'button'}
                    onClick={copy}
                    title={'Copy the PIN of txAdmin'}
                    className={
                        'inline-flex items-center gap-1.5 h-8 rounded-full border border-yellow-400/30 bg-yellow-400/10 px-3 text-xs font-semibold text-yellow-200 hover:bg-yellow-400/20 transition-colors duration-150'
                    }
                >
                    <span>PIN</span>
                    <span className={'font-mono tracking-widest text-white'}>{pin}</span>
                    <FontAwesomeIcon icon={copied ? faCheck : faCopy} className={'w-3 h-3'} />
                </button>
            )}
        </span>
    );
};
