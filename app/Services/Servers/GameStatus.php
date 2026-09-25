<?php

namespace Pterodactyl\Services\Servers;

use Pterodactyl\Models\Server;
use Illuminate\Contracts\Cache\Repository as CacheRepository;

/**
 * How many players are on a game server that speaks the Steam query, for the Console page.
 *
 * Only the addresses of the server itself are asked (its own allocations), and only for servers made from a Steam egg.
 * What was found is kept two seconds, so ten people looking at the same console cost one question; what was not found
 * is kept longer, so a game that does not answer is not asked every few seconds.
 */
class GameStatus
{
    private const FOUND_SECONDS = 2;

    private const MISSING_SECONDS = 20;

    private const PORT_SECONDS = 3600;

    public function __construct(private CacheRepository $cache, private SourceQuery $query)
    {
    }

    /**
     * Whether the egg is one of the Steam ones: made from the SteamCMD image, or with a Steam application id.
     */
    public function supports(Server $server): bool
    {
        if (str_contains(strtolower((string) $server->image), 'steamcmd')) {
            return true;
        }

        // Read with its own query, and not by eager loading, which would give the egg's defaults (see FiveMStatus).
        return $server->variables()->where('env_variable', 'SRCDS_APPID')->exists();
    }

    /**
     * @return array{supported: bool, online: bool, players: int|null, max_players: int|null, name: string|null, map: string|null}
     */
    public function status(Server $server): array
    {
        $none = ['supported' => false, 'online' => false, 'players' => null, 'max_players' => null, 'name' => null, 'map' => null];

        if (!$this->supports($server)) {
            return $none;
        }
        $none['supported'] = true;

        // A server that is suspended, installing or being moved, or on a node in maintenance, has nothing to ask.
        $server->loadMissing(['allocation', 'allocations', 'node']);
        if ($server->status !== null || $server->node->maintenance_mode) {
            return $none;
        }

        $key = 'game-query:' . $server->uuid;
        $cached = $this->cache->get($key);
        if (is_array($cached)) {
            return $cached['info'] === null ? $none : $this->format($cached['info']);
        }

        $info = $this->ask($server);
        $this->cache->put($key, ['info' => $info], $info === null ? self::MISSING_SECONDS : self::FOUND_SECONDS);

        return $info === null ? $none : $this->format($info);
    }

    /**
     * @return array{port: int, name: string, map: string, players: int, max_players: int, bots: int}|null
     */
    private function ask(Server $server): ?array
    {
        $allocation = $server->allocation;
        $host = $allocation?->ip;
        if (!$host || in_array($host, ['0.0.0.0', '::'], true)) {
            $host = $server->node->fqdn;
        }
        if (!$host) {
            return null;
        }
        // The address of a node can be a name.
        if (filter_var($host, FILTER_VALIDATE_IP) === false) {
            $resolved = gethostbyname($host);
            $host = $resolved !== $host ? $resolved : null;
        }
        if ($host === null) {
            return null;
        }

        $portKey = 'game-query-port:' . $server->uuid;
        $known = $this->cache->get($portKey);

        // The port that answered last time, alone and quickly, before all of them.
        if (is_int($known)) {
            $info = $this->query->first($host, [$known], 0.6);
            if ($info !== null) {
                return $info;
            }
        }

        // The query port is not always the game port: every allocation of the server is asked, the main one first.
        $ports = [];
        if ($allocation) {
            $ports[] = (int) $allocation->port;
        }
        foreach ($server->allocations as $other) {
            $ports[] = (int) $other->port;
        }

        $info = $this->query->first($host, $ports, 1.0);
        if ($info !== null) {
            $this->cache->put($portKey, $info['port'], self::PORT_SECONDS);
        }

        return $info;
    }

    /**
     * @param array{port: int, name: string, map: string, players: int, max_players: int, bots: int} $info
     *
     * @return array{supported: bool, online: bool, players: int|null, max_players: int|null, name: string|null, map: string|null}
     */
    private function format(array $info): array
    {
        return [
            'supported' => true,
            'online' => true,
            // Bots are not people: they are left out of the count.
            'players' => max(0, $info['players'] - $info['bots']),
            'max_players' => $info['max_players'] > 0 ? $info['max_players'] : null,
            'name' => $info['name'] !== '' ? $info['name'] : null,
            'map' => $info['map'] !== '' ? $info['map'] : null,
        ];
    }
}
