<?php

namespace Pterodactyl\Services\Admin;

use Pterodactyl\Models\Node;
use Pterodactyl\Models\User;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\Location;

/**
 * What the administration home page shows: how many servers, users, nodes and locations there are, how full each
 * node is, and what happened last. Nothing here asks a daemon, so the page opens at once even when a node is down.
 *
 * Only the sections the person may open are read, so a staff member limited to some sections never sees, or makes
 * the panel count, the others.
 */
class OverviewService
{
    private const LATEST = 5;

    private const NODES = 8;

    /**
     * @param callable(string): bool $can whether the person can open a section of the administration
     *
     * @return array{counts: array<string, array<string, int>>, nodes: array<int, array<string, mixed>>, recent_servers: array<int, array<string, mixed>>, recent_users: array<int, array<string, mixed>>}
     */
    public function build(callable $can): array
    {
        $counts = [];
        $nodes = $recentServers = $recentUsers = [];

        if ($can('servers')) {
            $row = Server::query()
                ->selectRaw("count(*) as total, sum(case when status = 'suspended' then 1 else 0 end) as suspended, sum(case when status in ('install_failed', 'reinstall_failed') then 1 else 0 end) as attention")
                ->first();
            $counts['servers'] = [
                'total' => (int) $row->total,
                'suspended' => (int) $row->suspended,
                'attention' => (int) $row->attention,
            ];
            $recentServers = $this->recentServers();
        }

        if ($can('users')) {
            $row = User::query()
                ->selectRaw('count(*) as total, sum(case when root_admin = 1 then 1 else 0 end) as admins')
                ->first();
            $counts['users'] = ['total' => (int) $row->total, 'admins' => (int) $row->admins];
            $recentUsers = $this->recentUsers();
        }

        if ($can('nodes')) {
            $nodes = $this->nodes();
            $counts['nodes'] = [
                'total' => Node::query()->count(),
                'maintenance' => Node::query()->where('maintenance_mode', true)->count(),
            ];
        }

        if ($can('locations')) {
            $counts['locations'] = ['total' => Location::query()->count()];
        }

        return ['counts' => $counts, 'nodes' => $nodes, 'recent_servers' => $recentServers, 'recent_users' => $recentUsers];
    }

    /**
     * How much of each node is promised to servers, taking the over-allocation of the node into account. A node that
     * allows unlimited over-allocation has no ceiling to measure against, and gets no percentage.
     *
     * @return array<int, array<string, mixed>>
     */
    public function nodes(): array
    {
        return Node::query()
            ->with('location:id,short')
            ->withCount('servers')
            ->withSum('servers as allocated_memory', 'memory')
            ->withSum('servers as allocated_disk', 'disk')
            ->orderBy('name')
            ->limit(self::NODES)
            ->get()
            ->map(fn (Node $node) => [
                'id' => $node->id,
                'name' => $node->name,
                'location' => $node->location?->short,
                'maintenance' => (bool) $node->maintenance_mode,
                'servers' => (int) $node->servers_count,
                'memory' => $this->usage((int) $node->allocated_memory, (int) $node->memory, (int) $node->memory_overallocate),
                'disk' => $this->usage((int) $node->allocated_disk, (int) $node->disk, (int) $node->disk_overallocate),
            ])
            ->all();
    }

    /**
     * @return array{allocated: int, total: int, percent: int|null}
     */
    public function usage(int $allocated, int $total, int $overallocate): array
    {
        $ceiling = $overallocate < 0 ? 0 : (int) floor($total * (1 + $overallocate / 100));

        return [
            'allocated' => $allocated,
            'total' => $total,
            'percent' => $ceiling > 0 ? (int) round($allocated / $ceiling * 100) : null,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function recentServers(): array
    {
        return Server::query()
            ->with(['user:id,username', 'node:id,name'])
            ->latest()
            ->limit(self::LATEST)
            ->get()
            ->map(fn (Server $server) => [
                'id' => $server->id,
                'name' => $server->name,
                'owner' => $server->user?->username,
                'node' => $server->node?->name,
                'status' => $server->status,
                'created_at' => $server->created_at,
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function recentUsers(): array
    {
        return User::query()
            ->latest()
            ->limit(self::LATEST)
            ->get()
            ->map(fn (User $user) => [
                'id' => $user->id,
                'username' => $user->username,
                'email' => $user->email,
                'admin' => (bool) $user->root_admin,
                'discord' => !is_null($user->discord_id),
                'created_at' => $user->created_at,
            ])
            ->all();
    }
}
