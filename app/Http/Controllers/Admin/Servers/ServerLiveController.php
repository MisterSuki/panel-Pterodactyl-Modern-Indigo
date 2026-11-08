<?php

namespace Pterodactyl\Http\Controllers\Admin\Servers;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Pterodactyl\Facades\Activity;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Models\Node;
use Pterodactyl\Models\Server;
use Pterodactyl\Repositories\Wings\DaemonPowerRepository;
use Pterodactyl\Repositories\Wings\DaemonServerListRepository;
use Pterodactyl\Services\Servers\ServerWatcher;
use Pterodactyl\Services\Servers\SuspensionService;

/**
 * The list of the servers of the administration, live: their state and use (one request to Wings per node, kept a few
 * seconds), and the power and suspension of several servers at once.
 */
class ServerLiveController extends Controller
{
    private const MAX_SERVERS = 200;

    private const STATES_SECONDS = 3;

    public function __construct(
        private CacheRepository $cache,
        private DaemonServerListRepository $list,
        private DaemonPowerRepository $power,
        private SuspensionService $suspensions,
    ) {
    }

    public function live(Request $request): JsonResponse
    {
        $ids = array_slice(array_filter(array_map('intval', explode(',', (string) $request->query('ids')))), 0, self::MAX_SERVERS);
        $servers = Server::query()->with('node')->whereIn('id', $ids)->get();

        $out = [];
        foreach ($servers->groupBy('node_id') as $group) {
            $states = $this->statesOf($group->first()->node);
            foreach ($group as $server) {
                $out[$server->id] = $this->describe($server, $states);
            }
        }

        return new JsonResponse(['servers' => $out]);
    }

    public function power(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'max:' . self::MAX_SERVERS],
            'ids.*' => ['integer'],
            'signal' => ['required', 'string', 'in:start,stop,restart,kill'],
        ]);

        $done = 0;
        $errors = [];
        foreach ($this->servers($data['ids']) as $server) {
            if ($server->status !== null) {
                $errors[] = $server->name . ': ' . ($server->isSuspended() ? 'suspended' : 'not installed');
                continue;
            }
            try {
                $this->power->setServer($server)->send($data['signal']);
            } catch (\Throwable) {
                $errors[] = $server->name . ': the node did not answer';
                continue;
            }
            Activity::event('server:power.' . $data['signal'])->subject($server)->log();
            ServerWatcher::powerAction($server, $data['signal'], $request->user()->username);
            ++$done;
        }
        $this->forgetStates();

        return new JsonResponse(['done' => $done, 'errors' => $errors]);
    }

    public function suspension(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'max:' . self::MAX_SERVERS],
            'ids.*' => ['integer'],
            'action' => ['required', 'string', 'in:suspend,unsuspend'],
            'reason' => ['nullable', 'string', 'max:' . SuspensionService::MAX_REASON_LENGTH],
        ]);

        $done = 0;
        $errors = [];
        foreach ($this->servers($data['ids']) as $server) {
            $suspended = $server->isSuspended();
            if ($data['action'] === 'suspend' ? $suspended : !$suspended) {
                continue;
            }
            try {
                if ($data['action'] === 'suspend') {
                    $this->suspensions->toggle($server, SuspensionService::ACTION_SUSPEND, [
                        'reason' => $data['reason'] ?? null,
                        'by' => $request->user()->id,
                        'until' => null,
                    ]);
                } else {
                    $this->suspensions->toggle($server, SuspensionService::ACTION_UNSUSPEND);
                }
                ++$done;
            } catch (\Throwable $exception) {
                $errors[] = $server->name . ': ' . $exception->getMessage();
            }
        }
        $this->forgetStates();

        return new JsonResponse(['done' => $done, 'errors' => $errors]);
    }

    /**
     * @return Collection<int, Server>
     */
    private function servers(array $ids): Collection
    {
        return Server::query()->with('node')->whereIn('id', array_map('intval', $ids))->get();
    }

    /**
     * The state of every server of a node, or null when the node does not answer.
     */
    private function statesOf(Node $node): ?array
    {
        $states = $this->cache->remember('admin-live:' . $node->id, self::STATES_SECONDS, function () use ($node) {
            try {
                return ['ok' => true, 'states' => $this->list->setNode($node)->all()];
            } catch (\Throwable) {
                return ['ok' => false, 'states' => []];
            }
        });

        return $states['ok'] ? $states['states'] : null;
    }

    private function forgetStates(): void
    {
        foreach (Node::query()->pluck('id') as $id) {
            $this->cache->forget('admin-live:' . $id);
        }
    }

    private function describe(Server $server, ?array $states): array
    {
        $memoryLimit = $server->memory > 0 ? $server->memory * 1048576 : 0;
        if ($server->isSuspended()) {
            return ['state' => 'suspended'];
        }
        if (!$server->isInstalled()) {
            return ['state' => 'installing'];
        }
        if ($states === null) {
            return ['state' => 'unreachable'];
        }
        $live = $states[$server->uuid] ?? null;
        if ($live === null) {
            return ['state' => 'unknown'];
        }

        return [
            'state' => $live['state'],
            'cpu' => round((float) ($live['cpu'] ?? 0), 1),
            'cpu_limit' => (int) $server->cpu,
            'memory' => (int) $live['memory'],
            'memory_limit' => $memoryLimit ?: (int) $live['memory_limit'],
            'uptime' => (int) $live['uptime'],
        ];
    }
}
