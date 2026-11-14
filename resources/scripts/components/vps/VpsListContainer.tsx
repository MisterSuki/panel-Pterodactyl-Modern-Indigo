import React, { useEffect } from 'react';
import tw from 'twin.macro';
import useSWR from 'swr';
import { Link } from 'react-router-dom';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faChevronRight, faDesktop } from '@fortawesome/free-solid-svg-icons';
import PageContentBlock from '@/components/elements/PageContentBlock';
import GreyRowBox from '@/components/elements/GreyRowBox';
import { getVpsList, Vps } from '@/api/vps';
import { bytesToString } from '@/lib/formatters';
import useFlash from '@/plugins/useFlash';
import VpsStatusPill from '@/components/vps/VpsStatusPill';
import VpsMeter from '@/components/vps/VpsMeter';

// How long the machine has been running: "3 j 4 h", "2 h 12 min" or "5 min".
const uptime = (seconds: number): string => {
    const minutes = Math.floor(seconds / 60);
    const hours = Math.floor(minutes / 60);
    const days = Math.floor(hours / 24);
    if (days > 0) return `${days} j ${hours % 24} h`;
    if (hours > 0) return `${hours} h ${minutes % 60} min`;

    return `${Math.max(1, minutes)} min`;
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
                        {[
                            vps.cores ? `${vps.cores} vCPU` : null,
                            vps.maxMemory ? `${bytesToString(vps.maxMemory)} RAM` : null,
                            vps.maxDisk ? `${bytesToString(vps.maxDisk)} SSD` : null,
                        ]
                            .filter(Boolean)
                            .join(' · ') || 'VPS'}
                    </p>
                </div>
                {!vps.owner && (
                    <span
                        css={tw`flex-shrink-0 rounded-full border border-neutral-600 px-2 py-0.5 text-xs text-neutral-400`}
                    >
                        Shared
                    </span>
                )}
                <VpsStatusPill status={vps.status} state={vps.state} />
            </div>
            <div css={tw`mt-5 pt-4 border-t border-neutral-700 grid grid-cols-2 gap-4`}>
                <VpsMeter
                    label={'CPU'}
                    value={running && vps.cpu !== null ? `${vps.cpu.toFixed(1)} %` : '—'}
                    ratio={running && vps.cpu !== null ? vps.cpu / 100 : null}
                />
                <VpsMeter
                    label={'Memory'}
                    value={running && vps.memory !== null ? bytesToString(vps.memory) : '—'}
                    ratio={running && vps.memory !== null && vps.maxMemory ? vps.memory / vps.maxMemory : null}
                />
            </div>
            <div css={tw`mt-4 flex items-center justify-between gap-3 text-xs text-neutral-500`}>
                <span css={tw`truncate`}>
                    {running && vps.uptime ? (
                        <>
                            <span>Running for</span> {uptime(vps.uptime)}
                        </>
                    ) : (
                        <span>Machine off</span>
                    )}
                </span>
                <span css={tw`inline-flex items-center gap-1.5 text-neutral-400`}>
                    <span>See the VPS</span>
                    <FontAwesomeIcon icon={faChevronRight} css={tw`text-xs`} />
                </span>
            </div>
        </GreyRowBox>
    );
};

// The VPS of the person: virtual machines of Proxmox given to them by the administration, refreshed every 5 seconds.
export default () => {
    const { clearFlashes, clearAndAddHttpError } = useFlash();
    const { data, error } = useSWR<Vps[]>('vps-list', getVpsList, { refreshInterval: 3000, revalidateOnFocus: false });

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
