import React from 'react';
import tw from 'twin.macro';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { IconDefinition, faHdd, faMemory, faMicrochip, faPowerOff } from '@fortawesome/free-solid-svg-icons';
import { Server } from '@/api/server/getServer';
import { ServerStats } from '@/api/server/getServerResourceUsage';
import { bytesToString, mbToBytes } from '@/lib/formatters';
import { accentHex } from '@/lib/helpers';
import Sparkline from '@/components/server/console/Sparkline';

// The top of a trend line's scale: a little above its highest point, so the curve shows its ups and downs in the middle
// of the block instead of hugging the bottom of a limit it never reaches.
export const headroom = (points: number[]) => (points.length ? Math.max(...points, 0.0001) * 1.6 : null);

const Cell = ({
    icon,
    label,
    value,
    detail,
    children,
}: {
    icon: IconDefinition;
    label: string;
    value: React.ReactNode;
    detail?: React.ReactNode;
    children?: React.ReactNode;
}) => (
    <div css={tw`relative min-w-0 px-5 pt-4 pb-10 border-neutral-700 overflow-hidden`}>
        <p css={tw`relative flex items-center gap-2 text-xs font-medium text-neutral-400`}>
            <FontAwesomeIcon icon={icon} css={tw`text-neutral-500`} fixedWidth />
            <span>{label}</span>
        </p>
        <p
            css={tw`relative mt-2 text-2xl font-semibold text-neutral-50 tabular-nums leading-tight truncate tracking-tight`}
        >
            {value}
            {detail && <span css={tw`hidden sm:inline ml-1.5 text-sm font-normal text-neutral-500`}>{detail}</span>}
        </p>
        {children}
    </div>
);

interface Props {
    total: number;
    servers: Server[];
    usage?: Record<string, ServerStats>;
    // The CPU (in %) and the memory (in bytes) of the servers together, oldest first: one reading per refresh.
    history?: { cpu: number[]; memory: number[] };
}

// What the servers of the page add up to, in one row of figures: how many run, and what they use together, with the
// trend of the last minutes drawn behind the CPU and the memory.
export default ({ total, servers, usage, history }: Props) => {
    const stats = servers.map((server) => usage?.[server.uuid]).filter((s): s is ServerStats => !!s);
    const running = stats.filter((s) => s.status === 'running').length;
    const cpu = stats.reduce((sum, s) => sum + s.cpuUsagePercent, 0);
    const memory = stats.reduce((sum, s) => sum + s.memoryUsageInBytes, 0);
    const disk = stats.reduce((sum, s) => sum + s.diskUsageInBytes, 0);

    // The limits together, only when every server has one (an unlimited server makes the total meaningless).
    const limit = (key: 'cpu' | 'memory' | 'disk') =>
        servers.length > 0 && servers.every((s) => s.limits[key] > 0)
            ? servers.reduce((sum, s) => sum + s.limits[key], 0)
            : null;
    const cpuLimit = limit('cpu');
    const memoryLimit = limit('memory');
    const diskLimit = limit('disk');
    const share = total > 0 ? (running / total) * 100 : 0;

    return (
        <div
            className={'pd-neon'}
            css={tw`relative grid grid-cols-2 lg:grid-cols-4 mb-8 rounded-2xl border border-neutral-700 bg-neutral-800 divide-x divide-neutral-700 overflow-hidden`}
        >
            <Cell icon={faPowerOff} label={'Running'} value={usage ? running : '–'} detail={`/ ${total}`}>
                <div css={tw`absolute inset-x-5 bottom-4 h-1 rounded-full bg-neutral-700 overflow-hidden`}>
                    <div
                        css={tw`h-full rounded-full transition-all duration-700`}
                        style={{ width: `${usage ? share : 0}%`, background: '#3ecf8e' }}
                    />
                </div>
            </Cell>
            <Cell
                icon={faMicrochip}
                label={'CPU'}
                value={usage ? `${cpu.toFixed(1)} %` : '–'}
                detail={cpuLimit ? `/ ${cpuLimit} %` : undefined}
            >
                <Sparkline
                    points={history?.cpu || []}
                    max={headroom(history?.cpu || [])}
                    color={accentHex(400)}
                    className={'absolute inset-x-0 bottom-0 w-full h-9 opacity-70 pointer-events-none'}
                />
            </Cell>
            <Cell
                icon={faMemory}
                label={'Memory'}
                value={usage ? bytesToString(memory) : '–'}
                detail={memoryLimit ? `/ ${bytesToString(mbToBytes(memoryLimit))}` : undefined}
            >
                <Sparkline
                    points={history?.memory || []}
                    max={headroom(history?.memory || [])}
                    color={'#22d3ee'}
                    className={'absolute inset-x-0 bottom-0 w-full h-9 opacity-70 pointer-events-none'}
                />
            </Cell>
            <Cell
                icon={faHdd}
                label={'Disk'}
                value={usage ? bytesToString(disk) : '–'}
                detail={diskLimit ? `/ ${bytesToString(mbToBytes(diskLimit))}` : undefined}
            >
                <div css={tw`absolute inset-x-5 bottom-4 h-1 rounded-full bg-neutral-700 overflow-hidden`}>
                    <div
                        css={tw`h-full rounded-full transition-all duration-700`}
                        style={{
                            width: `${usage && diskLimit ? Math.min(100, (disk / mbToBytes(diskLimit)) * 100) : 0}%`,
                            background: '#a78bfa',
                        }}
                    />
                </div>
            </Cell>
        </div>
    );
};
