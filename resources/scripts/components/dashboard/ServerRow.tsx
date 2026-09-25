import React, { memo, useState } from 'react';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import {
    faChevronRight,
    faEthernet,
    faHdd,
    faLock,
    faMemory,
    faMicrochip,
    faCalendarAlt,
    faServer,
    faSlidersH,
} from '@fortawesome/free-solid-svg-icons';
import UpgradeServerDialog from '@/components/dashboard/UpgradeServerDialog';
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
import Gauge from '@/components/dashboard/Gauge';
import { formatDistanceToNow } from 'date-fns';
import isEqual from 'react-fast-compare';

const slideIn = keyframes`
    from {
        opacity: 0;
        transform: translate3d(0, 10px, 0);
    }
`;

// The colour of a power state, as an "r, g, b" triple, used for the glow of the card and the dot on its icon.
export const TINTS: Record<ServerPowerState | 'unknown', string> = {
    running: '74, 222, 128',
    offline: '248, 113, 113',
    starting: '250, 204, 21',
    stopping: '250, 204, 21',
    unknown: '148, 163, 184',
};

// The card is lit from its top left corner in the colour of the power state, with a thin line along its top edge.
// The cards slide in one after the other (the delay comes from the list), and "backwards" keeps the hover lift
// working afterwards.
const ServerCard = styled(GreyRowBox)<{ $tint: string }>`
    ${tw`grid grid-cols-12 gap-x-4 gap-y-4 relative p-4 sm:p-5 rounded-2xl`};
    animation: ${slideIn} 360ms cubic-bezier(0.22, 1, 0.36, 1) backwards;
    background-image: radial-gradient(520px circle at 0% 0%, rgba(${(props) => props.$tint}, 0.1), transparent 60%),
        linear-gradient(180deg, hsl(224, 28%, 15%) 0%, hsl(224, 28%, 12%) 100%);

    &::before {
        content: '';
        ${tw`absolute left-0 right-0 top-0 h-px`};
        background: linear-gradient(90deg, rgba(${(props) => props.$tint}, 0.9) 0%, transparent 55%);
    }

    & .icon {
        ${tw`w-12 h-12 rounded-xl`};
    }

    &[aria-disabled='true'] {
        ${tw`cursor-not-allowed`};
        filter: saturate(0.6);
    }

    & .chevron {
        ${tw`opacity-30 transition-all duration-200`};
    }

    &:hover .chevron {
        ${tw`opacity-100`};
        transform: translateX(3px);
    }
`;

interface Props {
    server: Server;
    // The live usage of this server. The dashboard asks for all of its servers at once, every few seconds.
    stats?: ServerStats | null;
    // True once the usage was asked for and this server did not answer, so the card says so instead of waiting.
    unreachable?: boolean;
    // True when this server was bought in the shop and its resources can be changed (billed monthly).
    upgradeable?: boolean;
    // Set when the server was bought in the shop: its billing (custom monthly, or an offer's paid-until date).
    billing?: { custom: boolean; expiresAt: Date | null };
    className?: string;
    style?: React.CSSProperties;
}

const ServerRow = ({
    server,
    stats = null,
    unreachable = false,
    upgradeable = false,
    billing,
    className,
    style,
}: Props) => {
    const [upgradeOpen, setUpgradeOpen] = useState(false);
    const isSuspended = !!stats?.isSuspended || server.status === 'suspended';
    const state: ServerPowerState | 'unknown' = isSuspended || !stats ? 'unknown' : stats.status;
    const tint = isSuspended ? TINTS.offline : TINTS[state];

    const diskLimit = server.limits.disk !== 0 ? bytesToString(mbToBytes(server.limits.disk)) : 'Unlimited';
    const memoryLimit = server.limits.memory !== 0 ? bytesToString(mbToBytes(server.limits.memory)) : 'Unlimited';
    const cpuLimit = server.limits.cpu !== 0 ? server.limits.cpu + ' %' : 'Unlimited';

    const address = server.allocations.filter((alloc) => alloc.isDefault);

    // A suspended server cannot be opened: the card is only there to say so, and is not a link.
    const card: Record<string, unknown> = isSuspended
        ? { 'aria-disabled': true, title: 'This server is suspended.' }
        : { to: `/server/${server.id}` };

    return (
        <ServerCard
            as={isSuspended ? 'div' : (Link as React.ElementType)}
            {...card}
            $hoverable={!isSuspended}
            className={className}
            style={style}
            $tint={tint}
        >
            <div css={tw`flex items-center col-span-12 lg:col-span-5 min-w-0`}>
                <div css={tw`relative flex-shrink-0 mr-4`}>
                    <div className={'icon'}>
                        <FontAwesomeIcon icon={faServer} />
                    </div>
                    <span
                        css={tw`absolute -bottom-1 -right-1 w-3.5 h-3.5 rounded-full border-2 border-neutral-800`}
                        style={{ backgroundColor: `rgb(${tint})`, boxShadow: `0 0 8px rgba(${tint}, 0.8)` }}
                    />
                </div>
                <div css={tw`min-w-0`}>
                    <p
                        css={tw`flex items-center flex-wrap gap-x-3 gap-y-1 text-lg font-semibold text-neutral-50 break-words`}
                    >
                        {server.name}
                        {stats && !isSuspended && <StatusPill status={stats.status} />}
                    </p>
                    {isSuspended && !!server.suspension?.reason ? (
                        <p css={tw`text-sm text-red-300/80 break-words line-clamp-2`}>{server.suspension.reason}</p>
                    ) : (
                        !!server.description && (
                            <p css={tw`text-sm text-neutral-400 break-words line-clamp-2`}>{server.description}</p>
                        )
                    )}
                    {address.length > 0 && (
                        <div
                            css={tw`mt-2 inline-flex items-center gap-2 max-w-full rounded-lg border border-white/5 bg-black/30 px-2.5 py-1 text-xs text-neutral-300`}
                        >
                            <FontAwesomeIcon icon={faEthernet} css={tw`text-neutral-500 flex-shrink-0`} />
                            {address.map((allocation) => (
                                <HiddenAddress
                                    key={allocation.ip + allocation.port.toString()}
                                    host={allocation.alias || ip(allocation.ip)}
                                    port={allocation.port}
                                />
                            ))}
                        </div>
                    )}
                    {billing && (
                        <p css={tw`mt-2 flex items-center gap-1.5 text-xs text-neutral-400`}>
                            <FontAwesomeIcon icon={faCalendarAlt} css={tw`text-neutral-500`} />
                            {billing.custom ? (
                                <span>
                                    <span>Billed monthly · next invoice on</span>{' '}
                                    {new Intl.DateTimeFormat(undefined, {
                                        day: 'numeric',
                                        month: 'short',
                                        year: 'numeric',
                                    }).format(new Date(new Date().getFullYear(), new Date().getMonth() + 1, 1))}
                                </span>
                            ) : billing.expiresAt ? (
                                <span>
                                    <span>Paid until</span>{' '}
                                    {new Intl.DateTimeFormat(undefined, {
                                        day: 'numeric',
                                        month: 'short',
                                        year: 'numeric',
                                    }).format(billing.expiresAt)}
                                </span>
                            ) : (
                                <span>Billed monthly</span>
                            )}
                        </p>
                    )}
                </div>
            </div>
            <div css={tw`hidden sm:flex items-center gap-4 col-span-12 lg:col-span-7`}>
                <div css={tw`flex-1 min-w-0`}>
                    {!stats || isSuspended || server.isNodeUnderMaintenance ? (
                        isSuspended ? (
                            <div css={tw`text-center`}>
                                <span
                                    css={tw`bg-red-500/10 border border-red-500/30 rounded-full px-3 py-1 text-red-300 text-xs font-medium`}
                                >
                                    {server.status === 'suspended' ? 'Suspended' : 'Connection Error'}
                                </span>
                                {server.status === 'suspended' && (
                                    <p css={tw`text-xs text-neutral-500 mt-1.5`}>
                                        {!server.suspension?.until
                                            ? 'Until an administrator lifts it'
                                            : server.suspension.until.getTime() <= Date.now()
                                            ? 'Access is about to come back'
                                            : `Access back ${formatDistanceToNow(server.suspension.until, {
                                                  addSuffix: true,
                                              })}`}
                                    </p>
                                )}
                            </div>
                        ) : server.isNodeUnderMaintenance ? (
                            <div css={tw`text-center`}>
                                <span
                                    css={tw`bg-yellow-500/10 border border-yellow-500/30 rounded-full px-3 py-1 text-yellow-300 text-xs font-medium`}
                                >
                                    Under Maintenance
                                </span>
                            </div>
                        ) : server.isTransferring || server.status ? (
                            <div css={tw`text-center`}>
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
                        ) : unreachable ? (
                            <div css={tw`text-center`}>
                                <span
                                    css={tw`bg-neutral-500/10 border border-neutral-500/30 rounded-full px-3 py-1 text-neutral-300 text-xs font-medium`}
                                >
                                    Unreachable
                                </span>
                                <p css={tw`text-xs text-neutral-500 mt-1.5`}>
                                    The node does not answer, it is tried again every few seconds.
                                </p>
                            </div>
                        ) : (
                            <div css={tw`flex justify-center`}>
                                <Spinner size={'small'} />
                            </div>
                        )
                    ) : (
                        <div css={tw`grid grid-cols-3 gap-4`}>
                            <Gauge
                                icon={faMicrochip}
                                label={'CPU'}
                                color={'#818cf8'}
                                value={`${stats.cpuUsagePercent.toFixed(1)} %`}
                                limit={cpuLimit}
                                ratio={server.limits.cpu > 0 ? stats.cpuUsagePercent / server.limits.cpu : null}
                            />
                            <Gauge
                                icon={faMemory}
                                label={'Memory'}
                                color={'#22d3ee'}
                                value={bytesToString(stats.memoryUsageInBytes)}
                                limit={memoryLimit}
                                ratio={
                                    server.limits.memory > 0
                                        ? stats.memoryUsageInBytes / mbToBytes(server.limits.memory)
                                        : null
                                }
                            />
                            <Gauge
                                icon={faHdd}
                                label={'Disk'}
                                color={'#a78bfa'}
                                value={bytesToString(stats.diskUsageInBytes)}
                                limit={diskLimit}
                                ratio={
                                    server.limits.disk > 0
                                        ? stats.diskUsageInBytes / mbToBytes(server.limits.disk)
                                        : null
                                }
                            />
                        </div>
                    )}
                </div>
                {isSuspended ? (
                    <FontAwesomeIcon icon={faLock} css={tw`text-red-300/60 flex-shrink-0`} />
                ) : (
                    <FontAwesomeIcon
                        icon={faChevronRight}
                        className={'chevron'}
                        css={tw`text-neutral-400 flex-shrink-0`}
                    />
                )}
            </div>
            {upgradeable && !isSuspended && (
                <button
                    type={'button'}
                    title={'Resources'}
                    aria-label={'Change the resources'}
                    onClick={(e) => {
                        e.preventDefault();
                        e.stopPropagation();
                        setUpgradeOpen(true);
                    }}
                    css={tw`absolute top-3 right-3 z-10 w-8 h-8 rounded-lg border border-white/10 bg-black/30 text-neutral-300 hover:text-white hover:bg-white/10 flex items-center justify-center transition-colors duration-150`}
                >
                    <FontAwesomeIcon icon={faSlidersH} css={tw`text-xs`} />
                </button>
            )}
            {upgradeable && (
                <UpgradeServerDialog
                    server={server.id}
                    serverName={server.name}
                    open={upgradeOpen}
                    onClose={() => setUpgradeOpen(false)}
                />
            )}
        </ServerCard>
    );
};

export default memo(ServerRow, isEqual);
