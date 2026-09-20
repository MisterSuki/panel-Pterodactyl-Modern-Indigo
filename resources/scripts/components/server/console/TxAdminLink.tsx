import React from 'react';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faExclamationTriangle, faExternalLinkAlt } from '@fortawesome/free-solid-svg-icons';
import classNames from 'classnames';
import useFiveM from '@/components/server/console/useFiveM';

// A link that opens txAdmin, the web panel of a FiveM server. Nothing is shown for other servers.
export default ({ className }: { className?: string }) => {
    const { data } = useFiveM();

    if (!data?.txadmin.enabled || !data.txadmin.url) {
        return null;
    }

    const unreachable = !data.txadmin.portAllocated;

    return (
        <a
            href={data.txadmin.url}
            target={'_blank'}
            rel={'noopener noreferrer'}
            title={
                unreachable
                    ? `txAdmin listens on port ${data.txadmin.port}, which is not one of this server's allocations. Add it in the Network tab, or txAdmin cannot be reached from outside.`
                    : 'Open txAdmin'
            }
            className={classNames(
                'inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-xs font-medium no-underline transition-colors duration-150',
                unreachable
                    ? 'bg-yellow-500/10 border-yellow-500/30 text-yellow-300 hover:bg-yellow-500/20'
                    : 'bg-primary-500/10 border-primary-500/30 text-primary-300 hover:bg-primary-500/20',
                className
            )}
        >
            txAdmin
            <FontAwesomeIcon icon={unreachable ? faExclamationTriangle : faExternalLinkAlt} className={'w-3 h-3'} />
        </a>
    );
};
