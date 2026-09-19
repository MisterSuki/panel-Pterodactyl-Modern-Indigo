<?php

namespace Pterodactyl\Http\Controllers\Api\Client;

use Illuminate\Support\Arr;
use Pterodactyl\Models\Node;
use Illuminate\Http\Request;
use Pterodactyl\Models\Server;
use Illuminate\Http\JsonResponse;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Pterodactyl\Repositories\Wings\DaemonConfigurationRepository;
use Pterodactyl\Exceptions\Http\Connection\DaemonConnectionException;

/**
 * The live usage (CPU, memory, disk, network) of several servers in one request, for the dashboard.
 *
 * Asking each server separately would mean one request per server every few seconds. Here the servers are
 * grouped by node and each node is asked once for all of its servers, and that answer is shared for two seconds
 * between everyone looking at the dashboard.
 */
class ServerResourcesController extends ClientApiController
{
    private const MAX_SERVERS = 100;

    private const CACHE_SECONDS = 2;

    private const FAILURE_CACHE_SECONDS = 5;

    public function __construct(private CacheRepository $cache, private DaemonConfigurationRepository $repository)
    {
        parent::__construct();
    }

    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        $uuids = array_slice(array_values(array_unique(array_filter(
            explode(',', (string) $request->query('servers', '')),
            fn (string $uuid) => preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $uuid) === 1
        ))), 0, self::MAX_SERVERS);

        if (empty($uuids)) {
            return new JsonResponse(['object' => 'list', 'data' => (object) []]);
        }

        // Only servers the user can open: their own, the ones shared with them, or any server for an administrator.
        $servers = ($user->root_admin ? Server::query() : $user->accessibleServers())
            ->whereIn('servers.uuid', $uuids)
            ->with('node')
            ->get();

        return new JsonResponse(['object' => 'list', 'data' => (object) $this->usageOfServers($servers)]);
    }

    /**
     * The usage of the given servers, asking each node once.
     *
     * @param \Illuminate\Support\Collection<int, Server> $servers
     *
     * @return array<string, array>
     */
    public function usageOfServers(\Illuminate\Support\Collection $servers): array
    {
        $data = [];
        foreach ($servers->groupBy('node_id') as $group) {
            /** @var Node|null $node */
            $node = $group->first()->node;
            if ($node === null || $node->maintenance_mode) {
                continue;
            }

            $usage = $this->usageOf($node);
            foreach ($group as $server) {
                if (isset($usage[$server->uuid])) {
                    $data[$server->uuid] = $this->format($usage[$server->uuid]);
                }
            }
        }

        return $data;
    }

    /**
     * What every server of a node uses right now, by server uuid. A node that does not answer gives nothing,
     * and is not asked again for a few seconds.
     *
     * @return array<string, array>
     */
    private function usageOf(Node $node): array
    {
        $entry = $this->cache->get('node-usage:' . $node->id);
        if (is_array($entry)) {
            return $entry['servers'];
        }

        try {
            $servers = [];
            foreach ($this->repository->setNode($node)->getServersUtilization() as $server) {
                $uuid = Arr::get($server, 'configuration.uuid');
                if (is_string($uuid)) {
                    $servers[$uuid] = $server;
                }
            }
            $this->cache->put('node-usage:' . $node->id, ['servers' => $servers], self::CACHE_SECONDS);

            return $servers;
        } catch (DaemonConnectionException) {
            $this->cache->put('node-usage:' . $node->id, ['servers' => []], self::FAILURE_CACHE_SECONDS);

            return [];
        }
    }

    /**
     * The same shape as the usage of a single server, so the dashboard reads both the same way.
     */
    private function format(array $data): array
    {
        return [
            'current_state' => Arr::get($data, 'state', 'offline'),
            'is_suspended' => Arr::get($data, 'is_suspended', false),
            'resources' => [
                'memory_bytes' => Arr::get($data, 'utilization.memory_bytes', 0),
                'cpu_absolute' => Arr::get($data, 'utilization.cpu_absolute', 0),
                'disk_bytes' => Arr::get($data, 'utilization.disk_bytes', 0),
                'network_rx_bytes' => Arr::get($data, 'utilization.network.rx_bytes', 0),
                'network_tx_bytes' => Arr::get($data, 'utilization.network.tx_bytes', 0),
                'uptime' => Arr::get($data, 'utilization.uptime', 0),
            ],
        ];
    }
}
