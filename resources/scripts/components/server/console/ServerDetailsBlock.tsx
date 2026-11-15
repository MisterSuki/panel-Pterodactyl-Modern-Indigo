import React, { useEffect, useMemo, useRef, useState } from 'react';
import { accentHex } from '@/lib/helpers';
import {
    faArrowDown,
    faArrowUp,
    faClock,
    faExchangeAlt,
    faHdd,
    faMemory,
    faMicrochip,
    faUsers,
} from '@fortawesome/free-solid-svg-icons';
import Icon from '@/components/elements/Icon';
import { bytesToString, mbToBytes } from '@/lib/formatters';
import { ServerContext } from '@/state/server';
import { SocketEvent, SocketRequest } from '@/components/server/events';
import UptimeDuration from '@/components/server/UptimeDuration';
import StatBlock from '@/components/server/console/StatBlock';
import useFiveM from '@/components/server/console/useFiveM';
import useGameStatus from '@/components/server/console/useGameStatus';
import useConnectionCount from '@/components/server/console/useConnectionCount';
import useWebsocketEvent from '@/plugins/useWebsocketEvent';
import classNames from 'classnames';
import { capitalize } from '@/lib/strings';
import Sparkline from '@/components/server/console/Sparkline';
import { theme } from 'twin.macro';

type Stats = Record<'memory' | 'cpu' | 'disk' | 'uptime', number>;

// How many readings the little trend lines keep (about a minute at one reading every two seconds).
const HISTORY = 30;

const pushHistory = (list: number[], value: number): number[] => [...list, value].slice(-HISTORY);

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

// The value and its limit on the same line, smaller and aligned on the bottom of the figures. In a narrow block the
// limit is cut (with "...") before the value.
const Limit = ({ limit, children }: { limit: string | null; children: React.ReactNode }) => (
    <span className={'flex items-baseline gap-1 min-w-0'}>
        <span className={'flex-shrink-0 whitespace-nowrap'}>{children}</span>
        <span className={'min-w-0 truncate text-xs font-normal tracking-normal text-gray-400 select-none'}>
            / {limit || <>&infin;</>}
        </span>
    </span>
);

// A speed in bytes per second, short enough for a narrow block.
const speed = (bytes: number): string => `${bytesToString(bytes, bytes >= 1024 * 1024 ? 1 : 0)}/s`;

// One direction of the network: an arrow in its colour (green coming in, yellow going out, as in the Network chart),
// then the speed. The title of the block says which is which, in the same order.
const Flow = ({ up, value }: { up?: boolean; value: number }) => (
    <span className={'flex items-center gap-1.5 text-base leading-6 min-w-0'}>
        <Icon
            icon={up ? faArrowUp : faArrowDown}
            className={classNames('w-3 h-3 flex-shrink-0', up ? 'text-yellow-400' : 'text-green-400')}
        />
        <span className={'truncate'}>{speed(value)}</span>
    </span>
);

const ServerDetailsBlock = ({ className }: { className?: string }) => {
    const [stats, setStats] = useState<Stats>({ memory: 0, cpu: 0, disk: 0, uptime: 0 });
    const [history, setHistory] = useState<Record<'cpu' | 'memory' | 'disk' | 'rx' | 'tx', number[]>>({
        cpu: [],
        memory: [],
        disk: [],
        rx: [],
        tx: [],
    });
    // The network speeds (bytes per second): Wings gives totals since the start, the speed is the difference between
    // two readings over the time between them.
    const [network, setNetwork] = useState({ rx: 0, tx: 0 });
    const lastNetwork = useRef<{ rx: number; tx: number; at: number } | null>(null);

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
        const now = Date.now() / 1000;
        const rxTotal = Number(stats.network?.rx_bytes) || 0;
        const txTotal = Number(stats.network?.tx_bytes) || 0;
        const before = lastNetwork.current;
        // A total that went down means the server restarted: that reading only starts again from it.
        const rates =
            before && rxTotal >= before.rx && txTotal >= before.tx
                ? {
                      rx: (rxTotal - before.rx) / Math.max(0.5, now - before.at),
                      tx: (txTotal - before.tx) / Math.max(0.5, now - before.at),
                  }
                : { rx: 0, tx: 0 };
        lastNetwork.current = { rx: rxTotal, tx: txTotal, at: now };
        setNetwork(rates);

        setHistory((prev) => ({
            cpu: pushHistory(prev.cpu, stats.cpu_absolute || 0),
            memory: pushHistory(prev.memory, stats.memory_bytes || 0),
            disk: pushHistory(prev.disk, stats.disk_bytes || 0),
            rx: pushHistory(prev.rx, rates.rx),
            tx: pushHistory(prev.tx, rates.tx),
        }));
    });

    // When the server stops, the little trend lines are cleared so they do not show a stale minute.
    useEffect(() => {
        if (status === 'offline') {
            setHistory({ cpu: [], memory: [], disk: [], rx: [], tx: [] });
            setNetwork({ rx: 0, tx: 0 });
            lastNetwork.current = null;
        }
    }, [status]);

    return (
        // A row of figures above the console, as many columns as fit.
        <div
            className={classNames('grid gap-3', className)}
            style={{ gridTemplateColumns: 'repeat(auto-fit, minmax(130px, 1fr))' }}
        >
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
                        fivem.data.maxPlayers ? (
                            <Limit limit={String(fivem.data.maxPlayers)}>{fivem.data.players}</Limit>
                        ) : (
                            <>{fivem.data.players}</>
                        )
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
                        gameSlots ? (
                            <Limit limit={String(gameSlots)}>{gamePlayers}</Limit>
                        ) : (
                            <>{gamePlayers}</>
                        )
                    ) : (
                        <span className={'text-gray-400'}>
                            {status === 'offline' ? 'Offline' : game.data ? 'Unavailable' : 'Waiting...'}
                        </span>
                    )}
                </StatBlock>
            )}
            <StatBlock
                icon={faMicrochip}
                title={'CPU Load'}
                color={getBackgroundColor(stats.cpu, limits.cpu)}
                sparkline={
                    status !== 'offline' && (
                        <Sparkline points={history.cpu} max={limits.cpu || null} color={accentHex(400)} />
                    )
                }
            >
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
                sparkline={
                    status !== 'offline' && (
                        <Sparkline
                            points={history.memory}
                            max={limits.memory ? mbToBytes(limits.memory) : null}
                            color={theme('colors.cyan.400')}
                        />
                    )
                }
            >
                {status === 'offline' ? (
                    <span className={'text-gray-400'}>Offline</span>
                ) : (
                    <Limit limit={textLimits.memory}>{bytesToString(stats.memory)}</Limit>
                )}
            </StatBlock>
            <StatBlock
                icon={faHdd}
                title={'Disk'}
                color={getBackgroundColor(stats.disk / 1024, limits.disk * 1024)}
                sparkline={
                    <Sparkline
                        points={history.disk}
                        max={limits.disk ? mbToBytes(limits.disk) : null}
                        color={theme('colors.green.400')}
                    />
                }
            >
                <Limit limit={textLimits.disk}>{bytesToString(stats.disk)}</Limit>
            </StatBlock>
            <StatBlock
                icon={faExchangeAlt}
                title={'In / Out'}
                sparkline={
                    status !== 'offline' && (
                        <>
                            <Sparkline
                                points={history.rx}
                                max={Math.max(1024, ...history.rx, ...history.tx)}
                                color={theme('colors.green.400')}
                            />
                            <Sparkline
                                points={history.tx}
                                max={Math.max(1024, ...history.rx, ...history.tx)}
                                color={theme('colors.yellow.400')}
                            />
                        </>
                    )
                }
            >
                {status === 'offline' ? (
                    <span className={'text-gray-400'}>Offline</span>
                ) : (
                    <>
                        <Flow value={network.rx} />
                        <Flow up value={network.tx} />
                    </>
                )}
            </StatBlock>
        </div>
    );
};

export default ServerDetailsBlock;
