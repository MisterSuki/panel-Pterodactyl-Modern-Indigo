import React, { memo, useEffect, useState } from 'react';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faCalendarAlt, faClock, faLock, faSlidersH, faUsers } from '@fortawesome/free-solid-svg-icons';
import { getPlayersLive } from '@/api/server/insights';
import serverGame from '@/components/dashboard/serverGame';
import UpgradeServerDialog from '@/components/dashboard/UpgradeServerDialog';
import { Link } from 'react-router-dom';
import { Server } from '@/api/server/getServer';
import { ServerPowerState, ServerStats } from '@/api/server/getServerResourceUsage';
import { bytesToString, ip, mbToBytes } from '@/lib/formatters';
import tw from 'twin.macro';
import GreyRowBox from '@/components/elements/GreyRowBox';
import Spinner from '@/components/elements/Spinner';
import styled, { keyframes } from 'styled-components/macro';
import HiddenAddress from '@/components/elements/HiddenAddress';
import { formatDistanceToNow } from 'date-fns';
import isEqual from 'react-fast-compare';
import Sparkline from '@/components/server/console/Sparkline';
import { accentHex } from '@/lib/helpers';
import { headroom } from '@/components/dashboard/DashboardSummary';

const fadeIn = keyframes`
    from {
        opacity: 0;
        transform: translate3d(0, 6px, 0);
    }
`;

// The colour of the dot of each power state.
const DOTS: Record<ServerPowerState | 'unknown', string> = {
    running: '#3ecf8e',
    offline: '#707070',
    starting: '#f5a524',
    stopping: '#f5a524',
    unknown: '#454545',
};

const LABELS: Record<ServerPowerState | 'unknown', string> = {
    running: 'Running',
    offline: 'Offline',
    starting: 'Starting',
    stopping: 'Stopping',
    unknown: 'Connecting',
};

// A server, as a card of the grid: its game and state, its address, what it uses, and its players.
const ServerCard = styled(GreyRowBox)`
    ${tw`flex-col items-stretch p-5`};
    animation: ${fadeIn} 280ms cubic-bezier(0.22, 1, 0.36, 1) backwards;

    /* A soft light of the accent colour that follows the pointer (see onMouseMove), and a slight lift on hover. */
    background-image: radial-gradient(
        480px circle at var(--x, 50%) var(--y, 0%),
        rgb(var(--pd-accent-500) / calc(var(--pd-glow) * 0.13)),
        transparent 45%
    );
    transition: border-color 150ms ease, transform 220ms cubic-bezier(0.22, 1, 0.36, 1), box-shadow 220ms ease,
        --pd-glow 250ms ease;

    &[href]:hover {
        --pd-glow: 1;
        transform: translate3d(0, -2px, 0);
        border-color: rgb(var(--pd-accent-500) / 0.35);
        box-shadow: 0 18px 40px -18px rgba(0, 0, 0, 0.9), 0 0 0 1px rgb(var(--pd-accent-500) / 0.08);
    }

    & .icon {
        ${tw`w-9 h-9 rounded-lg text-sm text-white border-0 p-0`};
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.28), inset 0 -1px 0 rgba(0, 0, 0, 0.15);
    }

    & .chip {
        ${tw`inline-flex items-center gap-1.5 max-w-full rounded-full border border-neutral-600 px-2 py-0.5 text-xs text-neutral-300`};
    }

    & .state {
        ${tw`inline-flex items-center gap-1.5 flex-shrink-0 rounded-full px-2 py-0.5 text-xs font-medium`};
    }

    & .resting {
        ${tw`opacity-40`};
    }

    &[aria-disabled='true'] {
        ${tw`cursor-not-allowed`};
    }
`;

// One figure of the card, with a thin bar of how full it is: light, then orange from 75 %, red from 90 %.
const Meter = ({
    label,
    value,
    ratio,
    resting,
}: {
    label: string;
    value: string;
    ratio: number | null;
    resting?: boolean;
}) => {
    const percent = ratio === null ? 0 : Math.max(0, Math.min(1, ratio)) * 100;

    return (
        <div className={resting ? 'resting' : undefined} css={tw`min-w-0`}>
            <p css={tw`text-xs text-neutral-500`}>{label}</p>
            <p css={tw`mt-0.5 font-mono text-sm text-neutral-50 tabular-nums truncate`}>{value}</p>
            <div css={tw`mt-2 h-1 rounded-full bg-neutral-700 overflow-hidden`}>
                <div
                    css={tw`h-full rounded-full transition-all duration-500`}
                    style={{
                        width: `${ratio === null ? 0 : Math.max(2, percent)}%`,
                        background: percent >= 90 ? '#e5484d' : percent >= 75 ? '#f5a524' : '#ededed',
                    }}
                />
            </div>
        </div>
    );
};

const Notice = ({ children, detail }: { children: React.ReactNode; detail?: React.ReactNode }) => (
    <div>
        <p css={tw`text-sm text-neutral-300`}>{children}</p>
        {detail && <p css={tw`text-xs text-neutral-500 mt-1`}>{detail}</p>}
    </div>
);

interface Props {
    server: Server;
    // The live usage of this server. The dashboard asks for all of its servers at once, every few seconds.
    stats?: ServerStats | null;
    // The CPU of this server over the last readings (oldest first), drawn faintly along the bottom of the card.
    cpuHistory?: number[];
    // True once the usage was asked for and this server did not answer, so the card says so instead of waiting.
    unreachable?: boolean;
    // True when this server was bought in the shop and its resources can be changed (billed monthly).
    upgradeable?: boolean;
    // Set when the server was bought in the shop: its billing (custom monthly, or an offer's paid-until date).
    billing?: { custom: boolean; expiresAt: Date | null };
    className?: string;
    style?: React.CSSProperties;
}

// How long the server has been running ("3 h 12 min"), from its uptime in milliseconds.
const uptime = (ms: number): string => {
    const minutes = Math.floor(ms / 60000);
    if (minutes < 1) return '< 1 min';
    if (minutes < 60) return `${minutes} min`;
    const hours = Math.floor(minutes / 60);

    return hours < 48 ? `${hours} h ${minutes % 60} min` : `${Math.floor(hours / 24)} j ${hours % 24} h`;
};

// The players on a running game server, asked every 20 seconds while the dashboard is visible. A website, a bot or a
// game that does not say how many players it has shows nothing.
const usePlayers = (server: Server, active: boolean): string | null => {
    const [players, setPlayers] = useState<string | null>(null);

    useEffect(() => {
        setPlayers(null);
        if (!active || server.eggFeatures.includes('web')) return;

        let stop = false;
        const ask = () => {
            if (document.hidden) return;
            getPlayersLive(server.uuid)
                .then((live) => {
                    if (stop) return;
                    setPlayers(
                        live.online && live.players !== null
                            ? `${live.players}${live.maxPlayers !== null ? ` / ${live.maxPlayers}` : ''}`
                            : null
                    );
                })
                .catch(() => undefined);
        };
        ask();
        const timer = window.setInterval(ask, 20000);

        return () => {
            stop = true;
            window.clearInterval(timer);
        };
    }, [server.uuid, active]);

    return players;
};

const ServerRow = ({
    server,
    stats = null,
    cpuHistory,
    unreachable = false,
    upgradeable = false,
    billing,
    className,
    style,
}: Props) => {
    const [upgradeOpen, setUpgradeOpen] = useState(false);
    const isSuspended = !!stats?.isSuspended || server.status === 'suspended';
    const state: ServerPowerState | 'unknown' = isSuspended || !stats ? 'unknown' : stats.status;

    const address = server.allocations.filter((alloc) => alloc.isDefault);
    const game = serverGame(server);
    const players = usePlayers(
        server,
        stats?.status === 'running' && !['Node.js', 'Python', 'App'].includes(game.name)
    );
    // CPU and memory say nothing while the server is stopped: they are dimmed, the disk stays.
    const resting = stats?.status === 'offline';

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
            onMouseMove={(e: React.MouseEvent<HTMLElement>) => {
                const box = e.currentTarget.getBoundingClientRect();
                e.currentTarget.style.setProperty('--x', `${e.clientX - box.left}px`);
                e.currentTarget.style.setProperty('--y', `${e.clientY - box.top}px`);
            }}
        >
            {stats?.status === 'running' && cpuHistory && (
                <Sparkline
                    points={cpuHistory}
                    max={headroom(cpuHistory)}
                    color={accentHex(400)}
                    className={'absolute inset-x-0 bottom-0 w-full h-16 opacity-30 pointer-events-none'}
                />
            )}
            <div css={tw`flex items-center gap-3 min-w-0`}>
                <div
                    className={'icon'}
                    title={game.name || undefined}
                    css={tw`flex-shrink-0`}
                    style={{ background: game.to }}
                >
                    <FontAwesomeIcon icon={game.icon} />
                </div>
                <div css={tw`min-w-0 flex-1`}>
                    <p css={tw`text-sm font-semibold text-neutral-50 truncate`}>{server.name}</p>
                    <p css={tw`text-xs text-neutral-500 truncate`}>{game.name || 'Server'}</p>
                </div>
                {isSuspended ? (
                    <span className={'state'} style={{ background: 'rgba(229, 72, 77, 0.12)', color: '#ff9592' }}>
                        <FontAwesomeIcon icon={faLock} css={tw`text-2xs`} />
                        <span>Suspended</span>
                    </span>
                ) : (
                    <span
                        className={'state'}
                        style={{ background: `${DOTS[state]}1f`, color: state === 'running' ? '#6ee7b7' : '#d4d4d4' }}
                    >
                        <span css={tw`relative flex w-1.5 h-1.5`}>
                            {state === 'running' && (
                                <span
                                    css={tw`absolute inset-0 rounded-full animate-ping opacity-60`}
                                    style={{ background: DOTS[state], animationDuration: '2s' }}
                                />
                            )}
                            <span
                                css={tw`relative w-1.5 h-1.5 rounded-full`}
                                className={state === 'starting' || state === 'stopping' ? 'animate-pulse' : undefined}
                                style={{ background: DOTS[state] }}
                            />
                        </span>
                        <span>{LABELS[state]}</span>
                    </span>
                )}
            </div>

            {isSuspended && !!server.suspension?.reason ? (
                <p css={tw`mt-3 text-sm text-red-300 break-words line-clamp-2`}>{server.suspension.reason}</p>
            ) : (
                !!server.description && (
                    <p css={tw`mt-3 text-sm text-neutral-400 break-words line-clamp-2`}>{server.description}</p>
                )
            )}

            {address.length > 0 && (
                <p css={tw`mt-3 font-mono text-xs text-neutral-300 truncate`}>
                    {address.map((allocation) => (
                        <HiddenAddress
                            key={allocation.ip + allocation.port.toString()}
                            host={allocation.alias || ip(allocation.ip)}
                            port={allocation.port}
                        />
                    ))}
                </p>
            )}

            <div css={tw`mt-auto pt-4`}>
                <div css={tw`pt-4 border-t border-neutral-700`}>
                    {!stats || isSuspended || server.isNodeUnderMaintenance ? (
                        isSuspended ? (
                            <Notice
                                detail={
                                    server.status !== 'suspended'
                                        ? undefined
                                        : !server.suspension?.until
                                        ? 'Until an administrator lifts it'
                                        : server.suspension.until.getTime() <= Date.now()
                                        ? 'Access is about to come back'
                                        : `Access back ${formatDistanceToNow(server.suspension.until, {
                                              addSuffix: true,
                                          })}`
                                }
                            >
                                {server.status === 'suspended' ? 'Suspended' : 'Connection Error'}
                            </Notice>
                        ) : server.isNodeUnderMaintenance ? (
                            <Notice>Under Maintenance</Notice>
                        ) : server.isTransferring || server.status ? (
                            <Notice>
                                {server.isTransferring
                                    ? 'Transferring'
                                    : server.status === 'installing'
                                    ? 'Installing'
                                    : server.status === 'restoring_backup'
                                    ? 'Restoring Backup'
                                    : 'Unavailable'}
                            </Notice>
                        ) : unreachable ? (
                            <Notice detail={'The node does not answer, it is tried again every few seconds.'}>
                                Unreachable
                            </Notice>
                        ) : (
                            <div css={tw`flex justify-center py-2`}>
                                <Spinner size={'small'} />
                            </div>
                        )
                    ) : (
                        <div css={tw`grid grid-cols-3 gap-4`}>
                            <Meter
                                label={'CPU'}
                                resting={resting}
                                value={resting ? '—' : `${stats.cpuUsagePercent.toFixed(1)} %`}
                                ratio={
                                    !resting && server.limits.cpu > 0 ? stats.cpuUsagePercent / server.limits.cpu : null
                                }
                            />
                            <Meter
                                label={'Memory'}
                                resting={resting}
                                value={resting ? '—' : bytesToString(stats.memoryUsageInBytes)}
                                ratio={
                                    !resting && server.limits.memory > 0
                                        ? stats.memoryUsageInBytes / mbToBytes(server.limits.memory)
                                        : null
                                }
                            />
                            <Meter
                                label={'Disk'}
                                value={bytesToString(stats.diskUsageInBytes)}
                                ratio={
                                    server.limits.disk > 0
                                        ? stats.diskUsageInBytes / mbToBytes(server.limits.disk)
                                        : null
                                }
                            />
                        </div>
                    )}
                </div>

                {(players !== null || (stats?.status === 'running' && stats.uptime > 0) || billing || upgradeable) && (
                    <div css={tw`mt-4 flex flex-wrap items-center gap-1.5`}>
                        {players !== null && (
                            <span className={'chip'} title={'Players'}>
                                <FontAwesomeIcon icon={faUsers} css={tw`text-neutral-500`} />
                                {players}
                            </span>
                        )}
                        {stats?.status === 'running' && stats.uptime > 0 && (
                            <span className={'chip'} title={'Uptime'}>
                                <FontAwesomeIcon icon={faClock} css={tw`text-neutral-500`} />
                                {uptime(stats.uptime)}
                            </span>
                        )}
                        {billing && (
                            <span className={'chip'}>
                                <FontAwesomeIcon icon={faCalendarAlt} css={tw`text-neutral-500`} />
                                {billing.custom ? (
                                    <span>Billed monthly</span>
                                ) : billing.expiresAt ? (
                                    <span>
                                        <span>Paid until</span>{' '}
                                        {new Intl.DateTimeFormat(undefined, {
                                            day: 'numeric',
                                            month: 'short',
                                        }).format(billing.expiresAt)}
                                    </span>
                                ) : (
                                    <span>Billed monthly</span>
                                )}
                            </span>
                        )}
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
                                css={tw`ml-auto flex items-center justify-center w-7 h-7 rounded-md border border-neutral-600 text-neutral-400 hover:text-neutral-50 hover:bg-neutral-700 transition-colors duration-150`}
                            >
                                <FontAwesomeIcon icon={faSlidersH} css={tw`text-xs`} />
                            </button>
                        )}
                    </div>
                )}
            </div>
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
