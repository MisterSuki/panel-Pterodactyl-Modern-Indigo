import React, { useEffect } from 'react';
import tw from 'twin.macro';
import useSWR from 'swr';
import { Link } from 'react-router-dom';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faDesktop } from '@fortawesome/free-solid-svg-icons';
import PageContentBlock from '@/components/elements/PageContentBlock';
import GreyRowBox from '@/components/elements/GreyRowBox';
import { getVpsList, Vps } from '@/api/vps';
import { bytesToString } from '@/lib/formatters';
import useFlash from '@/plugins/useFlash';
import VpsStatusPill from '@/components/vps/VpsStatusPill';

const Meter = ({ label, value, ratio }: { label: string; value: string; ratio: number | null }) => {
    const percent = ratio === null ? 0 : Math.max(0, Math.min(1, ratio)) * 100;

    return (
        <div css={tw`min-w-0`}>
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

const Card = ({ vps }: { vps: Vps }) => {
    const running = vps.status === 'running';

    return (
        <GreyRowBox as={Link as React.ElementType} to={`/vps/${vps.uuid}`} css={tw`flex-col items-stretch p-5`}>
            <div css={tw`flex items-center gap-3 min-w-0`}>
                <div
                    className={'icon'}
                    css={tw`flex-shrink-0 w-9 h-9 border-0 p-0 text-white text-sm`}
                    style={{ background: 'rgb(var(--pd-accent-600))' }}
                >
                    <FontAwesomeIcon icon={faDesktop} />
                </div>
                <div css={tw`min-w-0 flex-1`}>
                    <p css={tw`text-sm font-semibold text-neutral-50 truncate`}>{vps.name}</p>
                    <p css={tw`text-xs text-neutral-500 truncate`}>
                        VPS{vps.cores ? ` · ${vps.cores} vCPU` : ''}
                        {vps.maxDisk ? ` · ${bytesToString(vps.maxDisk)}` : ''}
                    </p>
                </div>
                <VpsStatusPill status={vps.status} />
            </div>
            <div css={tw`mt-5 pt-4 border-t border-neutral-700 grid grid-cols-2 gap-4`}>
                <Meter
                    label={'CPU'}
                    value={running && vps.cpu !== null ? `${vps.cpu.toFixed(1)} %` : '—'}
                    ratio={running && vps.cpu !== null ? vps.cpu / 100 : null}
                />
                <Meter
                    label={'Memory'}
                    value={running && vps.memory !== null ? bytesToString(vps.memory) : '—'}
                    ratio={running && vps.memory !== null && vps.maxMemory ? vps.memory / vps.maxMemory : null}
                />
            </div>
        </GreyRowBox>
    );
};

// The VPS of the person: virtual machines of Proxmox given to them by the administration, refreshed every 5 seconds.
export default () => {
    const { clearFlashes, clearAndAddHttpError } = useFlash();
    const { data, error } = useSWR<Vps[]>('vps-list', getVpsList, { refreshInterval: 5000, revalidateOnFocus: false });

    useEffect(() => {
        if (error) clearAndAddHttpError({ key: 'vps', error });
        else clearFlashes('vps');
    }, [error]);

    return (
        <PageContentBlock title={'VPS'} showFlashKey={'vps'}>
            <div css={tw`mb-8`}>
                <h1 css={tw`text-3xl font-semibold text-neutral-50 tracking-tight`}>VPS</h1>
                <p css={tw`text-sm text-neutral-400 mt-1.5`}>
                    Your virtual machines: their state, what they use, and their power.
                </p>
            </div>
            {!data ? (
                <div css={tw`grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4`}>
                    {[0, 1].map((i) => (
                        <div key={i} className={'pd-skeleton'} css={tw`h-40 rounded-2xl`} />
                    ))}
                </div>
            ) : data.length === 0 ? (
                <div css={tw`rounded-2xl border border-neutral-700 bg-neutral-800 p-10 text-center`}>
                    <FontAwesomeIcon icon={faDesktop} css={tw`text-2xl text-neutral-500`} />
                    <p css={tw`mt-3 text-sm text-neutral-300`}>You do not have a VPS yet.</p>
                </div>
            ) : (
                <div css={tw`grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4`}>
                    {data.map((vps) => (
                        <Card key={vps.uuid} vps={vps} />
                    ))}
                </div>
            )}
        </PageContentBlock>
    );
};
