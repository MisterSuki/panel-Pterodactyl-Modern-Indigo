import React, { useEffect, useState } from 'react';
import tw from 'twin.macro';
import useSWR from 'swr';
import { Link, useParams } from 'react-router-dom';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import {
    faArrowLeft,
    faClock,
    faEye,
    faEyeSlash,
    faKey,
    faHdd,
    faMemory,
    faMicrochip,
    faNetworkWired,
    faPlay,
    faPowerOff,
    faRedo,
    faStop,
    faTerminal,
} from '@fortawesome/free-solid-svg-icons';
import PageContentBlock from '@/components/elements/PageContentBlock';
import Spinner from '@/components/elements/Spinner';
import CopyOnClick from '@/components/elements/CopyOnClick';
import { Button } from '@/components/elements/button/index';
import { Dialog } from '@/components/elements/dialog';
import Sparkline from '@/components/server/console/Sparkline';
import VpsStatusPill from '@/components/vps/VpsStatusPill';
import { getVps, getVpsHistory, sendVpsPower, VpsAction, VpsDetails, VpsPoint } from '@/api/vps';
import { bytesToString } from '@/lib/formatters';
import { accentHex } from '@/lib/helpers';
import useFlash from '@/plugins/useFlash';

type Range = 'hour' | 'day' | 'week';

const RANGES: { value: Range; label: string }[] = [
    { value: 'hour', label: '1 h' },
    { value: 'day', label: '24 h' },
    { value: 'week', label: '7 d' },
];

// "3 j 4 h", "2 h 12 min" or "5 min", from seconds.
const duration = (seconds: number | null): string => {
    if (!seconds || seconds <= 0) return '—';
    const minutes = Math.floor(seconds / 60);
    const hours = Math.floor(minutes / 60);
    const days = Math.floor(hours / 24);
    if (days > 0) return `${days} j ${hours % 24} h`;
    if (hours > 0) return `${hours} h ${minutes % 60} min`;

    return `${Math.max(1, minutes)} min`;
};

// The family of the system, from the type Proxmox keeps ("l26", "win11"...).
const system = (os: string | null): string | null => {
    if (!os) return null;
    if (os.startsWith('l')) return 'Linux';
    if (os.startsWith('win') || os.startsWith('w2k') || os === 'wxp' || os === 'wvista') return 'Windows';
    if (os === 'solaris') return 'Solaris';

    return null;
};

const Tile = ({
    icon,
    label,
    value,
    detail,
    children,
}: {
    icon: typeof faClock;
    label: string;
    value: React.ReactNode;
    detail?: React.ReactNode;
    children?: React.ReactNode;
}) => (
    <div className={'pd-neon'} css={tw`relative overflow-hidden rounded-xl bg-neutral-800 px-4 pt-3 pb-8 min-w-0`}>
        <p css={tw`relative flex items-center gap-2 text-xs text-neutral-400`}>
            <FontAwesomeIcon icon={icon} css={tw`text-neutral-500`} fixedWidth />
            <span>{label}</span>
        </p>
        <p css={tw`relative mt-1.5 text-xl font-semibold text-neutral-50 tabular-nums tracking-tight truncate`}>
            {value}
            {detail && <span css={tw`ml-1.5 text-sm font-normal text-neutral-500`}>{detail}</span>}
        </p>
        {children}
    </div>
);

const Chart = ({
    title,
    value,
    series,
}: {
    title: string;
    value: React.ReactNode;
    series: { points: number[]; color: string; max?: number | null }[];
}) => (
    <div className={'pd-neon'} css={tw`relative overflow-hidden rounded-xl bg-neutral-800 p-4 h-44`}>
        <div css={tw`relative flex items-baseline justify-between gap-3`}>
            <p css={tw`text-sm font-medium text-neutral-200`}>{title}</p>
            <p css={tw`text-xs text-neutral-400 tabular-nums`}>{value}</p>
        </div>
        {series.map((line, index) => (
            <Sparkline
                key={index}
                points={line.points}
                max={line.max}
                color={line.color}
                className={'absolute inset-x-0 bottom-0 w-full h-28 opacity-90 pointer-events-none'}
            />
        ))}
    </div>
);

export default () => {
    const { uuid } = useParams<{ uuid: string }>();
    const { addFlash, clearFlashes, clearAndAddHttpError } = useFlash();
    const [range, setRange] = useState<Range>('hour');
    const [pending, setPending] = useState<VpsAction | null>(null);
    const [confirm, setConfirm] = useState<VpsAction | null>(null);
    const [showPassword, setShowPassword] = useState(false);

    const {
        data: vps,
        error,
        mutate,
    } = useSWR<VpsDetails>(['vps', uuid], () => getVps(uuid), {
        refreshInterval: 4000,
        revalidateOnFocus: false,
    });
    const { data: history } = useSWR<VpsPoint[]>(['vps-history', uuid, range], () => getVpsHistory(uuid, range), {
        refreshInterval: 60000,
        revalidateOnFocus: false,
        shouldRetryOnError: false,
    });

    useEffect(() => {
        if (error) clearAndAddHttpError({ key: 'vps:view', error });
    }, [error]);

    const power = (action: VpsAction) => {
        setConfirm(null);
        setPending(action);
        clearFlashes('vps:view');
        sendVpsPower(uuid, action)
            .then(() => {
                addFlash({ key: 'vps:view', type: 'success', message: 'The order was sent to the machine.' });
                window.setTimeout(() => mutate(), 1500);
            })
            .catch((e) => clearAndAddHttpError({ key: 'vps:view', error: e }))
            .then(() => setPending(null));
    };

    const ready = vps?.state === 'ready';
    const running = ready && vps?.status === 'running';
    const points = history || [];
    const last = points.length ? points[points.length - 1] : null;
    const cpuPoints = points.map((p) => p.cpu ?? 0);
    const memPoints = points.map((p) => p.memory ?? 0);
    const inPoints = points.map((p) => p.netIn ?? 0);
    const outPoints = points.map((p) => p.netOut ?? 0);
    const netMax = Math.max(1, ...inPoints, ...outPoints) * 1.2;

    return (
        <PageContentBlock title={vps?.name || 'VPS'} showFlashKey={'vps:view'}>
            <Link
                to={'/vps'}
                css={tw`inline-flex items-center gap-2 text-sm text-neutral-400 hover:text-neutral-100 no-underline mb-5`}
            >
                <FontAwesomeIcon icon={faArrowLeft} css={tw`text-xs`} />
                <span>VPS</span>
            </Link>

            {!vps ? (
                <Spinner centered size={'large'} />
            ) : (
                <>
                    <div css={tw`flex flex-wrap items-start justify-between gap-4 mb-6`}>
                        <div css={tw`min-w-0`}>
                            <div css={tw`flex items-center gap-3`}>
                                <h1 css={tw`text-2xl font-semibold text-neutral-50 tracking-tight truncate`}>
                                    {vps.name}
                                </h1>
                                <VpsStatusPill status={vps.status} state={vps.state} />
                            </div>
                            <p css={tw`text-sm text-neutral-400 mt-1`}>
                                {[
                                    system(vps.os),
                                    vps.cores ? `${vps.cores} vCPU` : null,
                                    vps.maxMemory ? bytesToString(vps.maxMemory) : null,
                                ]
                                    .filter(Boolean)
                                    .join(' · ')}
                            </p>
                        </div>
                        <div css={tw`flex flex-wrap gap-2`}>
                            {ready && !running && (
                                <Button
                                    onClick={() => power('start')}
                                    disabled={!!pending || vps.status === 'busy' || !!vps.error}
                                >
                                    <FontAwesomeIcon icon={faPlay} css={tw`mr-2 text-xs`} />
                                    <span>Start</span>
                                </Button>
                            )}
                            {ready && running && vps.console && (
                                <Link to={`/vps/${uuid}/console`} css={tw`no-underline`}>
                                    <Button>
                                        <FontAwesomeIcon icon={faTerminal} css={tw`mr-2 text-xs`} />
                                        <span>Console</span>
                                    </Button>
                                </Link>
                            )}
                            {ready && running && (
                                <>
                                    <Button.Text onClick={() => power('reboot')} disabled={!!pending}>
                                        <FontAwesomeIcon icon={faRedo} css={tw`mr-2 text-xs`} />
                                        <span>Restart</span>
                                    </Button.Text>
                                    <Button.Text onClick={() => setConfirm('shutdown')} disabled={!!pending}>
                                        <FontAwesomeIcon icon={faPowerOff} css={tw`mr-2 text-xs`} />
                                        <span>Shut down</span>
                                    </Button.Text>
                                    <Button.Danger onClick={() => setConfirm('stop')} disabled={!!pending}>
                                        <FontAwesomeIcon icon={faStop} css={tw`mr-2 text-xs`} />
                                        <span>Stop</span>
                                    </Button.Danger>
                                </>
                            )}
                        </div>
                    </div>

                    {!ready && (
                        <div className={'pd-neon'} css={tw`relative rounded-xl bg-neutral-800 px-5 py-4 mb-6`}>
                            <div css={tw`relative flex items-center gap-3`}>
                                {vps.state === 'failed' ? null : <Spinner size={'small'} />}
                                <div>
                                    <p css={tw`text-sm font-medium text-neutral-100`}>
                                        {vps.state === 'creating'
                                            ? 'Your VPS is being created.'
                                            : vps.state === 'deleting'
                                            ? 'This VPS is being deleted.'
                                            : 'The creation of this VPS did not finish.'}
                                    </p>
                                    <p css={tw`text-sm text-neutral-400 mt-0.5`}>
                                        {vps.state === 'creating'
                                            ? 'Its system is copied and set up, then it starts by itself. It takes a few minutes; this page follows it.'
                                            : vps.state === 'deleting'
                                            ? 'It will leave the panel in a moment.'
                                            : 'Open a ticket so the team can start it again.'}
                                    </p>
                                </div>
                            </div>
                        </div>
                    )}

                    {vps.rootPassword && (
                        <div className={'pd-neon'} css={tw`relative rounded-xl bg-neutral-800 p-4 mb-6`}>
                            <p css={tw`relative flex items-center gap-2 text-sm font-medium text-neutral-200`}>
                                <FontAwesomeIcon icon={faKey} css={tw`text-neutral-500 text-xs`} />
                                <span>Login</span>
                            </p>
                            <div css={tw`relative mt-3 flex flex-wrap items-center gap-2`}>
                                <span
                                    css={tw`rounded-md border border-neutral-600 px-2.5 py-1 font-mono text-sm text-neutral-100`}
                                >
                                    root
                                </span>
                                <CopyOnClick text={vps.rootPassword}>
                                    <span
                                        css={tw`cursor-pointer rounded-md border border-neutral-600 px-2.5 py-1 font-mono text-sm text-neutral-100 hover:border-neutral-500`}
                                    >
                                        {showPassword ? vps.rootPassword : '••••••••••••••••'}
                                    </span>
                                </CopyOnClick>
                                <button
                                    type={'button'}
                                    onClick={() => setShowPassword((s) => !s)}
                                    css={tw`flex items-center justify-center w-8 h-8 rounded-md text-neutral-400 hover:text-neutral-50 hover:bg-neutral-700`}
                                    aria-label={showPassword ? 'Hide the password' : 'Show the password'}
                                >
                                    <FontAwesomeIcon icon={showPassword ? faEyeSlash : faEye} css={tw`text-xs`} />
                                </button>
                            </div>
                            <p css={tw`relative mt-2 text-xs text-neutral-500`}>
                                The password set when the VPS was created: change it inside the machine (passwd). Click
                                it to copy it.
                            </p>
                        </div>
                    )}

                    {vps.error && (
                        <div
                            css={tw`mb-6 rounded-xl border border-red-500 bg-red-500/10 px-4 py-3 text-sm text-red-200`}
                        >
                            {vps.error}
                        </div>
                    )}

                    <div css={tw`grid grid-cols-2 lg:grid-cols-4 gap-3 mb-6`}>
                        <Tile
                            icon={faMicrochip}
                            label={'CPU'}
                            value={running && vps.cpu !== null ? `${vps.cpu.toFixed(1)} %` : '—'}
                            detail={vps.cores ? `${vps.cores} vCPU` : undefined}
                        />
                        <Tile
                            icon={faMemory}
                            label={'Memory'}
                            value={running && vps.memory !== null ? bytesToString(vps.memory) : '—'}
                            detail={vps.maxMemory ? `/ ${bytesToString(vps.maxMemory)}` : undefined}
                        />
                        <Tile icon={faHdd} label={'Disk'} value={vps.maxDisk ? bytesToString(vps.maxDisk) : '—'} />
                        <Tile icon={faClock} label={'Uptime'} value={running ? duration(vps.uptime) : '—'} />
                    </div>

                    <div className={'pd-neon'} css={tw`relative rounded-xl bg-neutral-800 p-4 mb-6`}>
                        <p css={tw`relative flex items-center gap-2 text-sm font-medium text-neutral-200`}>
                            <FontAwesomeIcon icon={faNetworkWired} css={tw`text-neutral-500 text-xs`} />
                            <span>IP addresses</span>
                        </p>
                        {vps.addresses.length > 0 ? (
                            <div css={tw`relative mt-3 flex flex-wrap gap-2`}>
                                {vps.addresses.map((address) => (
                                    <CopyOnClick key={address} text={address}>
                                        <span
                                            css={tw`cursor-pointer rounded-md border border-neutral-600 px-2.5 py-1 font-mono text-sm text-neutral-100 hover:border-neutral-500`}
                                        >
                                            {address}
                                        </span>
                                    </CopyOnClick>
                                ))}
                            </div>
                        ) : (
                            <p css={tw`relative mt-2 text-sm text-neutral-400`}>
                                {!running
                                    ? 'Start the machine to see its addresses.'
                                    : vps.agent
                                    ? 'The machine has not told its addresses yet: the QEMU guest agent must be running inside it.'
                                    : 'The addresses show once the QEMU guest agent is installed in the machine.'}
                            </p>
                        )}
                    </div>

                    <div css={tw`flex items-center justify-between gap-3 mb-3`}>
                        <h2 css={tw`text-base font-semibold text-neutral-50`}>History</h2>
                        <div css={tw`flex items-center gap-0.5 rounded-md border border-neutral-600 p-0.5`}>
                            {RANGES.map((item) => (
                                <button
                                    key={item.value}
                                    type={'button'}
                                    onClick={() => setRange(item.value)}
                                    css={[
                                        tw`rounded px-3 py-1 text-sm transition-colors duration-150`,
                                        range === item.value
                                            ? tw`bg-neutral-700 text-neutral-50`
                                            : tw`text-neutral-400 hover:text-neutral-100`,
                                    ]}
                                >
                                    {item.label}
                                </button>
                            ))}
                        </div>
                    </div>
                    {!history ? (
                        <div className={'pd-skeleton'} css={tw`h-44 rounded-xl`} />
                    ) : points.length < 2 ? (
                        <p css={tw`text-sm text-neutral-400`}>No history yet.</p>
                    ) : (
                        <div css={tw`grid grid-cols-1 lg:grid-cols-3 gap-3`}>
                            <Chart
                                title={'CPU'}
                                value={last?.cpu !== null && last?.cpu !== undefined ? `${last.cpu.toFixed(1)} %` : '—'}
                                series={[
                                    { points: cpuPoints, color: accentHex(400), max: Math.max(5, ...cpuPoints) * 1.2 },
                                ]}
                            />
                            <Chart
                                title={'Memory'}
                                value={last?.memory ? bytesToString(last.memory) : '—'}
                                series={[{ points: memPoints, color: '#22d3ee', max: vps.maxMemory || null }]}
                            />
                            <Chart
                                title={'Network'}
                                value={
                                    <>
                                        <span style={{ color: '#3ecf8e' }}>↓ {bytesToString(last?.netIn || 0)}/s</span>{' '}
                                        <span style={{ color: '#a78bfa' }}>↑ {bytesToString(last?.netOut || 0)}/s</span>
                                    </>
                                }
                                series={[
                                    { points: inPoints, color: '#3ecf8e', max: netMax },
                                    { points: outPoints, color: '#a78bfa', max: netMax },
                                ]}
                            />
                        </div>
                    )}

                    <Dialog.Confirm
                        open={confirm !== null}
                        title={confirm === 'stop' ? 'Stop the machine?' : 'Shut down the machine?'}
                        confirm={confirm === 'stop' ? 'Stop' : 'Shut down'}
                        onClose={() => setConfirm(null)}
                        onConfirmed={() => confirm && power(confirm)}
                    >
                        {confirm === 'stop'
                            ? 'Stopping cuts the power at once, like pulling the plug: what is not saved inside the machine is lost.'
                            : 'The system of the machine is asked to shut down cleanly. It can take a minute.'}
                    </Dialog.Confirm>
                </>
            )}
        </PageContentBlock>
    );
};
