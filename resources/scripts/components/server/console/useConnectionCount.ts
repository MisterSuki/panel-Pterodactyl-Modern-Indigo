import { useEffect, useState } from 'react';
import { ServerContext } from '@/state/server';
import useWebsocketEvent from '@/plugins/useWebsocketEvent';
import { SocketEvent } from '@/components/server/events';
import { connectionCount } from '@/lib/connections';

// How long a count that was kept is trusted when the page is opened again.
const KEPT_FOR = 12 * 60 * 60 * 1000;

const read = (key: string): { count: number; at: number } | null => {
    try {
        const saved = JSON.parse(window.localStorage.getItem(key) || 'null');

        return saved && typeof saved.count === 'number' && Date.now() - saved.at < KEPT_FOR ? saved : null;
    } catch (e) {
        return null;
    }
};

const write = (key: string, count: number) => {
    try {
        window.localStorage.setItem(key, JSON.stringify({ count, at: Date.now() }));
    } catch (e) {
        /* the count is simply not remembered */
    }
};

/**
 * The number of people connected to a game that says it in its console ("... - 3 connections."), followed from the
 * lines of the console while the page is open. It starts again from zero when the server stops or starts, and the last
 * value is kept for the next time the page is opened, as long as the server has not been restarted since.
 *
 * `uptime` is the time the server has been running, in milliseconds, or 0 while it is not known.
 */
export default (enabled: boolean, uptime: number): number | null => {
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const status = ServerContext.useStoreState((state) => state.status.value);
    const key = `${uuid}:connections`;
    const [count, setCount] = useState<number | null>(() => (enabled ? read(key)?.count ?? null : null));
    const [checked, setChecked] = useState(false);

    useWebsocketEvent(SocketEvent.CONSOLE_OUTPUT, (line) => {
        const found = enabled ? connectionCount(line) : null;
        if (found !== null) {
            setCount(found);
            write(key, found);
        }
    });

    // Nobody is connected to a server that is stopped or is starting.
    useEffect(() => {
        if (enabled && (status === 'offline' || status === 'starting')) {
            setCount(0);
            write(key, 0);
        }
    }, [enabled, status]);

    // A value kept from before the server was last started does not count.
    useEffect(() => {
        if (!enabled || checked || uptime <= 0) {
            return;
        }
        setChecked(true);
        const saved = read(key);
        if (saved && saved.at < Date.now() - uptime - 5000) {
            setCount(null);
        }
    }, [enabled, checked, uptime]);

    return enabled ? count : null;
};
