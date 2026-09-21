import React from 'react';
import { formatDistanceToNow } from 'date-fns';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faClock, faInfoCircle } from '@fortawesome/free-solid-svg-icons';
import tw from 'twin.macro';
import { Server } from '@/api/server/getServer';

const formatDate = (date: Date): string =>
    new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(date);

// What the owner is told about a suspension: the reason, since when, and when access comes back by itself.
export default ({ suspension }: { suspension: Server['suspension'] }) => {
    if (!suspension) {
        return null;
    }

    const ended = suspension.until !== null && suspension.until.getTime() <= Date.now();

    return (
        <div css={tw`mt-8 text-left`}>
            {suspension.reason && (
                <div css={tw`rounded-xl border border-red-500/30 bg-red-500/10 px-4 py-3`}>
                    <p css={tw`text-xs uppercase tracking-wider text-red-300/80 mb-1`}>Reason</p>
                    <p css={tw`text-sm text-neutral-100 break-words whitespace-pre-line`}>{suspension.reason}</p>
                </div>
            )}
            <div css={tw`mt-4 space-y-2 text-sm text-neutral-300`}>
                {suspension.since && (
                    <p css={tw`flex items-center gap-2`}>
                        <FontAwesomeIcon icon={faInfoCircle} css={tw`text-neutral-500`} fixedWidth />
                        Suspended on {formatDate(suspension.since)}
                    </p>
                )}
                <p css={tw`flex items-center gap-2`}>
                    <FontAwesomeIcon icon={faClock} css={tw`text-neutral-500`} fixedWidth />
                    {suspension.until === null
                        ? 'It stays suspended until an administrator lifts the suspension.'
                        : ended
                        ? 'The suspension is about to be lifted.'
                        : `Access comes back by itself on ${formatDate(suspension.until)} (${formatDistanceToNow(
                              suspension.until,
                              { addSuffix: true }
                          )}).`}
                </p>
            </div>
        </div>
    );
};
