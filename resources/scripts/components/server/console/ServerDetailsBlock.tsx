import React, { useEffect, useMemo, useState } from 'react';
import { faClock, faHdd, faMemory, faMicrochip, faSignal, faUsers, faWifi } from '@fortawesome/free-solid-svg-icons';
import { bytesToString, ip, mbToBytes } from '@/lib/formatters';
import { ServerContext } from '@/state/server';
import { SocketEvent, SocketRequest } from '@/components/server/events';
import UptimeDuration from '@/components/server/UptimeDuration';
import StatBlock from '@/components/server/console/StatBlock';
import HiddenAddress from '@/components/elements/HiddenAddress';
import useFiveM from '@/components/server/console/useFiveM';
import useGameStatus from '@/components/server/console/useGameStatus';
import useConnectionCount from '@/components/server/console/useConnectionCount';
import usePing from '@/components/server/console/usePing';
import useWebsocketEvent from '@/plugins/useWebsocketEvent';
import classNames from 'classnames';
import { capitalize } from '@/lib/strings';

type Stats = Record<'memory' | 'cpu' | 'disk' | 'uptime', number>;

const getBackgroundColor = (value: number, max: number | null): string | undefined => {
    const delta = !max ? 0 : value / max;

    if (delta > 0.8) {
        if (delta > 0.9) {
            return 'bg-red-500';
        }
        return 'bg-yellow-500';
    }

    return undefined;
};

const Limit = ({ limit, children }: { limit: string | null; children: React.ReactNode }) => (
    <>
        {children}
        <span className={'ml-1 text-gray-300 text-[70%] select-none'}>/ {limit || <>&infin;</>}</span>
    </>
);

const ServerDetailsBlock = ({ className }: { className?: string }) => {
    const [stats, setStats] = useState<Stats>({ memory: 0, cpu: 0, disk: 0, uptime: 0 });
    const ping = usePing();

    const status = ServerContext.useStoreState((state) => state.status.value);
    const fivem = useFiveM();
    const game = useGameStatus(fivem.isFiveM);
    const variables = ServerContext.useStoreState((state) => state.server.data!.variables);
    // The number of people the game says are connected, when it does not answer the Steam query.
    const connections = useConnectionCount(game.isSteam, stats.uptime);
    const answered = !!game.data?.online && game.data.players !== null;
    const gamePlayers = answered ? game.data!.players : connections;
    const slotVariable = variables.find((v) => /^(SERVER_SLOTS|MAX_PLAYERS|MAXPLAYERS|SLOTS)$/i.test(v.envVariable));
    const slots = Number(slotVariable?.serverValue ?? slotVariable?.defaultValue);
    const gameSlots = (answered ? game.data!.maxPlayers : null) ?? (slots > 0 ? slots : null);
    const connected = ServerContext.useStoreState((state) => state.socket.connected);
    const instance = ServerContext.useStoreState((state) => state.socket.instance);
    const limits = ServerContext.useStoreState((state) => state.server.data!.limits);

    const textLimits = useMemo(
        () => ({
            cpu: limits?.cpu ? `${limits.cpu}%` : null,
            memory: limits?.memory ? bytesToString(mbToBytes(limits.memory)) : null,
            disk: limits?.disk ? bytesToString(mbToBytes(limits.disk)) : null,
        }),
        [limits]
    );

    const allocationHost = ServerContext.useStoreState((state) => {
        const match = state.server.data!.allocations.find((allocation) => allocation.isDefault);

        return !match ? null : match.alias || ip(match.ip);
    });
    const allocationPort = ServerContext.useStoreState(
        (state) => state.server.data!.allocations.find((allocation) => allocation.isDefault)?.port
    );
    // What gets copied is always the real address, even while it is hidden on screen.
    const allocation =
        allocationHost === null || allocationPort === undefined ? 'n/a' : `${allocationHost}:${allocationPort}`;

    useEffect(() => {
        if (!connected || !instance) {
            return;
        }

        instance.send(SocketRequest.SEND_STATS);
    }, [instance, connected]);

    useWebsocketEvent(SocketEvent.STATS, (data) => {
        let stats: any = {};
        try {
            stats = JSON.parse(data);
        } catch (e) {
            return;
        }

        setStats({
            memory: stats.memory_bytes,
            cpu: stats.cpu_absolute,
            disk: stats.disk_bytes,
            uptime: stats.uptime || 0,
        });
    });

    return (
        <div className={classNames('grid grid-cols-6 gap-2 md:gap-4', className)}>
            <StatBlock icon={faWifi} title={'Address'} copyOnClick={allocation}>
                {allocationHost === null || allocationPort === undefined ? (
                    allocation
                ) : (
                    <HiddenAddress host={allocationHost} port={allocationPort} />
                )}
            </StatBlock>
            {fivem.isFiveM && (
                <StatBlock
                    icon={faUsers}
                    title={'Players'}
                    color={
                        fivem.data?.online && fivem.data.players !== null && fivem.data.maxPlayers
                            ? getBackgroundColor(fivem.data.players, fivem.data.maxPlayers)
                            : undefined
                    }
                >
                    {fivem.data?.online && fivem.data.players !== null ? (
                        <>
                            {fivem.data.players}
                            {fivem.data.maxPlayers ? (
                                <span className={'ml-1 text-gray-300 text-[70%] select-none'}>
                                    / {fivem.data.maxPlayers}
                                </span>
                            ) : null}
                        </>
                    ) : (
                        <span className={'text-gray-400'}>{status === 'offline' ? 'Offline' : 'Waiting...'}</span>
                    )}
                </StatBlock>
            )}
            {game.isSteam && (
                <StatBlock
                    icon={faUsers}
                    title={'Players'}
                    color={gamePlayers !== null && gameSlots ? getBackgroundColor(gamePlayers, gameSlots) : undefined}
                >
                    {gamePlayers !== null ? (
                        <>
                            {gamePlayers}
                            {gameSlots ? (
                                <span className={'ml-1 text-gray-300 text-[70%] select-none'}>/ {gameSlots}</span>
                            ) : null}
                        </>
                    ) : (
                        <span className={'text-gray-400'}>
                            {status === 'offline' ? 'Offline' : game.data ? 'Unavailable' : 'Waiting...'}
                        </span>
                    )}
                </StatBlock>
            )}
            <StatBlock
                icon={faClock}
                title={'Uptime'}
                color={getBackgroundColor(status === 'running' ? 0 : status !== 'offline' ? 9 : 10, 10)}
            >
                {status === null ? (
                    'Offline'
                ) : stats.uptime > 0 ? (
                    <UptimeDuration uptime={stats.uptime / 1000} />
                ) : (
                    capitalize(status)
                )}
            </StatBlock>
            <StatBlock icon={faMicrochip} title={'CPU Load'} color={getBackgroundColor(stats.cpu, limits.cpu)}>
                {status === 'offline' ? (
                    <span className={'text-gray-400'}>Offline</span>
                ) : (
                    <Limit limit={textLimits.cpu}>{stats.cpu.toFixed(2)}%</Limit>
                )}
            </StatBlock>
            <StatBlock
                icon={faMemory}
                title={'Memory'}
                color={getBackgroundColor(stats.memory / 1024, limits.memory * 1024)}
            >
                {status === 'offline' ? (
                    <span className={'text-gray-400'}>Offline</span>
                ) : (
                    <Limit limit={textLimits.memory}>{bytesToString(stats.memory)}</Limit>
                )}
            </StatBlock>
            <StatBlock icon={faHdd} title={'Disk'} color={getBackgroundColor(stats.disk / 1024, limits.disk * 1024)}>
                <Limit limit={textLimits.disk}>{bytesToString(stats.disk)}</Limit>
            </StatBlock>
            <StatBlock icon={faSignal} title={'Ping'} color={ping === null ? undefined : getBackgroundColor(ping, 300)}>
                {ping === null ? <span className={'text-gray-400'}>&ndash;</span> : `${ping} ms`}
            </StatBlock>
        </div>
    );
};

export default ServerDetailsBlock;
