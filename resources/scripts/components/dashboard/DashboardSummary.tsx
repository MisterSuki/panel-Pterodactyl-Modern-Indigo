import React from 'react';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { IconDefinition } from '@fortawesome/fontawesome-svg-core';
import { faBolt, faMemory, faMicrochip, faServer } from '@fortawesome/free-solid-svg-icons';
import tw from 'twin.macro';
import { Server } from '@/api/server/getServer';
import { ServerStats } from '@/api/server/getServerResourceUsage';
import { bytesToString } from '@/lib/formatters';

const Tile = ({
    icon,
    label,
    value,
    detail,
    color,
}: {
    icon: IconDefinition;
    label: string;
    value: React.ReactNode;
    detail?: React.ReactNode;
    color: string;
}) => (
    <div
        css={tw`flex items-center gap-3 rounded-2xl border border-white/5 bg-neutral-800/70 px-4 py-3 shadow-card min-w-0`}
    >
        <span
            css={tw`flex-shrink-0 w-10 h-10 rounded-xl flex items-center justify-center`}
            style={{ backgroundColor: `${color}22`, color, boxShadow: `inset 0 0 0 1px ${color}33` }}
        >
            <FontAwesomeIcon icon={icon} />
        </span>
        <div css={tw`min-w-0`}>
            <p css={tw`text-2xs uppercase tracking-wider text-neutral-500`}>{label}</p>
            <p css={tw`text-lg font-semibold text-neutral-50 tabular-nums leading-tight truncate`}>{value}</p>
            {detail && <p css={tw`text-xs text-neutral-500 truncate`}>{detail}</p>}
        </div>
    </div>
);

interface Props {
    total: number;
    servers: Server[];
    usage?: Record<string, ServerStats>;
}

// What the servers of the page add up to: how many there are, how many are running, and what they use together.
export default ({ total, servers, usage }: Props) => {
    const stats = servers.map((server) => usage?.[server.uuid]).filter((s): s is ServerStats => !!s);
    const running = stats.filter((s) => s.status === 'running').length;
    const offline = stats.filter((s) => s.status === 'offline').length;
    const cpu = stats.reduce((sum, s) => sum + s.cpuUsagePercent, 0);
    const memory = stats.reduce((sum, s) => sum + s.memoryUsageInBytes, 0);

    return (
        <div css={tw`grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5`}>
            <Tile icon={faServer} label={'Servers'} value={total} color={'#818cf8'} />
            <Tile
                icon={faBolt}
                label={'Online'}
                value={usage ? running : '–'}
                detail={usage ? <>{offline} offline</> : undefined}
                color={'#4ade80'}
            />
            <Tile icon={faMicrochip} label={'CPU'} value={usage ? `${cpu.toFixed(1)} %` : '–'} color={'#a78bfa'} />
            <Tile icon={faMemory} label={'Memory'} value={usage ? bytesToString(memory) : '–'} color={'#22d3ee'} />
        </div>
    );
};
