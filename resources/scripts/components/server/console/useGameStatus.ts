import useSWR from 'swr';
import { ServerContext } from '@/state/server';
import getGameStatus, { GameStatus } from '@/api/server/getGameStatus';

/**
 * For a game made from a Steam egg (the SteamCMD image, or a Steam application id): the number of players, asked to the
 * game with the Steam query every 3 seconds while it is starting or running. Whether the game answers is up to the game:
 * when it does not, `answered` stays false. For any other server nothing is requested.
 */
export default (skip = false): { isSteam: boolean; data: GameStatus | undefined } => {
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const image = ServerContext.useStoreState((state) => state.server.data!.dockerImage);
    const variables = ServerContext.useStoreState((state) => state.server.data!.variables);
    const status = ServerContext.useStoreState((state) => state.status.value);

    const isSteam = !skip && (/steamcmd/i.test(image || '') || variables.some((v) => v.envVariable === 'SRCDS_APPID'));
    const active = status === 'running' || status === 'starting';

    // The key holds "active", so the numbers are asked again the moment the server starts or stops.
    const { data } = useSWR<GameStatus>(isSteam ? ['game-status', uuid, active] : null, () => getGameStatus(uuid), {
        refreshInterval: active ? 3000 : 0,
        dedupingInterval: 1500,
        revalidateOnFocus: false,
        shouldRetryOnError: false,
    });

    return { isSteam, data };
};
