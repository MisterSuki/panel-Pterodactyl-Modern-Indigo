import React, { useEffect, useMemo, useState } from 'react';
import tw from 'twin.macro';
import { format } from 'date-fns';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faChartLine, faCircleNotch, faClock, faUsers, faArrowUp } from '@fortawesome/free-solid-svg-icons';
import ServerContentBlock from '@/components/elements/ServerContentBlock';
import FlashMessageRender from '@/components/FlashMessageRender';
import { ServerContext } from '@/state/server';
import { useFlashKey } from '@/plugins/useFlash';
import { dateLocale } from '@/components/elements/activity/kinds';
import { getPlayerHistory, PlayerHistory } from '@/api/server/insights';

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

// The curve of the players, drawn in SVG (no library needed): the average of each slot, gaps where nothing was read.
const Curve = ({ data }: { data: PlayerHistory }) => {
    const top = Math.max(4, data.maxPlayers || 0, data.peak) * 1.1;
    const n = data.points.length;
    const x = (i: number) => PAD.left + (i / Math.max(1, n - 1)) * (W - PAD.left - PAD.right);
    const y = (v: number) => PAD.top + (1 - v / top) * (H - PAD.top - PAD.bottom);

    const segments: string[] = [];
    let current: string[] = [];
    data.points.forEach((p, i) => {
        if (p.players === null) {
            if (current.length) segments.push(current.join(' '));
            current = [];

            return;
        }
        current.push(`${current.length ? 'L' : 'M'}${x(i).toFixed(1)},${y(p.players).toFixed(1)}`);
    });
    if (current.length) segments.push(current.join(' '));

    const ticks = [0, 0.5, 1].map((f) => Math.round(top * f / 1.1));
    const labels = [0, Math.floor(n / 2), n - 1].filter((i) => data.points[i]);

    return (
        <svg viewBox={`0 0 ${W} ${H}`} css={tw`w-full h-auto`} preserveAspectRatio={'none'}>
            <defs>
                <linearGradient id={'pd-players-fill'} x1={0} y1={0} x2={0} y2={1}>
                    <stop offset={'0%'} stopColor={'rgb(var(--pd-accent-400))'} stopOpacity={0.45} />
                    <stop offset={'100%'} stopColor={'rgb(var(--pd-accent-400))'} stopOpacity={0} />
                </linearGradient>
            </defs>
            {ticks.map((t) => (
                <g key={t}>
                    <line x1={PAD.left} x2={W - PAD.right} y1={y(t)} y2={y(t)} stroke={'rgba(255,255,255,0.08)'} />
                    <text x={PAD.left - 8} y={y(t) + 4} textAnchor={'end'} fontSize={12} fill={'rgba(235,235,240,0.5)'}>
                        {t}
                    </text>
                </g>
            ))}
            {data.maxPlayers !== null && (
                <line
                    x1={PAD.left}
                    x2={W - PAD.right}
                    y1={y(data.maxPlayers)}
                    y2={y(data.maxPlayers)}
                    stroke={'rgba(255,159,10,0.5)'}
                    strokeDasharray={'6 6'}
                />
            )}
            {segments.map((d, i) => {
                const first = d.match(/M([\d.]+)/);
                const last = d.match(/([\d.]+),[\d.]+$/);

                return (
                    <g key={i}>
                        {first && last && (
                            <path
                                d={`${d} L${last[1]},${y(0)} L${first[1]},${y(0)} Z`}
                                fill={'url(#pd-players-fill)'}
                            />
                        )}
                        <path d={d} fill={'none'} stroke={'rgb(var(--pd-accent-400))'} strokeWidth={2.5} strokeLinejoin={'round'} />
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
                    {format(data.points[i].at, data.range === '7d' ? 'EEE HH:mm' : 'HH:mm', { locale: dateLocale() })}
                </text>
            ))}
        </svg>
    );
};

export default () => {
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const { clearAndAddHttpError } = useFlashKey('server:stats');
    const [range, setRange] = useState<'24h' | '7d'>('24h');
    const [data, setData] = useState<PlayerHistory | null>(null);

    useEffect(() => {
        setData(null);
        getPlayerHistory(uuid, range)
            .then(setData)
            .catch((error) => clearAndAddHttpError(error));
    }, [uuid, range]);

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
                    <h1 css={tw`text-xl text-neutral-50 font-semibold`}>Player statistics</h1>
                    <p css={tw`text-sm text-neutral-400`}>Counted every 5 minutes while the server runs.</p>
                </div>
                <div css={tw`flex gap-1 p-1 rounded-full bg-black/30 border border-white/10`}>
                    {(['24h', '7d'] as const).map((r) => (
                        <button
                            key={r}
                            type={'button'}
                            onClick={() => setRange(r)}
                            css={[
                                tw`px-4 py-1.5 rounded-full text-sm font-medium focus:outline-none`,
                                range === r ? tw`bg-white/20 text-white` : tw`text-neutral-300 hover:text-white`,
                            ]}
                        >
                            {r === '24h' ? <span>24 hours</span> : <span>7 days</span>}
                        </button>
                    ))}
                </div>
            </div>

            {!data ? (
                <div css={tw`flex justify-center py-16 text-neutral-400`}>
                    <FontAwesomeIcon icon={faCircleNotch} spin size={'2x'} />
                </div>
            ) : data.samples === 0 ? (
                <div css={tw`rounded-2xl bg-neutral-800 border border-white/10 shadow-card p-8 text-center`}>
                    <p css={tw`text-neutral-200 font-medium`}>No data yet.</p>
                    <p css={tw`text-sm text-neutral-400 mt-1`}>
                        The players are counted every 5 minutes while the server runs (Minecraft, FiveM and Steam games).
                        Come back in a little while.
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
                        <p css={tw`text-xs text-neutral-400 mb-3`}>Average players at each hour of the day, over the last 7 days.</p>
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
        </ServerContentBlock>
    );
};
