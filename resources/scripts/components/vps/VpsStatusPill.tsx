import React from 'react';
import tw from 'twin.macro';
import { VpsStatus } from '@/api/vps';

const COLORS: Record<VpsStatus, string> = {
    running: '#3ecf8e',
    stopped: '#707070',
    paused: '#f5a524',
    busy: '#f5a524',
    unknown: '#454545',
};

const LABELS: Record<VpsStatus, string> = {
    running: 'Running',
    stopped: 'Stopped',
    paused: 'Paused',
    busy: 'Busy',
    unknown: 'Unreachable',
};

// The state of a VPS as a small tinted pill, like the one of the game servers.
export default ({ status }: { status: VpsStatus }) => (
    <span
        css={tw`inline-flex items-center gap-1.5 flex-shrink-0 rounded-full px-2 py-0.5 text-xs font-medium`}
        style={{ background: `${COLORS[status]}1f`, color: status === 'running' ? '#6ee7b7' : '#d4d4d4' }}
    >
        <span
            css={tw`w-1.5 h-1.5 rounded-full`}
            className={status === 'busy' ? 'animate-pulse' : undefined}
            style={{ background: COLORS[status] }}
        />
        <span>{LABELS[status]}</span>
    </span>
);
