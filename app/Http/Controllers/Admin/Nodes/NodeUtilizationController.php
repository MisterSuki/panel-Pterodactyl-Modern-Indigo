<?php

namespace Pterodactyl\Http\Controllers\Admin\Nodes;

use Illuminate\Support\Arr;
use Pterodactyl\Models\Node;
use Pterodactyl\Models\Server;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Repositories\Wings\DaemonConfigurationRepository;
use Pterodactyl\Exceptions\Http\Connection\DaemonConnectionException;

class NodeUtilizationController extends Controller
{
    /**
     * How many of the busiest servers are listed next to the totals.
     */
    private const TOP_SERVERS = 6;

    public function __construct(private DaemonConfigurationRepository $repository)
    {
    }

    /**
     * Returns what all of the servers on a node are using right now.
     *
     * Wings does not report the usage of the machine itself, but it does report the live usage of every
     * server it runs, so the totals below are the sum of those servers. That is what the node's
     * allocated memory and disk are measured against.
     */
    public function __invoke(Node $node): JsonResponse
    {
        try {
            $servers = $this->repository->setNode($node)->getServersUtilization();
        } catch (DaemonConnectionException) {
            return new JsonResponse(['error' => 'The daemon of this node cannot be reached.'], 504);
        }

        $totals = ['cpu' => 0.0, 'memory_bytes' => 0, 'disk_bytes' => 0, 'rx_bytes' => 0, 'tx_bytes' => 0];
        $running = 0;
        $rows = [];

        foreach ($servers as $server) {
            $state = Arr::get($server, 'state', 'offline');
            $usage = Arr::get($server, 'utilization', []);

            $row = [
                'uuid' => Arr::get($server, 'configuration.uuid'),
                'state' => $state,
                'cpu' => (float) Arr::get($usage, 'cpu_absolute', 0),
                'memory_bytes' => (int) Arr::get($usage, 'memory_bytes', 0),
            ];

            $totals['cpu'] += $row['cpu'];
            $totals['memory_bytes'] += $row['memory_bytes'];
            $totals['disk_bytes'] += (int) Arr::get($usage, 'disk_bytes', 0);
            $totals['rx_bytes'] += (int) Arr::get($usage, 'network.rx_bytes', 0);
            $totals['tx_bytes'] += (int) Arr::get($usage, 'network.tx_bytes', 0);

            if ($state === 'running') {
                ++$running;
            }
            $rows[] = $row;
        }

        usort($rows, fn ($a, $b) => $b['memory_bytes'] <=> $a['memory_bytes']);
        $rows = array_slice($rows, 0, self::TOP_SERVERS);

        $names = Server::query()
            ->where('node_id', $node->id)
            ->whereIn('uuid', array_filter(array_column($rows, 'uuid')))
            ->get(['id', 'uuid', 'name'])
            ->keyBy('uuid');

        return new JsonResponse([
            'cpu' => round($totals['cpu'], 2),
            'memory_bytes' => $totals['memory_bytes'],
            'disk_bytes' => $totals['disk_bytes'],
            'rx_bytes' => $totals['rx_bytes'],
            'tx_bytes' => $totals['tx_bytes'],
            'memory_limit_mib' => (int) $node->memory,
            'disk_limit_mib' => (int) $node->disk,
            'servers' => ['total' => count($servers), 'running' => $running],
            'top' => array_map(fn ($row) => [
                'id' => $names->get($row['uuid'])?->id,
                'name' => $names->get($row['uuid'])?->name ?? 'Unknown server',
                'state' => $row['state'],
                'cpu' => $row['cpu'],
                'memory_bytes' => $row['memory_bytes'],
            ], $rows),
            'time' => microtime(true),
        ]);
    }
}
