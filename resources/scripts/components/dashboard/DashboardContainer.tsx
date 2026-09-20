import React, { useEffect, useState } from 'react';
import { Server } from '@/api/server/getServer';
import getServers from '@/api/getServers';
import ServerRow from '@/components/dashboard/ServerRow';
import PageContentBlock from '@/components/elements/PageContentBlock';
import useFlash from '@/plugins/useFlash';
import { useStoreState } from 'easy-peasy';
import { usePersistedState } from '@/plugins/usePersistedState';
import Switch from '@/components/elements/Switch';
import tw from 'twin.macro';
import useSWR from 'swr';
import { PaginatedResult } from '@/api/http';
import Pagination from '@/components/elements/Pagination';
import { useLocation } from 'react-router-dom';
import getServersResourceUsage from '@/api/getServersResourceUsage';
import { ServerStats } from '@/api/server/getServerResourceUsage';

export default () => {
    const { search } = useLocation();
    const defaultPage = Number(new URLSearchParams(search).get('page') || '1');

    const [page, setPage] = useState(!isNaN(defaultPage) && defaultPage > 0 ? defaultPage : 1);
    const { clearFlashes, clearAndAddHttpError } = useFlash();
    const uuid = useStoreState((state) => state.user.data!.uuid);
    const rootAdmin = useStoreState((state) => state.user.data!.rootAdmin);
    const [showOnlyAdmin, setShowOnlyAdmin] = usePersistedState(`${uuid}:show_all_servers`, false);

    const { data: servers, error } = useSWR<PaginatedResult<Server>>(
        ['/api/client/servers', showOnlyAdmin && rootAdmin, page],
        () => getServers({ page, type: showOnlyAdmin && rootAdmin ? 'admin' : undefined })
    );

    // The usage of every server of the page, in one request every 3 seconds (paused while the tab is hidden).
    // Suspended servers and servers on a node under maintenance have nothing to show.
    const liveUuids = servers?.items
        .filter((server) => server.status !== 'suspended' && !server.isNodeUnderMaintenance)
        .map((server) => server.uuid);
    const { data: usage } = useSWR<Record<string, ServerStats>>(
        liveUuids && liveUuids.length > 0 ? ['dashboard-usage', ...liveUuids] : null,
        () => getServersResourceUsage(liveUuids!),
        { refreshInterval: 3000, dedupingInterval: 1500, revalidateOnFocus: false, shouldRetryOnError: false }
    );

    useEffect(() => {
        setPage(1);
    }, [showOnlyAdmin]);

    useEffect(() => {
        if (!servers) return;
        if (servers.pagination.currentPage > 1 && !servers.items.length) {
            setPage(1);
        }
    }, [servers?.pagination.currentPage]);

    useEffect(() => {
        // Don't use react-router to handle changing this part of the URL, otherwise it
        // triggers a needless re-render. We just want to track this in the URL incase the
        // user refreshes the page.
        window.history.replaceState(null, document.title, `/${page <= 1 ? '' : `?page=${page}`}`);
    }, [page]);

    useEffect(() => {
        if (error) clearAndAddHttpError({ key: 'dashboard', error });
        if (!error) clearFlashes('dashboard');
    }, [error]);

    return (
        <PageContentBlock title={'Dashboard'} showFlashKey={'dashboard'}>
            <div css={tw`mb-5 flex flex-wrap items-end justify-between gap-3`}>
                <div>
                    <h1 css={tw`text-2xl font-semibold text-neutral-50 flex items-center gap-3`}>
                        {showOnlyAdmin ? 'All servers' : 'Your servers'}
                        {servers && (
                            <span
                                css={tw`rounded-full bg-primary-500/20 border border-primary-500/30 px-2.5 py-0.5 text-xs font-medium text-primary-300`}
                            >
                                {servers.pagination.total}
                            </span>
                        )}
                    </h1>
                    <p css={tw`text-sm text-neutral-400 mt-1`}>
                        Open a server to manage its console, files and settings.
                    </p>
                </div>
                {rootAdmin && (
                    <div css={tw`flex items-center`}>
                        <p css={tw`uppercase text-xs text-neutral-400 mr-2`}>
                            {showOnlyAdmin ? "Showing others' servers" : 'Showing your servers'}
                        </p>
                        <Switch
                            name={'show_all_servers'}
                            defaultChecked={showOnlyAdmin}
                            onChange={() => setShowOnlyAdmin((s) => !s)}
                        />
                    </div>
                )}
            </div>
            {!servers ? (
                <div aria-busy={'true'} aria-label={'Loading servers'}>
                    {[0, 1, 2].map((i) => (
                        <div
                            key={i}
                            css={tw`h-[5.5rem] mb-2 rounded-xl border border-white/5 bg-neutral-800/60 animate-pulse`}
                            style={{ animationDelay: `${i * 120}ms` }}
                        />
                    ))}
                </div>
            ) : (
                <Pagination data={servers} onPageSelect={setPage}>
                    {({ items }) =>
                        items.length > 0 ? (
                            items.map((server, index) => (
                                <ServerRow
                                    key={server.uuid}
                                    server={server}
                                    stats={usage?.[server.uuid] ?? null}
                                    css={index > 0 ? tw`mt-2` : undefined}
                                    style={{ animationDelay: `${Math.min(index, 8) * 45}ms` }}
                                />
                            ))
                        ) : (
                            <p css={tw`text-center text-sm text-neutral-400`}>
                                {showOnlyAdmin
                                    ? 'There are no other servers to display.'
                                    : 'There are no servers associated with your account.'}
                            </p>
                        )
                    }
                </Pagination>
            )}
        </PageContentBlock>
    );
};
