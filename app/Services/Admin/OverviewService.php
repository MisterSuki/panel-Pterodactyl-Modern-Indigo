<?php

namespace Pterodactyl\Services\Admin;

use Pterodactyl\Models\Node;
use Pterodactyl\Models\User;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\Ticket;
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
     * Someone who did nothing on the panel for this long is no longer "active now".
     */
    public const ACTIVE_SECONDS = 120;

    /**
     * @param callable(string): bool $can whether the person can open a section of the administration
     *
     * @return array{counts: array<string, array<string, int>>, nodes: array<int, array<string, mixed>>, recent_servers: array<int, array<string, mixed>>, recent_users: array<int, array<string, mixed>>, online: array<int, array<string, mixed>>}
     */
    public function build(callable $can): array
    {
        $counts = [];
        $nodes = $recentServers = $recentUsers = $online = [];

        if ($can('servers')) {
            // Plain rows: a count has no use for whole servers, which would also each load their allocation.
            $row = Server::query()->toBase()
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
            $row = User::query()->toBase()
                ->selectRaw('count(*) as total, sum(case when root_admin = 1 then 1 else 0 end) as admins')
                ->first();
            $counts['users'] = ['total' => (int) $row->total, 'admins' => (int) $row->admins];
            $recentUsers = $this->recentUsers();
            $online = $this->online();
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

        return ['counts' => $counts, 'nodes' => $nodes, 'recent_servers' => $recentServers, 'recent_users' => $recentUsers, 'online' => $online];
    }

    /**
     * The people who did something on the panel in the last two minutes, the most recent first, with the server they
     * are on if they are on one.
     *
     * @return array<int, array<string, mixed>>
     */
    public function online(): array
    {
        $now = now();
        $users = User::query()
            ->where('last_seen_at', '>=', $now->copy()->subSeconds(self::ACTIVE_SECONDS))
            ->orderByDesc('last_seen_at')
            ->limit(30)
            ->get();

        $names = Server::query()->without('allocation')
            ->whereIn('id', $users->pluck('last_seen_server_id')->filter()->unique()->all())
            ->pluck('name', 'id');

        return $users->map(fn (User $user) => [
            'id' => $user->id,
            'username' => $user->username,
            'admin' => (bool) $user->root_admin,
            'discord' => !is_null($user->discord_id),
            'avatar' => 'https://www.gravatar.com/avatar/' . md5(strtolower((string) $user->email)) . '?s=64&d=mp',
            'page' => $user->last_seen_page,
            'server' => $user->last_seen_server_id ? ($names[$user->last_seen_server_id] ?? null) : null,
            'seconds' => max(0, $now->timestamp - $user->last_seen_at->timestamp),
        ])->all();
    }

    /**
     * The figures shown next to some entries of the menu, on every page of the administration. They are kept for a
     * minute, so the menu costs three counts a minute and not three per page.
     *
     * @return array<string, int>
     */
    public function menuCounts(): array
    {
        return cache()->remember('admin:menu-counts', 60, fn () => [
            'servers' => Server::query()->count(),
            'users' => User::query()->count(),
            'nodes' => Node::query()->count(),
            // The tickets that wait for the staff. The table may not be there yet in the middle of an update.
            'tickets' => $this->openTickets(),
        ]);
    }

    private function openTickets(): int
    {
        try {
            return Ticket::query()->where('status', Ticket::OPEN)->count();
        } catch (\Throwable) {
            return 0;
        }
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
                'location' => $node->location->short,
                'maintenance' => (bool) $node->maintenance_mode,
                'servers' => (int) $node->servers_count,
                'memory' => $this->usage((int) $node->getAttribute('allocated_memory'), (int) $node->memory, (int) $node->memory_overallocate),
                'disk' => $this->usage((int) $node->getAttribute('allocated_disk'), (int) $node->disk, (int) $node->disk_overallocate),
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
            ->without('allocation')
            ->with(['user:id,username', 'node:id,name'])
            ->latest()
            ->limit(self::LATEST)
            ->get()
            ->map(fn (Server $server) => [
                'id' => $server->id,
                'name' => $server->name,
                'owner' => $server->user->username,
                'node' => $server->node->name,
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
