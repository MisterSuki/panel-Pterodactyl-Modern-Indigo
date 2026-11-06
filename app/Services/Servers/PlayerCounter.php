<?php

namespace Pterodactyl\Services\Servers;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Pterodactyl\Models\Server;
use Pterodactyl\Services\WebHosting\WebHostingService;

/**
 * How many players are on a server, whatever the game: used by the statistics (live and every 5 minutes).
 *
 * Each game answers one of a few questions: the Steam query (A2S, most Steam games), the Minecraft Java server list
 * ping, the Minecraft Bedrock ping (RakNet) or FiveM's players.json. The first time, they are tried one after the
 * other on the server's own addresses; the one that answered is remembered, so later counts ask only it. A server that
 * answers none (a bot, a game without a query, or a game still starting) is not asked again for two minutes.
 */
class PlayerCounter
{
    private const FOUND_SECONDS = 3;

    private const MISSING_SECONDS = 20;

    private const METHOD_SECONDS = 21600;

    private const NONE_SECONDS = 120;

    private const RAKNET_MAGIC = "\x00\xff\xff\x00\xfe\xfe\xfe\xfe\xfd\xfd\xfd\xfd\x12\x34\x56\x78";

    public function __construct(
        private CacheRepository $cache,
        private SourceQuery $steam,
        private MinecraftPing $minecraft,
        private FiveMStatus $fivem,
    ) {
    }

    /**
     * @return array{players: int, max_players: int|null, via: string}|null null when the game does not say
     */
    public function count(Server $server): ?array
    {
        $server->loadMissing(['allocation', 'allocations', 'node', 'egg']);
        if ($server->status !== null || $server->node->maintenance_mode || !$server->allocation) {
            return null;
        }
        // A website has visitors, not players.
        if (WebHostingService::isWebsite($server)) {
            return null;
        }

        $key = 'players:' . $server->uuid;
        $cached = $this->cache->get($key);
        if (is_array($cached)) {
            return $cached['value'];
        }

        $value = $this->ask($server);
        $this->cache->put($key, ['value' => $value], $value === null ? self::MISSING_SECONDS : self::FOUND_SECONDS);

        return $value;
    }

    /**
     * Writes the count in the statistics, at most once every 5 minutes for a server (the watch and the live page can
     * both ask). Returns whether it was written.
     */
    public function record(Server $server, array $count): bool
    {
        if (!$this->cache->add('players-sample:' . $server->id, true, 290)) {
            return false;
        }
        DB::table('server_player_samples')->insert([
            'server_id' => $server->id,
            'players' => min(65535, max(0, (int) $count['players'])),
            'max_players' => $count['max_players'] !== null ? min(65535, (int) $count['max_players']) : null,
            'created_at' => now(),
        ]);

        return true;
    }

    private function ask(Server $server): ?array
    {
        $host = $this->host($server);
        if ($host === null) {
            return null;
        }
        $port = (int) $server->allocation->port;
        $methodKey = 'players-method:' . $server->uuid;
        $known = $this->cache->get($methodKey);

        if ($known === 'none') {
            return null;
        }
        if (is_array($known)) {
            // The question that worked before: if it gets no answer, the server is stopped (or starting).
            $found = $this->try($known['via'], $host, $known['port'] ?? $port, $server);
            if ($found !== null) {
                unset($found['port']);
            }

            return $found;
        }

        // The likely question first, from what the server is; then all of them.
        $order = ['steam', 'minecraft', 'bedrock', 'fivem'];
        if ($this->fivem->isFiveM($server)) {
            $order = ['fivem', 'steam'];
        } elseif ($this->isMinecraft($server)) {
            $order = ['minecraft', 'bedrock', 'steam'];
        } elseif (preg_match('/bedrock|pocketmine|nukkit/i', (string) $server->image . ' ' . $server->egg?->name)) {
            $order = ['bedrock', 'minecraft', 'steam'];
        }

        foreach ($order as $via) {
            $found = $this->try($via, $host, $port, $server);
            if ($found !== null) {
                $this->cache->put($methodKey, ['via' => $via, 'port' => $found['port']], self::METHOD_SECONDS);
                unset($found['port']);

                return $found;
            }
        }

        $this->cache->put($methodKey, 'none', self::NONE_SECONDS);

        return null;
    }

    /**
     * @return array{players: int, max_players: int|null, via: string, port?: int}|null
     */
    private function try(string $via, string $host, int $port, Server $server): ?array
    {
        $result = match ($via) {
            'steam' => $this->steam($host, $port, $server),
            'minecraft' => $this->minecraft->ask($host, $port, 1.0),
            'bedrock' => $this->bedrock($host, $port),
            'fivem' => $this->fivem($host, $port),
            default => null,
        };
        if ($result === null) {
            return null;
        }

        return [
            'players' => max(0, (int) $result['players']),
            'max_players' => isset($result['max_players']) && (int) $result['max_players'] > 0 ? (int) $result['max_players'] : null,
            'via' => $via,
            'port' => (int) ($result['port'] ?? $port),
        ];
    }

    private function steam(string $host, int $port, Server $server): ?array
    {
        // The query port is not always the game port: the server's allocations, and the port after the game port
        // (ARK, Unturned, ETS2...) are all asked at once.
        $ports = [$port, $port + 1];
        foreach ($server->allocations as $allocation) {
            $ports[] = (int) $allocation->port;
        }
        $info = $this->steam->first($host, $ports, 1.0);
        if ($info === null) {
            return null;
        }

        return ['players' => max(0, $info['players'] - $info['bots']), 'max_players' => $info['max_players'], 'port' => $info['port']];
    }

    private function bedrock(string $host, int $port): ?array
    {
        $socket = @stream_socket_client("udp://{$host}:{$port}", $errno, $error, 1);
        if ($socket === false) {
            return null;
        }
        stream_set_timeout($socket, 1);
        try {
            @fwrite($socket, "\x01" . pack('J', (int) (microtime(true) * 1000)) . self::RAKNET_MAGIC . pack('J', random_int(1, PHP_INT_MAX)));
            $data = @fread($socket, 2048);
            // 0x1c, time (8), server id (8), magic (16), length (2), then "MCPE;motd;protocol;version;players;max;..."
            if (!is_string($data) || strlen($data) < 35 || $data[0] !== "\x1c") {
                return null;
            }
            $parts = explode(';', substr($data, 35));
            if (count($parts) < 6 || !is_numeric($parts[4])) {
                return null;
            }

            return ['players' => (int) $parts[4], 'max_players' => (int) $parts[5]];
        } catch (\Throwable) {
            return null;
        } finally {
            fclose($socket);
        }
    }

    private function fivem(string $host, int $port): ?array
    {
        try {
            $players = Http::connectTimeout(1)->timeout(2)->get("http://{$host}:{$port}/players.json");
            if (!$players->successful() || !is_array($players->json())) {
                return null;
            }
            $info = Http::connectTimeout(1)->timeout(2)->get("http://{$host}:{$port}/info.json");
            $max = $info->successful() ? ($info->json('vars.sv_maxClients') ?? null) : null;

            return ['players' => count($players->json()), 'max_players' => is_numeric($max) ? (int) $max : null];
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * The address to ask: the server's own IP, or its node's when it listens on every address (0.0.0.0).
     */
    private function host(Server $server): ?string
    {
        $host = $server->allocation->ip;
        if (!$host || in_array($host, ['0.0.0.0', '::'], true)) {
            $host = $server->node->fqdn;
        }
        if (!$host) {
            return null;
        }
        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            return $host;
        }
        $cacheKey = 'players-host:' . $host;
        $resolved = $this->cache->remember($cacheKey, 300, fn () => gethostbyname($host));

        return filter_var($resolved, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false ? $resolved : null;
    }

    private function isMinecraft(Server $server): bool
    {
        $features = (array) ($server->egg?->inherit_features ?? $server->egg?->features ?? []);
        if (in_array('eula', $features, true) || in_array('java_version', $features, true)) {
            return true;
        }

        return (bool) preg_match('/minecraft|paper|spigot|purpur|forge|fabric|velocity|bungee/i', (string) $server->egg?->name . ' ' . $server->startup);
    }
}
