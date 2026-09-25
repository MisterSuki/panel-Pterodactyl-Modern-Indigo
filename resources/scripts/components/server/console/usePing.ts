import { useEffect, useState } from 'react';
import { ServerContext } from '@/state/server';

// How often the delay is measured, while the page is on screen.
const EVERY_MS = 4000;
// How many of the latest measures are averaged, so that one late answer does not make the number jump.
const KEPT = 3;

// The time, in milliseconds, a message takes to go to the machine of the server and come back. It is measured on the
// websocket that is already open (the machine answers an authentication right away), so it is the delay the person
// really has with the server and not the one of the panel.
const usePing = (): number | null => {
    const connected = ServerContext.useStoreState((state) => state.socket.connected);
    const instance = ServerContext.useStoreState((state) => state.socket.instance);
    const [ping, setPing] = useState<number | null>(null);

    useEffect(() => {
        if (!connected || !instance) {
            setPing(null);

            return;
        }

        let alive = true;
        let measures: number[] = [];
        const measure = () => {
            if (document.hidden) {
                return;
            }
            instance
                .ping()
                .then((ms) => {
                    if (!alive) {
                        return;
                    }
                    measures = [...measures, ms].slice(-KEPT);
                    setPing(Math.round(measures.reduce((sum, value) => sum + value, 0) / measures.length));
                })
                .catch(() => alive && setPing(null));
        };

        measure();
        const timer = window.setInterval(measure, EVERY_MS);

        return () => {
            alive = false;
            window.clearInterval(timer);
        };
    }, [connected, instance]);

    return ping;
};

export default usePing;
