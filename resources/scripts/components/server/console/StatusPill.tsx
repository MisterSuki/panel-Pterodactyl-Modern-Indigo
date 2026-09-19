import React from 'react';
import classNames from 'classnames';
import { ServerPowerState } from '@/api/server/getServerResourceUsage';

const labels: Record<ServerPowerState, string> = {
    offline: 'Offline',
    starting: 'Starting',
    running: 'Running',
    stopping: 'Stopping',
};

// A small "Running / Offline" badge. Until the first state arrives it reads as connecting.
export default ({ status, className }: { status: ServerPowerState | null; className?: string }) => (
    <span
        className={classNames(
            'inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-xs font-medium select-none',
            {
                'bg-green-500/10 border-green-500/30 text-green-300': status === 'running',
                'bg-red-500/10 border-red-500/30 text-red-300': status === 'offline',
                'bg-yellow-500/10 border-yellow-500/30 text-yellow-300': status === 'starting' || status === 'stopping',
                'bg-gray-500/10 border-gray-500/30 text-gray-300': !status,
            },
            className
        )}
    >
        <span
            className={classNames('w-1.5 h-1.5 rounded-full', {
                'bg-green-400': status === 'running',
                'bg-red-400': status === 'offline',
                'bg-yellow-400 animate-pulse': status === 'starting' || status === 'stopping',
                'bg-gray-400 animate-pulse': !status,
            })}
        />
        {status ? labels[status] : 'Connecting'}
    </span>
);
