import useSWR from 'swr';
import { ServerContext } from '@/state/server';
import getFiveMStatus, { FiveMStatus } from '@/api/server/getFiveMStatus';

// Matches the egg and startup command of FiveM (and RedM) servers.
const FIVEM = /fxserver|cfx-server|citizen_dir|fivem/i;

/**
 * For a FiveM server: the number of connected players and the txAdmin address, refreshed every 15 seconds while the
 * server runs. For any other server nothing is requested and everything is empty.
 */
export default (): { isFiveM: boolean; data: FiveMStatus | undefined } => {
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const invocation = ServerContext.useStoreState((state) => state.server.data!.invocation);
    const status = ServerContext.useStoreState((state) => state.status.value);

    const isFiveM = FIVEM.test(invocation || '');
    const running = status === 'running';

    // The key holds "running", so the numbers are asked again the moment the server has started.
    const { data } = useSWR<FiveMStatus>(isFiveM ? ['fivem', uuid, running] : null, () => getFiveMStatus(uuid), {
        refreshInterval: running ? 15000 : 0,
        revalidateOnFocus: false,
        shouldRetryOnError: false,
    });

    return { isFiveM, data };
};
