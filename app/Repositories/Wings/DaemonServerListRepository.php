<?php

namespace Pterodactyl\Repositories\Wings;

use GuzzleHttp\Exception\TransferException;
use Pterodactyl\Exceptions\Http\Connection\DaemonConnectionException;

/**
 * All the servers of a node in one request: their state and what they use. Used by the watch of the servers (alerts,
 * statistics, status page), so a node costs one request a minute instead of one per server.
 */
class DaemonServerListRepository extends DaemonRepository
{
    /**
     * @return array<string, array{state: string, memory: int, memory_limit: int, disk: int, uptime: int}> by uuid
     *
     * @throws DaemonConnectionException
     */
    public function all(): array
    {
        try {
            $response = $this->getHttpClient()->get('/api/servers', ['timeout' => 5, 'connect_timeout' => 3]);
        } catch (TransferException $exception) {
            throw new DaemonConnectionException($exception, false);
        }

        $out = [];
        foreach ((array) json_decode($response->getBody()->__toString(), true) as $item) {
            $uuid = $item['configuration']['uuid'] ?? $item['uuid'] ?? null;
            if (!is_string($uuid)) {
                continue;
            }
            $use = (array) ($item['utilization'] ?? []);
            $out[$uuid] = [
                'state' => (string) ($item['state'] ?? $use['state'] ?? 'offline'),
                'memory' => (int) ($use['memory_bytes'] ?? 0),
                'memory_limit' => (int) ($use['memory_limit_bytes'] ?? 0),
                'disk' => (int) ($use['disk_bytes'] ?? 0),
                'uptime' => (int) ($use['uptime'] ?? 0),
            ];
        }

        return $out;
    }
}
