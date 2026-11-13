import React, { useEffect, useRef, useState } from 'react';
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
import { getUpgradeableServers, UpgradeableServer } from '@/api/shop';
import DashboardSummary from '@/components/dashboard/DashboardSummary';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faPlus, faSearch } from '@fortawesome/free-solid-svg-icons';
import { Link } from 'react-router-dom';
import { Button } from '@/components/elements/button/index';
import classNames from 'classnames';
import serverGame from '@/components/dashboard/serverGame';

type StatusFilter = 'all' | 'online' | 'offline';

// How many readings the trend lines keep: at one every 3 seconds, the last two minutes.
const HISTORY = 40;

interface History {
    cpu: number[];
    memory: number[];
    servers: Record<string, number[]>;
}

const push = (list: number[] | undefined, value: number) => [...(list || []), value].slice(-HISTORY);

// "Good morning" until six in the evening, "Good evening" after (and in the middle of the night).
const greeting = () => {
    const hour = new Date().getHours();

    return hour >= 5 && hour < 18 ? 'Good morning' : 'Good evening';
};

// Today, in the language of the page: "vendredi 13 novembre".
const today = () => {
    try {
        const text = new Intl.DateTimeFormat(document.documentElement.lang || undefined, {
            weekday: 'long',
            day: 'numeric',
            month: 'long',
        }).format(new Date());

        return text.charAt(0).toUpperCase() + text.slice(1);
    } catch {
        return '';
    }
};

export default () => {
    const { search } = useLocation();
    const defaultPage = Number(new URLSearchParams(search).get('page') || '1');

    const [page, setPage] = useState(!isNaN(defaultPage) && defaultPage > 0 ? defaultPage : 1);
    const { clearFlashes, clearAndAddHttpError } = useFlash();
    const uuid = useStoreState((state) => state.user.data!.uuid);
    const username = useStoreState((state) => state.user.data!.username);
    const rootAdmin = useStoreState((state) => state.user.data!.rootAdmin);
    const shopOpen = useStoreState((state) => !!state.settings.data?.shop?.enabled);
    const [showOnlyAdmin, setShowOnlyAdmin] = usePersistedState(`${uuid}:show_all_servers`, false);
    const [query, setQuery] = useState('');
    const [filter, setFilter] = useState<StatusFilter>('all');
    const searchInput = useRef<HTMLInputElement>(null);

    // "/" puts the cursor in the search, like on GitHub; Escape empties it.
    useEffect(() => {
        const onKey = (e: KeyboardEvent) => {
            const target = e.target as HTMLElement;
            if (e.key !== '/' || /input|textarea|select/i.test(target.tagName) || target.isContentEditable) return;
            e.preventDefault();
            searchInput.current?.focus();
        };
        document.addEventListener('keydown', onKey);

        return () => document.removeEventListener('keydown', onKey);
    }, []);

    const { data: servers, error } = useSWR<PaginatedResult<Server>>(
        ['/api/client/servers', showOnlyAdmin && rootAdmin, page],
        () => getServers({ page, type: showOnlyAdmin && rootAdmin ? 'admin' : undefined })
    );

    // The usage of every server of the page, in one request every 3 seconds (paused while the tab is hidden).
    // Suspended servers and servers on a node under maintenance have nothing to show.
    const liveUuids = servers?.items
        .filter((server) => server.status !== 'suspended' && !server.isNodeUnderMaintenance)
        .map((server) => server.uuid);
    const { data: usage, error: usageError } = useSWR<Record<string, ServerStats>>(
        liveUuids && liveUuids.length > 0 ? ['dashboard-usage', ...liveUuids] : null,
        () => getServersResourceUsage(liveUuids!),
        { refreshInterval: 3000, dedupingInterval: 1500, revalidateOnFocus: false, shouldRetryOnError: false }
    );

    // Every answer adds a point to the trend lines: of all the servers together, and of each server's CPU.
    const [history, setHistory] = useState<History>({ cpu: [], memory: [], servers: {} });
    useEffect(() => {
        if (!usage) return;
        const stats = Object.values(usage);
        setHistory((previous) => ({
            cpu: push(
                previous.cpu,
                stats.reduce((sum, s) => sum + s.cpuUsagePercent, 0)
            ),
            memory: push(
                previous.memory,
                stats.reduce((sum, s) => sum + s.memoryUsageInBytes, 0)
            ),
            servers: Object.fromEntries(
                Object.entries(usage).map(([id, s]) => [
                    id,
                    push(previous.servers[id], s.status === 'running' ? s.cpuUsagePercent : 0),
                ])
            ),
        }));
    }, [usage]);

    // Once the first answer is in (or has failed), a server that is missing from it could not be reached.
    const usageSettled = usage !== undefined || !!usageError;

    // The servers whose resources the person may change (bought in the shop). Fetched once; empty when the feature is off.
    const { data: upgradeable } = useSWR<UpgradeableServer[]>('shop-upgradeable', getUpgradeableServers, {
        revalidateOnFocus: false,
        shouldRetryOnError: false,
    });
    const shopServers = new Map((upgradeable || []).map((s) => [s.identifier, s]));

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
            <div css={tw`mb-8 flex flex-wrap items-end justify-between gap-4`}>
                <div css={tw`min-w-0`}>
                    <p css={tw`text-xs font-medium uppercase tracking-wider text-neutral-500`}>{today()}</p>
                    <h1 css={tw`mt-1.5 text-3xl font-semibold text-neutral-50 tracking-tight truncate`}>
                        {showOnlyAdmin ? (
                            'All servers'
                        ) : (
                            <>
                                <span>{greeting()}</span>, <span className={'pd-gradient-text'}>{username}</span>
                            </>
                        )}
                    </h1>
                    <p css={tw`text-sm text-neutral-400 mt-1.5`}>
                        Open a server to manage its console, files and settings.
                    </p>
                </div>
                <div css={tw`flex items-center gap-4`}>
                    {rootAdmin && (
                        <div css={tw`flex items-center`}>
                            <p css={tw`text-sm text-neutral-400 mr-2`}>
                                {showOnlyAdmin ? "Showing others' servers" : 'Showing your servers'}
                            </p>
                            <Switch
                                name={'show_all_servers'}
                                defaultChecked={showOnlyAdmin}
                                onChange={() => setShowOnlyAdmin((s) => !s)}
                            />
                        </div>
                    )}
                    {shopOpen && (
                        <Link to={'/shop'} css={tw`no-underline`}>
                            <Button type={'button'}>
                                <FontAwesomeIcon icon={faPlus} css={tw`mr-2 text-xs`} />
                                <span>New server</span>
                            </Button>
                        </Link>
                    )}
                </div>
            </div>
            {servers && (
                <DashboardSummary
                    total={servers.pagination.total}
                    servers={servers.items}
                    usage={usage}
                    history={history}
                />
            )}
            {servers && servers.items.length > 3 && (
                <div css={tw`flex flex-wrap items-center gap-2 mb-4`}>
                    <div css={tw`relative flex-1 min-w-[12rem]`}>
                        <FontAwesomeIcon
                            icon={faSearch}
                            css={tw`absolute left-3 inset-y-0 my-auto text-neutral-500 text-sm`}
                        />
                        <input
                            ref={searchInput}
                            type={'search'}
                            value={query}
                            onChange={(e) => setQuery(e.target.value)}
                            onKeyDown={(e) => e.key === 'Escape' && setQuery('')}
                            placeholder={'Search a server...'}
                            aria-label={'Search a server...'}
                            css={tw`w-full h-9 rounded-md border border-neutral-600 bg-neutral-900 pl-9 pr-10 text-sm text-neutral-100 placeholder-neutral-500 outline-none focus:border-neutral-500 focus:ring-0`}
                        />
                        {!query && (
                            <kbd
                                css={tw`hidden sm:block absolute right-3 top-1/2 -translate-y-1/2 rounded border border-neutral-600 px-1.5 text-2xs text-neutral-500 pointer-events-none`}
                            >
                                /
                            </kbd>
                        )}
                    </div>
                    <div css={tw`flex items-center gap-0.5 rounded-md border border-neutral-600 p-0.5`}>
                        {(['all', 'online', 'offline'] as StatusFilter[]).map((value) => (
                            <button
                                key={value}
                                type={'button'}
                                onClick={() => setFilter(value)}
                                className={classNames(
                                    'rounded px-3 py-1 text-sm transition-colors duration-150',
                                    filter === value
                                        ? 'bg-neutral-700 text-neutral-50'
                                        : 'text-neutral-400 hover:text-neutral-100'
                                )}
                            >
                                {value === 'all' ? 'All' : value === 'online' ? 'Online' : 'Offline'}
                            </button>
                        ))}
                    </div>
                </div>
            )}
            {!servers ? (
                <div
                    aria-busy={'true'}
                    aria-label={'Loading servers'}
                    css={tw`grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4`}
                >
                    {[0, 1, 2].map((i) => (
                        <div key={i} css={tw`h-52 rounded-2xl border border-neutral-700 bg-neutral-800 p-5`}>
                            <div css={tw`flex items-center gap-3`}>
                                <div className={'pd-skeleton'} css={tw`w-9 h-9 rounded-lg`} />
                                <div css={tw`flex-1 space-y-2`}>
                                    <div className={'pd-skeleton'} css={tw`h-3 w-1/2 rounded`} />
                                    <div className={'pd-skeleton'} css={tw`h-2.5 w-1/4 rounded`} />
                                </div>
                            </div>
                            <div className={'pd-skeleton'} css={tw`mt-5 h-2.5 w-2/5 rounded`} />
                            <div css={tw`mt-8 pt-4 border-t border-neutral-700 grid grid-cols-3 gap-4`}>
                                {[0, 1, 2].map((j) => (
                                    <div key={j} css={tw`space-y-2`}>
                                        <div className={'pd-skeleton'} css={tw`h-2 w-1/2 rounded`} />
                                        <div className={'pd-skeleton'} css={tw`h-3 w-3/4 rounded`} />
                                        <div className={'pd-skeleton'} css={tw`h-1 w-full rounded-full`} />
                                    </div>
                                ))}
                            </div>
                        </div>
                    ))}
                </div>
            ) : (
                <Pagination data={servers} onPageSelect={setPage}>
                    {({ items: all }) => {
                        const needle = query.trim().toLowerCase();
                        const items = all.filter((server) => {
                            const running = usage?.[server.uuid]?.status === 'running';
                            const offline = usage?.[server.uuid]?.status === 'offline';

                            // The name, the description, the game and the address (with its port) are searched.
                            const text = [
                                server.name,
                                server.description || '',
                                serverGame(server).name,
                                ...server.allocations.map((a) => `${a.alias || a.ip}:${a.port}`),
                            ]
                                .join(' ')
                                .toLowerCase();

                            return (
                                (filter === 'all' || (filter === 'online' ? running : offline)) &&
                                (needle === '' || text.includes(needle))
                            );
                        });

                        return all.length > 0 && items.length === 0 ? (
                            <p css={tw`text-center text-sm text-neutral-400`}>No server matches your search.</p>
                        ) : items.length > 0 ? (
                            <div css={tw`grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4`}>
                                {items.map((server, index) => (
                                    <ServerRow
                                        key={server.uuid}
                                        server={server}
                                        stats={usage?.[server.uuid] ?? null}
                                        cpuHistory={history.servers[server.uuid]}
                                        unreachable={usageSettled && !usage?.[server.uuid]}
                                        upgradeable={shopServers.has(server.id)}
                                        billing={shopServers.get(server.id)}
                                        style={{ animationDelay: `${Math.min(index, 8) * 40}ms` }}
                                    />
                                ))}
                            </div>
                        ) : (
                            <p css={tw`text-center text-sm text-neutral-400`}>
                                {showOnlyAdmin
                                    ? 'There are no other servers to display.'
                                    : 'There are no servers associated with your account.'}
                            </p>
                        );
                    }}
                </Pagination>
            )}
        </PageContentBlock>
    );
};
