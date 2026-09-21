import React from 'react';
import { Link, useLocation } from 'react-router-dom';
import useSWR from 'swr';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faLifeRing } from '@fortawesome/free-solid-svg-icons';
import Tooltip from '@/components/elements/tooltip/Tooltip';
import { getUnreadTickets } from '@/api/tickets';

// The way to the support: a round bubble in the corner of every page, with the number of answers not read yet.
export default () => {
    const { pathname } = useLocation();
    // The answers of the staff that were not read yet, looked at again every half minute.
    const { data: unread } = useSWR('tickets:unread', getUnreadTickets, {
        refreshInterval: 30000,
        revalidateOnFocus: true,
        shouldRetryOnError: false,
    });

    // On the pages of the support itself the bubble would only be in the way.
    if (pathname.startsWith('/tickets')) {
        return null;
    }

    return (
        <div className={'fixed bottom-5 right-5 z-40'}>
            <Tooltip placement={'left'} content={'Support'}>
                <Link
                    to={'/tickets'}
                    aria-label={'Support'}
                    className={
                        'relative flex items-center justify-center h-14 w-14 rounded-full text-white text-xl no-underline bg-gradient-to-br from-primary-400 to-primary-600 shadow-glow border border-white/20 transition-transform duration-150 hover:scale-110 active:scale-95'
                    }
                >
                    {!!unread && <span className={'absolute inset-0 rounded-full bg-primary-400/40 animate-ping'} />}
                    <FontAwesomeIcon icon={faLifeRing} className={'relative'} />
                    {!!unread && (
                        <span
                            className={
                                'absolute -top-1 -right-1 min-w-[1.25rem] h-5 px-1 rounded-full bg-red-500 border-2 border-neutral-900 text-white text-2xs leading-4 text-center font-semibold'
                            }
                        >
                            {unread}
                        </span>
                    )}
                </Link>
            </Tooltip>
        </div>
    );
};
