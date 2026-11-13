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
            'inline-flex items-center gap-1.5 rounded-full border border-gray-600 bg-gray-900 px-2.5 py-0.5 text-xs font-medium text-gray-100 select-none',
            className
        )}
    >
        <span
            className={classNames('w-1.5 h-1.5 rounded-full', {
                'bg-green-400': status === 'running',
                'bg-gray-500': status === 'offline',
                'bg-yellow-400 animate-pulse': status === 'starting' || status === 'stopping',
                'bg-gray-400 animate-pulse': !status,
            })}
        />
        {status ? labels[status] : 'Connecting'}
    </span>
);
