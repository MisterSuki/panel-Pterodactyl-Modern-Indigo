import React, { useCallback, useEffect, useState } from 'react';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faDesktop, faStop } from '@fortawesome/free-solid-svg-icons';
import http, { httpErrorToHuman } from '@/api/http';

// How often the page asks whether somebody wants to see the screen.
const POLL_MS = 4000;

const ICE_SERVERS = [{ urls: 'stun:stun.l.google.com:19302' }];

interface Session {
    id: string;
    state: 'requested' | 'offered' | 'connected' | 'declined' | 'stopped';
    admin: string;
    answer: string | null;
}

// Waits until the browser has found all the ways to reach it, so that one message carries everything.
const gathered = (peer: RTCPeerConnection): Promise<void> =>
    new Promise((resolve) => {
        if (peer.iceGatheringState === 'complete') {
            resolve();

            return;
        }
        const done = () => {
            if (peer.iceGatheringState === 'complete') {
                peer.removeEventListener('icegatheringstatechange', done);
                resolve();
            }
        };
        peer.addEventListener('icegatheringstatechange', done);
        window.setTimeout(resolve, 4000);
    });

// Every line of a description ends with a line break, the last one too.
const withLineBreaks = (sdp: string): string => sdp.replace(/\r?\n/g, '\r\n').replace(/(\r\n)*$/, '\r\n');

// What is being shared lives outside of the component: the bar at the top is drawn again by every part of the panel, and
// going from the dashboard to a server must not end the sharing.
const shared: {
    peer: RTCPeerConnection | null;
    stream: MediaStream | null;
    answered: string;
    starting: boolean;
    live: boolean;
} = { peer: null, stream: null, answered: '', starting: false, live: false };

const listeners = new Set<() => void>();
const notify = () => listeners.forEach((listener) => listener());

const releaseShared = () => {
    shared.stream?.getTracks().forEach((track) => track.stop());
    shared.stream = null;
    shared.peer?.close();
    shared.peer = null;
    shared.answered = '';
    shared.live = false;
    notify();
};

const stopShared = () => {
    releaseShared();
    http.delete('/api/client/screen').catch(() => undefined);
};

// Somebody of the staff asks to see the screen to help. Nothing is shared unless the person says yes here and then picks,
// in their own browser, what to share; the browser shows that it is being shared, and a banner here says so as well, with
// a button to stop. The picture goes straight to the staff member, it is not kept.
export default () => {
    const [session, setSession] = useState<Session | null>(null);
    const [, redraw] = useState(0);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState('');

    useEffect(() => {
        const listener = () => redraw((count) => count + 1);
        listeners.add(listener);

        return () => {
            listeners.delete(listener);
        };
    }, []);

    const sharing = shared.live;

    const stop = useCallback(() => {
        setSession(null);
        stopShared();
    }, []);

    useEffect(() => {
        let alive = true;
        const tick = () => {
            if (document.hidden && !shared.peer) {
                return;
            }
            http.get('/api/client/screen')
                .then(({ data }) => {
                    if (!alive) {
                        return;
                    }
                    const next: Session | null = data.data;
                    setSession(next);

                    if (!next || next.state === 'stopped' || next.state === 'declined') {
                        // The staff member stopped, or the request went out of date.
                        if (shared.peer) {
                            releaseShared();
                        }

                        return;
                    }
                    if (next.state !== 'requested' && !shared.peer && !shared.starting) {
                        // A session that was started before this page was opened cannot be picked up again.
                        http.delete('/api/client/screen').catch(() => undefined);

                        return;
                    }
                    if (next.answer && shared.peer && next.answer !== shared.answered) {
                        shared.answered = next.answer;
                        shared.peer
                            .setRemoteDescription({ type: 'answer', sdp: withLineBreaks(next.answer) })
                            .catch(() => stop());
                    }
                })
                .catch(() => undefined);
        };

        tick();
        const timer = window.setInterval(tick, POLL_MS);
        document.addEventListener('visibilitychange', tick);

        return () => {
            alive = false;
            window.clearInterval(timer);
            document.removeEventListener('visibilitychange', tick);
        };
    }, []);

    const decline = () => {
        setError('');
        setSession(null);
        http.post('/api/client/screen/decline').catch(() => undefined);
    };

    const accept = async () => {
        if (busy) {
            return;
        }
        setBusy(true);
        shared.starting = true;
        setError('');

        try {
            if (!navigator.mediaDevices || !navigator.mediaDevices.getDisplayMedia) {
                throw new Error('Your browser cannot share the screen.');
            }
            // The browser now asks what to share; a refusal there is a refusal of the request.
            const media = await navigator.mediaDevices.getDisplayMedia({ video: true, audio: false });
            shared.stream = media;

            const connection = new RTCPeerConnection({ iceServers: ICE_SERVERS });
            shared.peer = connection;
            media.getTracks().forEach((track) => {
                connection.addTrack(track, media);
                // The button of the browser to stop sharing.
                track.addEventListener('ended', stopShared);
            });

            await connection.setLocalDescription(await connection.createOffer());
            await gathered(connection);
            await http.post('/api/client/screen/offer', { sdp: connection.localDescription?.sdp });
            shared.live = true;
            notify();
        } catch (e) {
            releaseShared();
            http.post('/api/client/screen/decline').catch(() => undefined);
            setSession(null);
            if (e instanceof Error && e.name !== 'NotAllowedError' && e.name !== 'AbortError') {
                setError(e.message.startsWith('Your browser') ? e.message : httpErrorToHuman(e));
            }
        } finally {
            shared.starting = false;
            setBusy(false);
        }
    };

    const asking = session?.state === 'requested' && !sharing;

    return (
        <>
            {asking && (
                <div className={'fixed inset-0 z-[60] flex items-center justify-center bg-black/60 p-4'}>
                    <div
                        role={'dialog'}
                        aria-label={'Screen sharing'}
                        className={
                            'w-full max-w-md rounded-2xl border border-white/10 bg-neutral-800 p-6 shadow-card text-center'
                        }
                    >
                        <div
                            className={
                                'mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-primary-500/20 text-2xl text-primary-300'
                            }
                        >
                            <FontAwesomeIcon icon={faDesktop} />
                        </div>
                        <h2 className={'text-lg font-semibold text-neutral-50'}>The support asks to see your screen</h2>
                        <p className={'mt-2 text-sm text-neutral-300'}>
                            {session?.admin} would like to see what you are doing to help you. You choose what to share
                            (a tab, a window or the whole screen) and you can stop at any time. Nothing is recorded.
                        </p>
                        {error && <p className={'mt-3 text-sm text-red-400'}>{error}</p>}
                        <div className={'mt-5 flex justify-center gap-3'}>
                            <button
                                type={'button'}
                                onClick={decline}
                                disabled={busy}
                                className={
                                    'rounded-lg border border-white/10 bg-white/5 px-4 py-2 text-sm font-semibold text-neutral-200 hover:bg-white/10 disabled:opacity-50'
                                }
                            >
                                Decline
                            </button>
                            <button
                                type={'button'}
                                onClick={accept}
                                disabled={busy}
                                className={
                                    'rounded-lg bg-primary-500 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-400 disabled:opacity-50'
                                }
                            >
                                Share my screen
                            </button>
                        </div>
                    </div>
                </div>
            )}
            {sharing && (
                <div
                    className={
                        'fixed top-3 left-1/2 z-[60] flex -translate-x-1/2 items-center gap-3 rounded-full border border-red-500/40 bg-neutral-900/95 py-1.5 pl-4 pr-2 text-sm text-neutral-100 shadow-card'
                    }
                >
                    <span className={'h-2.5 w-2.5 animate-pulse rounded-full bg-red-500'} />
                    <span>Your screen is shared with {session?.admin || 'the support'}</span>
                    <button
                        type={'button'}
                        onClick={stop}
                        className={
                            'flex items-center gap-2 rounded-full bg-red-500/90 px-3 py-1 text-xs font-semibold text-white hover:bg-red-500'
                        }
                    >
                        <FontAwesomeIcon icon={faStop} />
                        Stop
                    </button>
                </div>
            )}
        </>
    );
};
