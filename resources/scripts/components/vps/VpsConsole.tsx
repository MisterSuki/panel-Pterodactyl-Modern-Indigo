import React from 'react';
import tw from 'twin.macro';
import useSWR from 'swr';
import { Link, useParams } from 'react-router-dom';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faArrowLeft } from '@fortawesome/free-solid-svg-icons';
import PageContentBlock from '@/components/elements/PageContentBlock';
import VpsScreen from '@/components/vps/VpsScreen';
import VpsStatusPill from '@/components/vps/VpsStatusPill';
import { getVps, VpsDetails } from '@/api/vps';

// The screen of a VPS, large, on a page of its own.
export default () => {
    const { uuid } = useParams<{ uuid: string }>();
    const { data: vps } = useSWR<VpsDetails>(['vps', uuid], () => getVps(uuid), {
        refreshInterval: 10000,
        revalidateOnFocus: false,
    });
    const active = !vps || (vps.state === 'ready' && vps.status === 'running');

    return (
        <PageContentBlock title={vps ? `${vps.name} · Console` : 'Console'}>
            <div css={tw`flex items-center gap-4 mb-4 min-w-0`}>
                <Link
                    to={`/vps/${uuid}`}
                    css={tw`inline-flex items-center gap-2 text-sm text-neutral-400 hover:text-neutral-100 no-underline`}
                >
                    <FontAwesomeIcon icon={faArrowLeft} css={tw`text-xs`} />
                    <span>Back</span>
                </Link>
                {vps && (
                    <span css={tw`flex items-center gap-3 min-w-0`}>
                        <span css={tw`text-sm font-semibold text-neutral-50 truncate`}>{vps.name}</span>
                        <VpsStatusPill status={vps.status} state={vps.state} />
                    </span>
                )}
            </div>
            <VpsScreen uuid={uuid} active={active} height={'calc(100vh - 230px)'} />
        </PageContentBlock>
    );
};
