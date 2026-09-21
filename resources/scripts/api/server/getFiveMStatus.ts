import http from '@/api/http';

export interface FiveMStatus {
    // False when the server is not a FiveM one, or does not answer yet.
    online: boolean;
    players: number | null;
    maxPlayers: number | null;
    txadmin: {
        enabled: boolean;
        url: string | null;
        port: number | null;
        // Whether the txAdmin port is one of the server's allocations, which it has to be to be reachable.
        portAllocated: boolean;
    };
}

export default (uuid: string): Promise<FiveMStatus> => {
    return new Promise((resolve, reject) => {
        http.get(`/api/client/servers/${uuid}/fivem`)
            .then(({ data: { attributes } }) =>
                resolve({
                    online: !!attributes.online,
                    players: attributes.players ?? null,
                    maxPlayers: attributes.max_players ?? null,
                    txadmin: {
                        enabled: !!attributes.txadmin?.enabled,
                        url: attributes.txadmin?.url ?? null,
                        port: attributes.txadmin?.port ?? null,
                        portAllocated: !!attributes.txadmin?.port_allocated,
                    },
                })
            )
            .catch(reject);
    });
};
