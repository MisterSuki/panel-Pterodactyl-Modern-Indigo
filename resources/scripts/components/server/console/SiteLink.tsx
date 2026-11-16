import React from 'react';
import useSWR from 'swr';
import isEqual from 'react-fast-compare';
import { Link } from 'react-router-dom';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faExternalLinkAlt, faGlobe } from '@fortawesome/free-solid-svg-icons';
import classNames from 'classnames';
import { ServerContext } from '@/state/server';
import { getWeb } from '@/api/server/web';

const pill =
    'inline-flex items-center gap-2 h-8 rounded-full border pl-2.5 pr-3 text-xs font-semibold no-underline transition-all duration-150 shadow-[inset_0_1px_0_rgba(255,255,255,0.18)] hover:-translate-y-px';

// The button that opens a website (PHP, Node.js, Python, Emby...) from its console: its domain, or its temporary
// address while no domain leads to it. Without either, it leads to the Website tab to add a domain. Nothing is shown
// for other servers, or for people who may not see the domains of the site.
export default ({ className }: { className?: string }) => {
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const id = ServerContext.useStoreState((state) => state.server.data!.id);
    const features = ServerContext.useStoreState((state) => state.server.data!.eggFeatures, isEqual);
    const status = ServerContext.useStoreState((state) => state.status.value);
    const isWebsite = features.includes('web');

    const { data, error } = useSWR(isWebsite ? ['web-link', uuid] : null, () => getWeb(uuid), {
        revalidateOnFocus: false,
        shouldRetryOnError: false,
    });

    if (!isWebsite || error || !data) {
        return null;
    }

    const url = data.url || data.preview;
    const up = status === 'running' || status === 'starting';

    if (!url) {
        return (
            <Link
                to={`/server/${id}/web`}
                className={classNames(
                    pill,
                    'bg-yellow-500/10 border-yellow-500/30 text-yellow-200 hover:bg-yellow-500/20',
                    className
                )}
            >
                <FontAwesomeIcon icon={faGlobe} className={'w-3 h-3'} />
                <span>Add a domain</span>
            </Link>
        );
    }

    return (
        <a
            href={url}
            target={'_blank'}
            rel={'noopener noreferrer'}
            title={url}
            className={classNames(
                pill,
                'bg-gradient-to-b from-white/[0.12] to-white/[0.04] border-white/15 text-gray-50 hover:from-white/[0.18] hover:to-white/[0.08]',
                className
            )}
        >
            <span
                className={'flex items-center justify-center w-5 h-5 rounded-full text-white'}
                style={{ background: 'linear-gradient(135deg, #38bdf8, #0284c7)' }}
            >
                <FontAwesomeIcon icon={faGlobe} className={'w-2.5 h-2.5'} />
            </span>
            <span>Open the site</span>
            <span className={classNames('w-1.5 h-1.5 rounded-full', up ? 'bg-green-400' : 'bg-gray-500')} />
            <FontAwesomeIcon icon={faExternalLinkAlt} className={'w-3 h-3 opacity-60'} />
        </a>
    );
};
