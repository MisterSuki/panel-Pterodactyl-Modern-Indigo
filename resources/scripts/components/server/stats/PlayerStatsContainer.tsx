import React, { useEffect, useMemo, useRef, useState } from 'react';
import tw from 'twin.macro';
import { format } from 'date-fns';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import {
    faArrowUp,
    faChartLine,
    faCircleNotch,
    faClock,
    faHdd,
    faMemory,
    faMicrochip,
    faPowerOff,
    faUsers,
} from '@fortawesome/free-solid-svg-icons';
import ServerContentBlock from '@/components/elements/ServerContentBlock';
import FlashMessageRender from '@/components/FlashMessageRender';
import { ServerContext } from '@/state/server';
import { useFlashKey } from '@/plugins/useFlash';
import { dateLocale } from '@/components/elements/activity/kinds';
import {
    getPlayerHistory,
    getPlayersLive,
    getResourceHistory,
    PlayerHistory,
    PlayersLive,
    ResourceHistory,
    StatsRange,
} from '@/api/server/insights';

const W = 1000;
const H = 260;
const PAD = { left: 36, right: 12, top: 14, bottom: 28 };

const Tile = ({ icon, label, value, sub }: { icon: any; label: string; value: string; sub?: string }) => (
    <div css={tw`rounded-2xl bg-neutral-800 border border-white/10 shadow-card p-4 flex items-center gap-3`}>
        <span
            css={tw`flex items-center justify-center w-11 h-11 rounded-xl bg-primary-500 bg-opacity-20 text-primary-300 flex-shrink-0`}
        >
            <FontAwesomeIcon icon={icon} />
        </span>
        <div css={tw`min-w-0`}>
            <p css={tw`text-xs uppercase tracking-wide text-neutral-400`}>{label}</p>
            <p css={tw`text-xl font-semibold text-neutral-50 tabular-nums`}>
                {value}
                {sub && <span css={tw`text-sm text-neutral-400 font-normal`}> {sub}</span>}
            </p>
        </div>
    </div>
);

// A curve drawn in SVG (no library needed): the average of each slot, the peak as a faint line above it, gaps where
// nothing was read, and the limit of the server as a dashed orange line.
interface ChartPoint {
    at: Date;
    value: number | null;
    peak?: number | null;
}

const dateFormat = (range: StatsRange) => (range === '24h' ? 'HH:mm' : range === '7d' ? 'EEE HH:mm' : 'd MMM');

const LineChart = ({
    id,
    points,
    range,
    top,
    limit,
    color,
    label,
}: {
    id: string;
    points: ChartPoint[];
    range: StatsRange;
    top: number;
    limit?: number | null;
    color: string;
    label: (v: number) => string;
}) => {
    const n = points.length;
    const x = (i: number) => PAD.left + (i / Math.max(1, n - 1)) * (W - PAD.left - PAD.right);
    const y = (v: number) => PAD.top + (1 - Math.min(v, top) / top) * (H - PAD.top - PAD.bottom);

    const lines = (pick: (p: ChartPoint) => number | null | undefined) => {
        const out: string[] = [];
        let current: string[] = [];
        points.forEach((p, i) => {
            const v = pick(p);
            if (v === null || v === undefined) {
                if (current.length) out.push(current.join(' '));
                current = [];

                return;
            }
            current.push(`${current.length ? 'L' : 'M'}${x(i).toFixed(1)},${y(v).toFixed(1)}`);
        });
        if (current.length) out.push(current.join(' '));

        return out;
    };
    const average = lines((p) => p.value);
    const peaks = lines((p) => p.peak);

    const ticks = [0, 0.5, 1].map((f) => (top / 1.1) * f);
    const labels = [0, Math.floor(n / 2), n - 1].filter((i) => points[i]);

    return (
        <svg viewBox={`0 0 ${W} ${H}`} css={tw`w-full h-auto`} preserveAspectRatio={'none'}>
            <defs>
                <linearGradient id={id} x1={0} y1={0} x2={0} y2={1}>
                    <stop offset={'0%'} stopColor={color} stopOpacity={0.42} />
                    <stop offset={'100%'} stopColor={color} stopOpacity={0} />
                </linearGradient>
            </defs>
            {ticks.map((t, i) => (
                <g key={i}>
                    <line x1={PAD.left} x2={W - PAD.right} y1={y(t)} y2={y(t)} stroke={'rgba(255,255,255,0.08)'} />
                    <text x={PAD.left - 8} y={y(t) + 4} textAnchor={'end'} fontSize={12} fill={'rgba(235,235,240,0.5)'}>
                        {label(t)}
                    </text>
                </g>
            ))}
            {!!limit && limit <= top && (
                <line
                    x1={PAD.left}
                    x2={W - PAD.right}
                    y1={y(limit)}
                    y2={y(limit)}
                    stroke={'rgba(255,159,10,0.55)'}
                    strokeDasharray={'6 6'}
                />
            )}
            {peaks.map((d, i) => (
                <path key={`p${i}`} d={d} fill={'none'} stroke={color} strokeOpacity={0.35} strokeWidth={1.5} />
            ))}
            {average.map((d, i) => {
                const first = d.match(/M([\d.]+)/);
                const last = d.match(/([\d.]+),[\d.]+$/);

                return (
                    <g key={i}>
                        {first && last && (
                            <path d={`${d} L${last[1]},${y(0)} L${first[1]},${y(0)} Z`} fill={`url(#${id})`} />
                        )}
                        <path d={d} fill={'none'} stroke={color} strokeWidth={2.5} strokeLinejoin={'round'} />
                    </g>
                );
            })}
            {labels.map((i) => (
                <text
                    key={i}
                    x={x(i)}
                    y={H - 8}
                    textAnchor={i === 0 ? 'start' : i === n - 1 ? 'end' : 'middle'}
                    fontSize={12}
                    fill={'rgba(235,235,240,0.5)'}
                >
                    {format(points[i].at, dateFormat(range), { locale: dateLocale() })}
                </text>
            ))}
        </svg>
    );
};

// The players: the same curve, up to the number of slots of the server.
const Curve = ({ data }: { data: PlayerHistory }) => (
    <LineChart
        id={'pd-players-fill'}
        points={data.points.map((p) => ({ at: p.at, value: p.players }))}
        range={data.range}
        top={Math.max(4, data.maxPlayers || 0, data.peak) * 1.1}
        limit={data.maxPlayers}
        color={'rgb(var(--pd-accent-400))'}
        label={(v) => String(Math.round(v))}
    />
);

const megabytes = (mb: number) =>
    mb >= 1024 ? `${(mb / 1024).toFixed(mb >= 10240 ? 0 : 1)} GB` : `${Math.round(mb)} MB`;

// The top of a chart: room above the peak, and the limit of the server when it is close enough to be useful.
const chartTop = (peak: number, limit: number, least: number) =>
    Math.max(least, peak * 1.15, limit > 0 && limit <= peak * 2 ? limit * 1.05 : 0);

// What the server used over the range: processor, memory, disk and the time it ran.
const Resources = ({ range }: { range: StatsRange }) => {
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const [data, setData] = useState<ResourceHistory | null>(null);

    useEffect(() => {
        setData(null);
        const load = () =>
            getResourceHistory(uuid, range)
                .then(setData)
                .catch(() => undefined);
        load();
        const timer = window.setInterval(() => !document.hidden && load(), 60000);

        return () => window.clearInterval(timer);
    }, [uuid, range]);

    return (
        <div css={tw`mt-8`}>
            <div css={tw`flex items-center gap-3 mb-4`}>
                <span
                    css={tw`flex items-center justify-center w-11 h-11 rounded-2xl text-white text-lg flex-shrink-0`}
                    style={{ background: '#30d158', boxShadow: 'inset 0 1px 0 rgba(255,255,255,0.3)' }}
                >
                    <FontAwesomeIcon icon={faMicrochip} />
                </span>
                <div css={tw`flex-1 min-w-0`}>
                    <h2 css={tw`text-xl text-neutral-50 font-semibold`}>Resources</h2>
                    <p css={tw`text-sm text-neutral-400`}>Read every 5 minutes while the server runs, kept 30 days.</p>
                </div>
            </div>
            {!data ? (
                <div css={tw`flex justify-center py-12 text-neutral-400`}>
                    <FontAwesomeIcon icon={faCircleNotch} spin size={'2x'} />
                </div>
            ) : data.samples === 0 ? (
                <div css={tw`rounded-2xl bg-neutral-800 border border-white/10 shadow-card p-8 text-center`}>
                    <p css={tw`text-neutral-200 font-medium`}>No data yet.</p>
                    <p css={tw`text-sm text-neutral-400 mt-1`}>
                        What the server uses is written every 5 minutes while it runs. The first point arrives in a few
                        minutes.
                    </p>
                </div>
            ) : (
                <>
                    <div css={tw`grid grid-cols-2 lg:grid-cols-4 gap-4 mb-4`}>
                        <Tile
                            icon={faMicrochip}
                            label={'CPU average'}
                            value={`${data.cpu.average}%`}
                            sub={`↑ ${data.cpu.peak}%`}
                        />
                        <Tile
                            icon={faMemory}
                            label={'Memory average'}
                            value={megabytes(data.memory.average)}
                            sub={`↑ ${megabytes(data.memory.peak)}`}
                        />
                        <Tile
                            icon={faHdd}
                            label={'Disk'}
                            value={megabytes(data.disk.used)}
                            sub={data.disk.limit > 0 ? `/ ${megabytes(data.disk.limit)}` : undefined}
                        />
                        <Tile icon={faPowerOff} label={'Online'} value={`${data.uptime}%`} sub={'of the time'} />
                    </div>
                    <div css={tw`grid grid-cols-1 xl:grid-cols-2 gap-4`}>
                        <div css={tw`rounded-2xl bg-neutral-800 border border-white/10 shadow-card p-4`}>
                            <p css={tw`text-sm font-semibold text-neutral-100 mb-2`}>Processor</p>
                            <LineChart
                                id={'pd-cpu-fill'}
                                points={data.points.map((p) => ({ at: p.at, value: p.cpu, peak: p.cpuPeak }))}
                                range={data.range}
                                top={chartTop(data.cpu.peak, data.cpu.limit, 10)}
                                limit={data.cpu.limit}
                                color={'#30d158'}
                                label={(v) => `${Math.round(v)}%`}
                            />
                        </div>
                        <div css={tw`rounded-2xl bg-neutral-800 border border-white/10 shadow-card p-4`}>
                            <p css={tw`text-sm font-semibold text-neutral-100 mb-2`}>Memory</p>
                            <LineChart
                                id={'pd-memory-fill'}
                                points={data.points.map((p) => ({ at: p.at, value: p.memory, peak: p.memoryPeak }))}
                                range={data.range}
                                top={chartTop(data.memory.peak, data.memory.limit, 256)}
                                limit={data.memory.limit}
                                color={'#bf5af2'}
                                label={megabytes}
                            />
                        </div>
                    </div>
                </>
            )}
        </div>
    );
};

const VIA: Record<string, string> = {
    steam: 'Steam query',
    minecraft: 'Minecraft Java',
    bedrock: 'Minecraft Bedrock',
    fivem: 'FiveM',
};

// How many points the live curve keeps (one every 3 seconds: the last 5 minutes).
const LIVE_POINTS = 100;

// The players right now, asked every 3 seconds while the page is open and visible, with the curve of the last minutes.
const LiveCard = ({ onFirstCount }: { onFirstCount: () => void }) => {
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const status = ServerContext.useStoreState((state) => state.status.value);
    const [live, setLive] = useState<PlayersLive | null>(null);
    const [points, setPoints] = useState<number[]>([]);
    const counted = useRef(false);

    useEffect(() => {
        setPoints([]);
        setLive(null);
        if (status !== 'running') return;

        let stop = false;
        let timer = 0;
        const ask = () => {
            if (document.hidden) {
                timer = window.setTimeout(ask, 3000);
                return;
            }
            getPlayersLive(uuid)
                .then((data) => {
                    if (stop) return;
                    setLive(data);
                    if (data.online && data.players !== null) {
                        setPoints((list) => [...list, data.players!].slice(-LIVE_POINTS));
                        if (!counted.current) {
                            counted.current = true;
                            onFirstCount();
                        }
                    }
                })
                .catch(() => undefined)
                .then(() => {
                    if (!stop) timer = window.setTimeout(ask, 3000);
                });
        };
        ask();

        return () => {
            stop = true;
            window.clearTimeout(timer);
        };
    }, [uuid, status]);

    const top = Math.max(4, live?.maxPlayers || 0, ...points) * 1.1;
    const line = points
        .map(
            (v, i) =>
                `${i ? 'L' : 'M'}${((i / (LIVE_POINTS - 1)) * 1000).toFixed(1)},${(82 - (v / top) * 78).toFixed(1)}`
        )
        .join(' ');
    const lastX = (((points.length - 1) / (LIVE_POINTS - 1)) * 1000).toFixed(1);
    const counting = status === 'running' && !!live?.online;

    return (
        <div css={tw`rounded-2xl bg-neutral-800 border border-white/10 shadow-card p-4 mb-4 overflow-hidden`}>
            <div css={tw`flex flex-wrap items-center gap-4`}>
                <div css={tw`flex items-center gap-3`}>
                    <span css={tw`relative flex w-3 h-3`}>
                        {counting && (
                            <span
                                css={tw`absolute inline-flex w-full h-full rounded-full bg-green-400 opacity-75 animate-ping`}
                            />
                        )}
                        <span
                            css={[
                                tw`relative inline-flex w-3 h-3 rounded-full`,
                                counting ? tw`bg-green-400` : tw`bg-neutral-500`,
                            ]}
                        />
                    </span>
                    <div>
                        <p css={tw`text-xs uppercase tracking-wide text-neutral-400`}>Players right now</p>
                        <p css={tw`text-3xl font-semibold text-neutral-50 tabular-nums leading-tight`}>
                            {status !== 'running' ? (
                                <span css={tw`text-xl text-neutral-400`}>Server offline</span>
                            ) : live === null ? (
                                <FontAwesomeIcon icon={faCircleNotch} spin css={tw`text-xl text-neutral-400`} />
                            ) : live.online ? (
                                <>
                                    {live.players}
                                    {live.maxPlayers !== null && (
                                        <span css={tw`text-lg text-neutral-400 font-normal`}> / {live.maxPlayers}</span>
                                    )}
                                </>
                            ) : (
                                <span css={tw`text-xl text-neutral-400`}>–</span>
                            )}
                        </p>
                    </div>
                </div>
                <div css={tw`ml-auto text-right`}>
                    <span
                        css={tw`inline-flex items-center rounded-full border border-green-500/30 bg-green-500/10 px-2.5 py-0.5 text-xs font-medium text-green-300`}
                    >
                        <span>Live</span>
                    </span>
                    {live?.via && <p css={tw`text-xs text-neutral-500 mt-1`}>{VIA[live.via] || live.via}</p>}
                </div>
            </div>
            {status === 'running' && live !== null && !live.online && (
                <p css={tw`text-sm text-neutral-400 mt-3`}>
                    This game does not give its number of players, or it is still starting.
                </p>
            )}
            {points.length > 1 && (
                <svg viewBox={'0 0 1000 84'} preserveAspectRatio={'none'} css={tw`w-full h-20 mt-3`}>
                    <defs>
                        <linearGradient id={'pd-live-fill'} x1={0} y1={0} x2={0} y2={1}>
                            <stop offset={'0%'} stopColor={'rgb(74, 222, 128)'} stopOpacity={0.35} />
                            <stop offset={'100%'} stopColor={'rgb(74, 222, 128)'} stopOpacity={0} />
                        </linearGradient>
                    </defs>
                    <path d={`${line} L${lastX},84 L0,84 Z`} fill={'url(#pd-live-fill)'} />
                    <path
                        d={line}
                        fill={'none'}
                        stroke={'rgb(74, 222, 128)'}
                        strokeWidth={2}
                        vectorEffect={'non-scaling-stroke'}
                    />
                </svg>
            )}
        </div>
    );
};

export default () => {
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const { clearAndAddHttpError } = useFlashKey('server:stats');
    const [range, setRange] = useState<StatsRange>('24h');
    const [data, setData] = useState<PlayerHistory | null>(null);

    const [reload, setReload] = useState(0);

    useEffect(() => {
        setData(null);
        getPlayerHistory(uuid, range)
            .then(setData)
            .catch((error) => clearAndAddHttpError(error));
    }, [uuid, range]);

    // The history is read again quietly every minute, so a new point shows without reloading the page.
    useEffect(() => {
        if (!reload) return;
        getPlayerHistory(uuid, range)
            .then(setData)
            .catch(() => undefined);
    }, [reload]);
    useEffect(() => {
        const timer = window.setInterval(() => !document.hidden && setReload((n) => n + 1), 60000);

        return () => window.clearInterval(timer);
    }, []);

    const busiest = useMemo(() => {
        if (!data) return null;
        let best = -1;
        let hour = -1;
        data.hours.forEach((v, h) => {
            if (v !== null && v > best) {
                best = v;
                hour = h;
            }
        });

        return hour < 0 ? null : hour;
    }, [data]);
    const hourMax = data ? Math.max(1, ...data.hours.map((v) => v || 0)) : 1;

    return (
        <ServerContentBlock title={'Statistics'}>
            <FlashMessageRender byKey={'server:stats'} css={tw`mb-4`} />
            <div css={tw`flex flex-wrap items-center gap-3 mb-4`}>
                <span
                    css={tw`flex items-center justify-center w-11 h-11 rounded-2xl bg-primary-500 bg-opacity-20 text-primary-300 text-lg flex-shrink-0`}
                >
                    <FontAwesomeIcon icon={faChartLine} />
                </span>
                <div css={tw`flex-1 min-w-0`}>
                    <h1 css={tw`text-xl text-neutral-50 font-semibold`}>Statistics</h1>
                    <p css={tw`text-sm text-neutral-400`}>Players and resources, live and over the last 30 days.</p>
                </div>
                <div css={tw`flex gap-1 p-1 rounded-full bg-black/30 border border-white/10`}>
                    {(['24h', '7d', '30d'] as const).map((r) => (
                        <button
                            key={r}
                            type={'button'}
                            onClick={() => setRange(r)}
                            css={[
                                tw`px-4 py-1.5 rounded-full text-sm font-medium focus:outline-none`,
                                range === r ? tw`bg-white/20 text-white` : tw`text-neutral-300 hover:text-white`,
                            ]}
                        >
                            {r === '24h' ? (
                                <span>24 hours</span>
                            ) : r === '7d' ? (
                                <span>7 days</span>
                            ) : (
                                <span>30 days</span>
                            )}
                        </button>
                    ))}
                </div>
            </div>

            <LiveCard onFirstCount={() => window.setTimeout(() => setReload((n) => n + 1), 1500)} />

            {!data ? (
                <div css={tw`flex justify-center py-16 text-neutral-400`}>
                    <FontAwesomeIcon icon={faCircleNotch} spin size={'2x'} />
                </div>
            ) : data.samples === 0 ? (
                <div css={tw`rounded-2xl bg-neutral-800 border border-white/10 shadow-card p-8 text-center`}>
                    <p css={tw`text-neutral-200 font-medium`}>No data yet.</p>
                    <p css={tw`text-sm text-neutral-400 mt-1`}>
                        The players are counted every 5 minutes while the server runs. The first point arrives in a few
                        minutes.
                    </p>
                </div>
            ) : (
                <>
                    <div css={tw`grid grid-cols-1 sm:grid-cols-3 gap-4 mb-4`}>
                        <Tile
                            icon={faArrowUp}
                            label={'Peak'}
                            value={String(data.peak)}
                            sub={data.maxPlayers ? `/ ${data.maxPlayers}` : undefined}
                        />
                        <Tile icon={faUsers} label={'Average'} value={String(data.average)} />
                        <Tile
                            icon={faClock}
                            label={'Busiest hour'}
                            value={busiest === null ? '–' : `${String(busiest).padStart(2, '0')}:00`}
                        />
                    </div>
                    <div css={tw`rounded-2xl bg-neutral-800 border border-white/10 shadow-card p-4 mb-4`}>
                        <p css={tw`text-sm font-semibold text-neutral-100 mb-2`}>Players online</p>
                        <Curve data={data} />
                    </div>
                    <div css={tw`rounded-2xl bg-neutral-800 border border-white/10 shadow-card p-4`}>
                        <p css={tw`text-sm font-semibold text-neutral-100`}>Busy hours</p>
                        <p css={tw`text-xs text-neutral-400 mb-3`}>
                            Average players at each hour of the day, over the last 7 days.
                        </p>
                        <div css={tw`flex items-end gap-1`} style={{ height: 120 }}>
                            {data.hours.map((v, h) => (
                                <div key={h} css={tw`flex-1 flex flex-col items-center justify-end h-full`}>
                                    <div
                                        css={tw`w-full rounded-t`}
                                        title={`${String(h).padStart(2, '0')}:00 — ${v ?? '–'}`}
                                        style={{
                                            height: `${v === null ? 3 : Math.max(4, (v / hourMax) * 100)}%`,
                                            background:
                                                v === null
                                                    ? 'rgba(255,255,255,0.08)'
                                                    : h === busiest
                                                    ? 'rgb(var(--pd-accent-400))'
                                                    : 'rgb(var(--pd-accent-500) / 0.45)',
                                        }}
                                    />
                                </div>
                            ))}
                        </div>
                        <div css={tw`flex justify-between text-2xs text-neutral-500 mt-1.5`}>
                            <span>00:00</span>
                            <span>06:00</span>
                            <span>12:00</span>
                            <span>18:00</span>
                            <span>23:00</span>
                        </div>
                    </div>
                </>
            )}

            <Resources range={range} />
        </ServerContentBlock>
    );
};
