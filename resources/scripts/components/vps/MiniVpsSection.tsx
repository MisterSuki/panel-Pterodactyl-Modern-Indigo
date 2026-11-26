import React from 'react';
import tw from 'twin.macro';
import useSWR from 'swr';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faTerminal } from '@fortawesome/free-solid-svg-icons';
import getServers from '@/api/getServers';
import getServersResourceUsage from '@/api/getServersResourceUsage';
import { ServerStats } from '@/api/server/getServerResourceUsage';
import ServerRow from '@/components/dashboard/ServerRow';

// The mini VPS of the person (a small isolated Linux in a server, see the "Mini VPS" nest): listed with the VPS, with
// the same cards as the servers. A click opens it: its Console is the terminal of the Linux.
export default ({ standalone }: { standalone: boolean }) => {
    const { data } = useSWR('mini-vps-servers', () =>
        getServers({ page: 1 }).then((page) => page.items.filter((server) => server.eggFeatures.includes('mini_vps')))
    );
    const uuids = (data || []).map((server) => server.uuid);
    const { data: usage, error } = useSWR<Record<string, ServerStats>>(
        uuids.length > 0 ? ['mini-vps-usage', ...uuids] : null,
        () => getServersResourceUsage(uuids),
        { refreshInterval: 3000, revalidateOnFocus: false, shouldRetryOnError: false }
    );

    if (!data || data.length === 0) {
        return null;
    }

    return (
        <section css={standalone ? undefined : tw`mt-10`}>
            <h2 css={tw`flex items-center gap-2 text-lg font-semibold text-neutral-100`}>
                <FontAwesomeIcon icon={faTerminal} css={tw`text-sm text-neutral-400`} />
                <span>Mini VPS</span>
            </h2>
            <p css={tw`text-sm text-neutral-400 mt-1 mb-4`}>
                A small isolated Linux in a server: open it, and type its commands in the Console (you are root in it).
            </p>
            <div css={tw`grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4`}>
                {data.map((server) => (
                    <ServerRow
                        key={server.uuid}
                        server={server}
                        stats={usage?.[server.uuid] ?? null}
                        unreachable={(usage !== undefined || !!error) && !usage?.[server.uuid]}
                    />
                ))}
            </div>
        </section>
    );
};
