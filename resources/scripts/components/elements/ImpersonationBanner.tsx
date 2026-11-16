import React, { useState } from 'react';
import { useStoreState } from 'easy-peasy';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faArrowLeft, faUserSecret } from '@fortawesome/free-solid-svg-icons';
import http from '@/api/http';
import { ApplicationStore } from '@/state';

declare global {
    interface Window {
        PterodactylImpersonating?: boolean;
    }
}

// Shown while an administrator uses the account of a user: whose account it is, and the way back to their own.
export default () => {
    const username = useStoreState((state: ApplicationStore) => state.user.data?.username || '');
    const [busy, setBusy] = useState(false);

    if (!window.PterodactylImpersonating) {
        return null;
    }

    const back = () => {
        setBusy(true);
        http.post('/auth/impersonate/stop')
            .then(({ data }) => {
                window.location.href = data.redirect || '/admin';
            })
            .catch(() => {
                window.location.href = '/admin';
            });
    };

    return (
        <div
            className={
                'relative z-50 flex flex-wrap items-center justify-center gap-x-4 gap-y-1 px-4 py-2 text-sm text-yellow-50'
            }
            style={{ background: 'linear-gradient(90deg, #b45309, #d97706)' }}
        >
            <span className={'flex items-center gap-2'}>
                <FontAwesomeIcon icon={faUserSecret} />
                <span>You are using the account of</span>
                <strong>{username}</strong>
            </span>
            <button
                type={'button'}
                onClick={back}
                disabled={busy}
                className={
                    'inline-flex items-center gap-2 rounded-md bg-black/25 px-3 py-1 font-semibold hover:bg-black/40 transition-colors duration-150 disabled:opacity-60'
                }
            >
                <FontAwesomeIcon icon={faArrowLeft} />
                <span>Back to my account</span>
            </button>
        </div>
    );
};
