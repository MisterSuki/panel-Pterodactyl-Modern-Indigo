<?php

namespace Pterodactyl\Services\Servers;

use Pterodactyl\Models\Server;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\Pool;
use Illuminate\Contracts\Cache\Repository as CacheRepository;

/**
 * What the Console page shows for a FiveM server: how many players are connected, and where txAdmin is.
 *
 * A FiveM server answers on its own game port with /players.json and /info.json, so the panel asks the
 * server directly (a browser could not: the panel is served over https and the server over plain http).
 * Only the default allocation of the server itself is ever contacted.
 */
class FiveMStatus
{
    private const CACHE_SECONDS = 5;

    private const DEFAULT_TXADMIN_PORT = 40120;

    public function __construct(private CacheRepository $cache)
    {
    }

    /**
     * Whether this is a FiveM (Cfx.re) server, judged from its egg and its startup command.
     */
    public function isFiveM(Server $server): bool
    {
        $egg = $server->egg;
        $text = strtolower(($egg?->name ?? '') . ' ' . ($server->startup ?? '') . ' ' . ($egg?->startup ?? ''));

        return preg_match('/fivem|cfx|fxserver|citizen_dir/', $text) === 1;
    }

    /**
     * @return array{is_fivem: bool, online: bool, players: int|null, max_players: int|null, txadmin: array{enabled: bool, url: string|null, port: int|null, port_allocated: bool}}
     */
    public function status(Server $server): array
    {
        if (!$this->isFiveM($server)) {
            return ['is_fivem' => false, 'online' => false, 'players' => null, 'max_players' => null, 'txadmin' => $this->txadmin($server)];
        }

        return $this->cache->remember('fivem-status:' . $server->uuid, self::CACHE_SECONDS, function () use ($server) {
            $allocation = $server->allocation;
            $players = null;
            $max = null;

            if ($allocation && $allocation->ip && !in_array($allocation->ip, ['0.0.0.0', '::'], true)) {
                $base = 'http://' . $allocation->ip . ':' . (int) $allocation->port;

                try {
                    $responses = Http::pool(fn (Pool $pool) => [
                        $pool->as('players')->connectTimeout(2)->timeout(3)->get($base . '/players.json'),
                        $pool->as('info')->connectTimeout(2)->timeout(3)->get($base . '/info.json'),
                    ]);
                } catch (\Throwable) {
                    $responses = [];
                }

                $list = $this->json($responses['players'] ?? null);
                if (is_array($list) && array_is_list($list)) {
                    $players = count($list);
                }

                $info = $this->json($responses['info'] ?? null);
                $limit = is_array($info) ? ($info['vars']['sv_maxClients'] ?? null) : null;
                $max = is_numeric($limit) && (int) $limit > 0 ? (int) $limit : null;
            }

            if ($max === null) {
                $fallback = $this->variable($server, 'MAX_PLAYERS');
                $max = is_numeric($fallback) && (int) $fallback > 0 ? (int) $fallback : null;
            }

            return [
                'is_fivem' => true,
                'online' => $players !== null,
                'players' => $players,
                'max_players' => $max,
                'txadmin' => $this->txadmin($server),
            ];
        });
    }

    /**
     * Where txAdmin can be opened. It is on its own port, set by the egg's TXADMIN_PORT variable, on the same address
     * as the server. The port has to be one of the server's allocations for it to be reachable from outside.
     *
     * @return array{enabled: bool, url: string|null, port: int|null, port_allocated: bool}
     */
    public function txadmin(Server $server): array
    {
        $flag = $this->variable($server, 'TXADMIN_ENABLE');
        $portValue = $this->variable($server, 'TXADMIN_PORT');
        $enabled = $flag !== null ? in_array(strtolower($flag), ['1', 'true', 'yes', 'on'], true) : $portValue !== null;
        $port = is_numeric($portValue) && (int) $portValue > 0 && (int) $portValue < 65536 ? (int) $portValue : self::DEFAULT_TXADMIN_PORT;

        $allocation = $server->allocation;
        $host = $allocation ? ($allocation->alias ?: $allocation->ip) : null;
        if (!$enabled || !$host || in_array($host, ['0.0.0.0', '::'], true)) {
            return ['enabled' => $enabled, 'url' => null, 'port' => $enabled ? $port : null, 'port_allocated' => false];
        }

        return [
            'enabled' => true,
            'url' => 'http://' . $host . ':' . $port,
            'port' => $port,
            'port_allocated' => $server->allocations->contains(fn ($a) => (int) $a->port === $port),
        ];
    }

    /**
     * The value of an egg variable for this server: what was set for it, or the egg's default.
     */
    private function variable(Server $server, string $name): ?string
    {
        $variable = $server->variables->firstWhere('env_variable', $name);
        if ($variable === null) {
            return null;
        }

        $value = $variable->server_value ?? $variable->default_value;

        return $value === null || $value === '' ? null : (string) $value;
    }

    private function json(mixed $response): mixed
    {
        if (!$response instanceof \Illuminate\Http\Client\Response || !$response->successful()) {
            return null;
        }

        return json_decode($response->body(), true);
    }
}
