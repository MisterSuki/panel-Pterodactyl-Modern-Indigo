import React, { useEffect, useState } from 'react';
import { useActivityLogs } from '@/api/server/activity';
import ServerContentBlock from '@/components/elements/ServerContentBlock';
import { useFlashKey } from '@/plugins/useFlash';
import FlashMessageRender from '@/components/FlashMessageRender';
import Spinner from '@/components/elements/Spinner';
import ActivityLogList from '@/components/elements/activity/ActivityLogList';
import PaginationFooter from '@/components/elements/table/PaginationFooter';
import { ActivityLogFilters } from '@/api/account/activity';
import { Link } from 'react-router-dom';
import classNames from 'classnames';
import { XCircleIcon } from '@heroicons/react/solid';
import { ClipboardListIcon } from '@heroicons/react/outline';
import useLocationHash from '@/plugins/useLocationHash';
import { serverKinds } from '@/components/elements/activity/kinds';

const chip = (active: boolean) =>
    classNames(
        'shrink-0 rounded-full border px-3 py-1 text-xs font-semibold transition-colors duration-100',
        active
            ? 'border-primary-400 bg-primary-500 text-white'
            : 'border-white/10 bg-white/5 text-gray-300 hover:bg-white/10 hover:text-gray-50'
    );

export default () => {
    const { hash, pathTo } = useLocationHash();
    const { clearAndAddHttpError } = useFlashKey('server:activity');
    const [filters, setFilters] = useState<ActivityLogFilters>({ page: 1, sorts: { timestamp: -1 } });

    const { data, isValidating, error } = useActivityLogs(filters, {
        revalidateOnMount: true,
        revalidateOnFocus: false,
    });

    useEffect(() => {
        setFilters((value) => ({ ...value, page: 1, filters: { ip: hash.ip, event: hash.event } }));
    }, [hash]);

    useEffect(() => {
        clearAndAddHttpError(error);
    }, [error]);

    const event = filters.filters?.event;
    const inGroup = !!event && serverKinds.some((kind) => kind.event === event);

    return (
        <ServerContentBlock title={'Activity Log'}>
            <FlashMessageRender byKey={'server:activity'} />
            <div className={'mb-5 flex flex-wrap items-center gap-2'}>
                <Link to={'#'} className={chip(!event)}>
                    All
                </Link>
                {serverKinds.map((kind) => (
                    <Link
                        key={kind.event}
                        to={`#${pathTo({ event: kind.event })}`}
                        className={chip(event === kind.event)}
                    >
                        {kind.label}
                    </Link>
                ))}
                {event && !inGroup && (
                    <Link to={'#'} className={classNames(chip(true), 'inline-flex items-center gap-1.5 font-mono')}>
                        {event} <XCircleIcon className={'w-4 h-4'} />
                    </Link>
                )}
                {data && (
                    <span className={'ml-auto text-xs text-gray-400'}>
                        {data.pagination.total} <span>event(s)</span>
                    </span>
                )}
            </div>
            {!data && isValidating ? (
                <Spinner centered />
            ) : !data?.items.length ? (
                <div className={'flex flex-col items-center py-16 text-center text-gray-400'}>
                    <ClipboardListIcon className={'w-12 h-12 mb-3 text-gray-500'} />
                    <p className={'text-sm'}>
                        {event
                            ? 'Nothing of this kind happened on this server.'
                            : 'No activity logs available for this server.'}
                    </p>
                </div>
            ) : (
                <ActivityLogList items={data.items} />
            )}
            {data && data.pagination.totalPages > 1 && (
                <PaginationFooter
                    pagination={data.pagination}
                    onPageSelect={(page) => setFilters((value) => ({ ...value, page }))}
                />
            )}
        </ServerContentBlock>
    );
};
