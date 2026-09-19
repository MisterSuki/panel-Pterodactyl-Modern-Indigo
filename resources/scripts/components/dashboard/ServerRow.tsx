import React, { memo } from 'react';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faEthernet, faHdd, faMemory, faMicrochip, faServer } from '@fortawesome/free-solid-svg-icons';
import { Link } from 'react-router-dom';
import { Server } from '@/api/server/getServer';
import { ServerPowerState, ServerStats } from '@/api/server/getServerResourceUsage';
import { bytesToString, ip, mbToBytes } from '@/lib/formatters';
import tw from 'twin.macro';
import GreyRowBox from '@/components/elements/GreyRowBox';
import Spinner from '@/components/elements/Spinner';
import styled, { keyframes } from 'styled-components/macro';
import StatusPill from '@/components/server/console/StatusPill';
import HiddenAddress from '@/components/elements/HiddenAddress';
import isEqual from 'react-fast-compare';

// Determines if the current value is in an alarm threshold so we can show it in red rather
// than the more faded default style.
const isAlarmState = (current: number, limit: number): boolean => limit > 0 && current / (limit * 1024 * 1024) >= 0.9;

const Icon = memo(
    styled(FontAwesomeIcon)<{ $alarm: boolean }>`
        ${(props) => (props.$alarm ? tw`text-red-400` : tw`text-neutral-500`)};
    `,
    isEqual
);

const IconDescription = styled.p<{ $alarm: boolean }>`
    ${tw`text-sm ml-2`};
    ${(props) => (props.$alarm ? tw`text-white` : tw`text-neutral-400`)};
`;

const slideIn = keyframes`
    from {
        opacity: 0;
        transform: translate3d(0, 10px, 0);
    }
`;

// A thin accent on the left edge shows the power state at a glance. The cards slide in one after the
// other (the delay comes from the list), and "backwards" keeps the hover lift working afterwards.
const StatusIndicatorBox = styled(GreyRowBox)<{ $status: ServerPowerState | undefined }>`
    ${tw`grid grid-cols-12 gap-4 relative`};
    animation: ${slideIn} 360ms cubic-bezier(0.22, 1, 0.36, 1) backwards;

    &::before {
        content: '';
        ${tw`absolute left-0 top-3 bottom-3 w-1 rounded-r-full transition-colors duration-300`};
        ${({ $status }) =>
            !$status
                ? tw`bg-neutral-600`
                : $status === 'offline'
                ? tw`bg-red-500`
                : $status === 'running'
                ? tw`bg-green-400`
                : tw`bg-yellow-400`};
    }
`;

// Thin usage bar under a figure. Servers without a limit get an empty track so the columns line up.
const Meter = ({ ratio, alarm }: { ratio: number | null; alarm: boolean }) => (
    <div css={tw`h-1 mt-1.5 mx-auto w-full max-w-[7rem] rounded-full bg-white/5 overflow-hidden`}>
        {ratio !== null && (
            <div
                css={[
                    tw`h-full rounded-full transition-all duration-700 ease-out`,
                    alarm ? tw`bg-red-400` : tw`bg-gradient-brand`,
                ]}
                style={{ width: `${Math.max(2, Math.min(100, ratio * 100))}%` }}
            />
        )}
    </div>
);

interface Props {
    server: Server;
    // The live usage of this server. The dashboard asks for all of its servers at once, every few seconds.
    stats?: ServerStats | null;
    className?: string;
    style?: React.CSSProperties;
}

const ServerRow = ({ server, stats = null, className, style }: Props) => {
    const isSuspended = !!stats?.isSuspended || server.status === 'suspended';

    const alarms = { cpu: false, memory: false, disk: false };
    if (stats) {
        alarms.cpu = server.limits.cpu === 0 ? false : stats.cpuUsagePercent >= server.limits.cpu * 0.9;
        alarms.memory = isAlarmState(stats.memoryUsageInBytes, server.limits.memory);
        alarms.disk = server.limits.disk === 0 ? false : isAlarmState(stats.diskUsageInBytes, server.limits.disk);
    }

    const diskLimit = server.limits.disk !== 0 ? bytesToString(mbToBytes(server.limits.disk)) : 'Unlimited';
    const memoryLimit = server.limits.memory !== 0 ? bytesToString(mbToBytes(server.limits.memory)) : 'Unlimited';
    const cpuLimit = server.limits.cpu !== 0 ? server.limits.cpu + ' %' : 'Unlimited';

    return (
        <StatusIndicatorBox
            as={Link}
            to={`/server/${server.id}`}
            className={className}
            style={style}
            $status={isSuspended ? undefined : stats?.status}
        >
            <div css={tw`flex items-center col-span-12 sm:col-span-5 lg:col-span-6`}>
                <div className={'icon mr-4'}>
                    <FontAwesomeIcon icon={faServer} />
                </div>
                <div>
                    <p
                        css={tw`flex items-center flex-wrap gap-x-3 gap-y-1 text-lg font-semibold text-neutral-50 break-words`}
                    >
                        {server.name}
                        {stats && !isSuspended && <StatusPill status={stats.status} />}
                    </p>
                    {!!server.description && (
                        <p css={tw`text-sm text-neutral-400 break-words line-clamp-2`}>{server.description}</p>
                    )}
                </div>
            </div>
            <div css={tw`flex-1 ml-4 lg:block lg:col-span-2 hidden`}>
                <div css={tw`flex justify-center`}>
                    <FontAwesomeIcon icon={faEthernet} css={tw`text-neutral-500`} />
                    <p css={tw`text-sm text-neutral-400 ml-2`}>
                        {server.allocations
                            .filter((alloc) => alloc.isDefault)
                            .map((allocation) => (
                                <React.Fragment key={allocation.ip + allocation.port.toString()}>
                                    <HiddenAddress
                                        host={allocation.alias || ip(allocation.ip)}
                                        port={allocation.port}
                                    />
                                </React.Fragment>
                            ))}
                    </p>
                </div>
            </div>
            <div css={tw`hidden col-span-7 lg:col-span-4 sm:flex items-baseline justify-center`}>
                {!stats || isSuspended || server.isNodeUnderMaintenance ? (
                    isSuspended ? (
                        <div css={tw`flex-1 text-center`}>
                            <span
                                css={tw`bg-red-500/10 border border-red-500/30 rounded-full px-3 py-1 text-red-300 text-xs font-medium`}
                            >
                                {server.status === 'suspended' ? 'Suspended' : 'Connection Error'}
                            </span>
                        </div>
                    ) : server.isNodeUnderMaintenance ? (
                        <div css={tw`flex-1 text-center`}>
                            <span
                                css={tw`bg-yellow-500/10 border border-yellow-500/30 rounded-full px-3 py-1 text-yellow-300 text-xs font-medium`}
                            >
                                Under Maintenance
                            </span>
                        </div>
                    ) : server.isTransferring || server.status ? (
                        <div css={tw`flex-1 text-center`}>
                            <span
                                css={tw`bg-neutral-500/10 border border-neutral-500/30 rounded-full px-3 py-1 text-neutral-300 text-xs font-medium`}
                            >
                                {server.isTransferring
                                    ? 'Transferring'
                                    : server.status === 'installing'
                                    ? 'Installing'
                                    : server.status === 'restoring_backup'
                                    ? 'Restoring Backup'
                                    : 'Unavailable'}
                            </span>
                        </div>
                    ) : (
                        <Spinner size={'small'} />
                    )
                ) : (
                    <React.Fragment>
                        <div css={tw`flex-1 ml-4 sm:block hidden`}>
                            <div css={tw`flex justify-center`}>
                                <Icon icon={faMicrochip} $alarm={alarms.cpu} />
                                <IconDescription $alarm={alarms.cpu}>
                                    {stats.cpuUsagePercent.toFixed(2)} %
                                </IconDescription>
                            </div>
                            <Meter
                                ratio={server.limits.cpu > 0 ? stats.cpuUsagePercent / server.limits.cpu : null}
                                alarm={alarms.cpu}
                            />
                            <p css={tw`text-xs text-neutral-500 text-center mt-1`}>of {cpuLimit}</p>
                        </div>
                        <div css={tw`flex-1 ml-4 sm:block hidden`}>
                            <div css={tw`flex justify-center`}>
                                <Icon icon={faMemory} $alarm={alarms.memory} />
                                <IconDescription $alarm={alarms.memory}>
                                    {bytesToString(stats.memoryUsageInBytes)}
                                </IconDescription>
                            </div>
                            <Meter
                                ratio={
                                    server.limits.memory > 0
                                        ? stats.memoryUsageInBytes / mbToBytes(server.limits.memory)
                                        : null
                                }
                                alarm={alarms.memory}
                            />
                            <p css={tw`text-xs text-neutral-500 text-center mt-1`}>of {memoryLimit}</p>
                        </div>
                        <div css={tw`flex-1 ml-4 sm:block hidden`}>
                            <div css={tw`flex justify-center`}>
                                <Icon icon={faHdd} $alarm={alarms.disk} />
                                <IconDescription $alarm={alarms.disk}>
                                    {bytesToString(stats.diskUsageInBytes)}
                                </IconDescription>
                            </div>
                            <Meter
                                ratio={
                                    server.limits.disk > 0
                                        ? stats.diskUsageInBytes / mbToBytes(server.limits.disk)
                                        : null
                                }
                                alarm={alarms.disk}
                            />
                            <p css={tw`text-xs text-neutral-500 text-center mt-1`}>of {diskLimit}</p>
                        </div>
                    </React.Fragment>
                )}
            </div>
        </StatusIndicatorBox>
    );
};

export default memo(ServerRow, isEqual);
