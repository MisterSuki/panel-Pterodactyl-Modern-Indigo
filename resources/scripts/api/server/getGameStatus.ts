import http from '@/api/http';

export interface GameStatus {
    // True when the game answered the Steam query.
    online: boolean;
    players: number | null;
    maxPlayers: number | null;
    name: string | null;
    map: string | null;
}

export default (uuid: string): Promise<GameStatus> => {
    return new Promise((resolve, reject) => {
        http.get(`/api/client/servers/${uuid}/game-status`)
            .then(({ data: { attributes } }) =>
                resolve({
                    online: !!attributes.online,
                    players: attributes.players ?? null,
                    maxPlayers: attributes.max_players ?? null,
                    name: attributes.name ?? null,
                    map: attributes.map ?? null,
                })
            )
            .catch(reject);
    });
};
