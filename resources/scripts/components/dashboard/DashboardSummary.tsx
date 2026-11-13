import React from 'react';
import tw from 'twin.macro';
import { Server } from '@/api/server/getServer';
import { ServerStats } from '@/api/server/getServerResourceUsage';
import { bytesToString } from '@/lib/formatters';

const Cell = ({ label, value, detail }: { label: string; value: React.ReactNode; detail?: React.ReactNode }) => (
    <div css={tw`min-w-0 px-5 py-4 border-neutral-700`}>
        <p css={tw`text-sm text-neutral-400`}>{label}</p>
        <p css={tw`mt-1.5 text-2xl font-semibold text-neutral-50 tabular-nums leading-tight truncate tracking-tight`}>
            {value}
            {detail && <span css={tw`ml-2 text-sm font-normal text-neutral-500`}>{detail}</span>}
        </p>
    </div>
);

interface Props {
    total: number;
    servers: Server[];
    usage?: Record<string, ServerStats>;
}

// What the servers of the page add up to, in one row of figures: how many run, and what they use together.
export default ({ total, servers, usage }: Props) => {
    const stats = servers.map((server) => usage?.[server.uuid]).filter((s): s is ServerStats => !!s);
    const running = stats.filter((s) => s.status === 'running').length;
    const cpu = stats.reduce((sum, s) => sum + s.cpuUsagePercent, 0);
    const memory = stats.reduce((sum, s) => sum + s.memoryUsageInBytes, 0);
    const disk = stats.reduce((sum, s) => sum + s.diskUsageInBytes, 0);

    return (
        <div
            css={tw`grid grid-cols-2 lg:grid-cols-4 mb-8 rounded-2xl border border-neutral-700 bg-neutral-800 divide-x divide-neutral-700 overflow-hidden`}
        >
            <Cell label={'Running'} value={usage ? running : '–'} detail={`/ ${total}`} />
            <Cell label={'CPU'} value={usage ? `${cpu.toFixed(1)} %` : '–'} />
            <Cell label={'Memory'} value={usage ? bytesToString(memory) : '–'} />
            <Cell label={'Disk'} value={usage ? bytesToString(disk) : '–'} />
        </div>
    );
};
