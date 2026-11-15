import React, { useEffect, useState } from 'react';
import { faWifi } from '@fortawesome/free-solid-svg-icons';
import { ServerContext } from '@/state/server';
import { getSubdomains } from '@/api/server/subdomains';
import { ip } from '@/lib/formatters';
import Icon from '@/components/elements/Icon';
import CopyOnClick from '@/components/elements/CopyOnClick';
import HiddenAddress from '@/components/elements/HiddenAddress';

// The address players type, under the name of the server: a name that leads to the main address (an own domain that
// points at the server, or a subdomain of the panel) when there is one, otherwise the IP and port, blurred until the
// eye is clicked. A click copies it (always the real address, even while it is hidden).
export default () => {
    const uuid = ServerContext.useStoreState((state) => state.server.data?.uuid);
    const main = ServerContext.useStoreState((state) =>
        state.server.data?.allocations.find((allocation) => allocation.isDefault)
    );
    const host = main ? main.alias || ip(main.ip) : null;
    const port = main?.port;
    const allocationId = main?.id;

    const [hostname, setHostname] = useState<string | null>(null);
    useEffect(() => {
        if (!uuid) return;
        let alive = true;
        getSubdomains(uuid)
            .then((page) => {
                if (!alive) return;
                const own = page.custom.data.find((d) => d.verified && d.allocationId === allocationId);
                const sub = page.data.find((d) => d.allocationId === allocationId);
                setHostname(own?.hostname || sub?.fqdn || null);
            })
            .catch(() => alive && setHostname(null));

        return () => {
            alive = false;
        };
    }, [uuid, allocationId]);

    if (host === null || port === undefined) {
        return null;
    }

    // Only the name: the SRV record (or the default port of the game) takes players to the right port.
    const copied = hostname ?? `${host}:${port}`;

    return (
        <CopyOnClick text={copied}>
            <div
                className={
                    'mt-1.5 flex items-center gap-1.5 min-w-0 text-xs text-gray-400 hover:text-gray-200 cursor-pointer transition-colors duration-150'
                }
                title={hostname ? `${host}:${port}` : undefined}
            >
                <Icon icon={faWifi} className={'w-3 h-3 flex-shrink-0 text-gray-500'} />
                {hostname ? (
                    <span className={'truncate'}>{hostname}</span>
                ) : (
                    <HiddenAddress host={host} port={port} className={'min-w-0'} />
                )}
            </div>
        </CopyOnClick>
    );
};
