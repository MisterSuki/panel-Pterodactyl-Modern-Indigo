import React, { useEffect, useState } from 'react';
import classNames from 'classnames';
import { PlayIcon, RefreshIcon, StopIcon, XIcon } from '@heroicons/react/solid';
import Can from '@/components/elements/Can';
import { ServerContext } from '@/state/server';
import { PowerAction } from '@/components/server/console/ServerConsoleContainer';
import { Dialog } from '@/components/elements/dialog';

interface PowerButtonProps {
    className?: string;
}

type Tone = 'green' | 'primary' | 'red';

// How long a button keeps showing that it is waiting, if the server never answers.
const PENDING_TIMEOUT = 10000;

const tones: Record<Tone, { solid: string; soft: string }> = {
    green: {
        solid: 'bg-gradient-to-b from-green-500 to-green-600 border-green-400/40 text-white shadow-[0_8px_24px_-8px_rgba(34,197,94,0.7)] hover:brightness-110',
        soft: 'bg-green-500/10 border-green-500/30 text-green-200 hover:bg-green-500/20 hover:border-green-400/50',
    },
    primary: {
        solid: 'bg-gradient-to-b from-primary-500 to-primary-600 border-primary-400/40 text-white shadow-[0_8px_24px_-8px_rgba(99,102,241,0.7)] hover:brightness-110',
        soft: 'bg-primary-500/10 border-primary-500/30 text-primary-200 hover:bg-primary-500/20 hover:border-primary-400/50',
    },
    red: {
        solid: 'bg-gradient-to-b from-red-500 to-red-600 border-red-400/40 text-white shadow-[0_8px_24px_-8px_rgba(239,68,68,0.7)] hover:brightness-110',
        soft: 'bg-red-500/10 border-red-500/30 text-red-200 hover:bg-red-500/20 hover:border-red-400/50',
    },
};

interface PowerButtonProps2 {
    label: string;
    icon: React.ReactNode;
    tone: Tone;
    // The main action of the moment is filled, the others are lighter.
    emphasis?: boolean;
    disabled?: boolean;
    pending?: boolean;
    onClick: (e: React.MouseEvent<HTMLButtonElement, MouseEvent>) => void;
}

const PowerButton = ({ label, icon, tone, emphasis, disabled, pending, onClick }: PowerButtonProps2) => (
    <button
        type={'button'}
        disabled={disabled || pending}
        onClick={onClick}
        className={classNames(
            'flex-1 sm:flex-none inline-flex items-center justify-center gap-2 h-10 px-4 rounded-lg border text-sm font-semibold tracking-wide select-none',
            'transition-all duration-150 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-400 focus-visible:ring-offset-2 focus-visible:ring-offset-gray-900',
            'active:scale-95 disabled:active:scale-100',
            disabled && !pending
                ? 'bg-white/5 border-white/5 text-gray-500 cursor-not-allowed'
                : emphasis
                ? tones[tone].solid
                : tones[tone].soft,
            pending && 'cursor-wait opacity-80'
        )}
    >
        {pending ? (
            <span className={'w-4 h-4 rounded-full border-2 border-current border-t-transparent animate-spin'} />
        ) : (
            <span className={'w-4 h-4'}>{icon}</span>
        )}
        {label}
    </button>
);

export default ({ className }: PowerButtonProps) => {
    const [open, setOpen] = useState(false);
    const [pending, setPending] = useState<PowerAction | null>(null);
    const status = ServerContext.useStoreState((state) => state.status.value);
    const instance = ServerContext.useStoreState((state) => state.socket.instance);

    const killable = status === 'stopping';
    const onButtonClick = (
        action: PowerAction | 'kill-confirmed',
        e: React.MouseEvent<HTMLButtonElement, MouseEvent>
    ): void => {
        e.preventDefault();
        if (action === 'kill') {
            return setOpen(true);
        }

        if (instance) {
            setOpen(false);
            const sent = action === 'kill-confirmed' ? 'kill' : action;
            setPending(sent);
            instance.send('set state', sent);
        }
    };

    useEffect(() => {
        if (status === 'offline') {
            setOpen(false);
        }
        // The server answered, whatever the answer was.
        setPending(null);
    }, [status]);

    useEffect(() => {
        if (!pending) {
            return;
        }

        const timeout = window.setTimeout(() => setPending(null), PENDING_TIMEOUT);

        return () => window.clearTimeout(timeout);
    }, [pending]);

    return (
        <div className={className}>
            <Dialog.Confirm
                open={open}
                hideCloseIcon
                onClose={() => setOpen(false)}
                title={'Forcibly Stop Process'}
                confirm={'Continue'}
                onConfirmed={onButtonClick.bind(this, 'kill-confirmed')}
            >
                Forcibly stopping a server can lead to data corruption.
            </Dialog.Confirm>
            <Can action={'control.start'}>
                <PowerButton
                    label={'Start'}
                    tone={'green'}
                    emphasis={status === 'offline'}
                    icon={<PlayIcon />}
                    disabled={status !== 'offline'}
                    pending={pending === 'start'}
                    onClick={onButtonClick.bind(this, 'start')}
                />
            </Can>
            <Can action={'control.restart'}>
                <PowerButton
                    label={'Restart'}
                    tone={'primary'}
                    icon={<RefreshIcon />}
                    disabled={!status || status === 'offline'}
                    pending={pending === 'restart'}
                    onClick={onButtonClick.bind(this, 'restart')}
                />
            </Can>
            <Can action={'control.stop'}>
                <PowerButton
                    label={killable ? 'Kill' : 'Stop'}
                    tone={'red'}
                    emphasis={killable}
                    icon={killable ? <XIcon /> : <StopIcon />}
                    disabled={status === 'offline'}
                    pending={pending === 'stop'}
                    onClick={onButtonClick.bind(this, killable ? 'kill' : 'stop')}
                />
            </Can>
        </div>
    );
};
