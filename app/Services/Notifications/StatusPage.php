<?php

namespace Pterodactyl\Services\Notifications;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Pterodactyl\Contracts\Repository\SettingsRepositoryInterface;
use Pterodactyl\Models\Node;
use Pterodactyl\Models\ProxmoxHost;
use Pterodactyl\Models\StatusMaintenance;

/**
 * The public status page: whether it is open, its title, and what it shows (the nodes, the Proxmox nodes of the VPS,
 * how often they answered, the maintenances, and the announcements the administration made public).
 */
class StatusPage
{
    // Each bar of the page is half an hour.
    private const SLOT = 1800;

    public function __construct(private SettingsRepositoryInterface $settings, private Notifier $notifier)
    {
    }

    public function enabled(): bool
    {
        return $this->settings->get('status:enabled', '0') === '1';
    }

    public function title(): string
    {
        return (string) ($this->settings->get('status:title', '') ?: config('app.name', 'Pterodactyl'));
    }

    public function showNodes(): bool
    {
        return $this->settings->get('status:show_nodes', '1') === '1';
    }

    public function showVps(): bool
    {
        return $this->settings->get('status:show_vps', '1') === '1';
    }

    public function save(bool $enabled, string $title, bool $showNodes, bool $showVps = true): void
    {
        $this->settings->set('status:enabled', $enabled ? '1' : '0');
        $this->settings->set('status:title', mb_substr(trim($title), 0, 80));
        $this->settings->set('status:show_nodes', $showNodes ? '1' : '0');
        $this->settings->set('status:show_vps', $showVps ? '1' : '0');
    }

    /**
     * What a maintenance can concern, by key: the panel, each node, each Proxmox host (all of its nodes).
     *
     * @return array<string, string>
     */
    public function components(): array
    {
        $list = ['panel' => 'Panel'];
        foreach (Node::query()->orderBy('name')->get(['id', 'name']) as $node) {
            $list['node:' . $node->id] = $node->name;
        }
        if (Schema::hasTable('proxmox_hosts')) {
            foreach (ProxmoxHost::query()->orderBy('name')->get(['id', 'name']) as $host) {
                $list['proxmox:' . $host->id] = 'VPS · ' . $host->name;
            }
        }

        return $list;
    }

    /**
     * Everything the page shows.
     */
    public function build(): array
    {
        $maintenances = $this->maintenances();
        $active = $maintenances->filter(fn (StatusMaintenance $m) => $m->isActive())->values();
        $upcoming = $maintenances->filter(fn (StatusMaintenance $m) => $m->isUpcoming())->sortBy('starts_at')->values();
        $recent = $maintenances->filter(fn (StatusMaintenance $m) => $m->ends_at->lte(now()) && $m->ends_at->gte(now()->subDays(7)))
            ->sortByDesc('ends_at')->take(5)->values();

        $windows = fn (string $key) => $maintenances->filter(fn (StatusMaintenance $m) => $m->covers($key))
            ->map(fn (StatusMaintenance $m) => [$m->starts_at->timestamp, $m->ends_at->timestamp])->values()->all();
        $inMaintenance = fn (string $key) => $active->contains(fn (StatusMaintenance $m) => $m->covers($key));

        $nodes = [];
        if ($this->showNodes()) {
            foreach (Node::query()->with('location')->orderBy('name')->get() as $node) {
                $now = cache()->get('watch:node:' . $node->id);
                $samples = DB::table('node_status_samples')->where('node_id', $node->id)
                    ->where('created_at', '>=', now()->subDays(7))->get(['online', 'created_at']);

                $nodes[] = array_merge([
                    'name' => $node->name,
                    'location' => $node->location->short,
                    'maintenance' => (bool) $node->maintenance_mode || $inMaintenance('node:' . $node->id),
                    'online' => is_array($now) ? (bool) $now['online'] : null,
                ], $this->series($samples, $windows('node:' . $node->id)));
            }
        }

        $vps = [];
        if ($this->showVps() && Schema::hasTable('proxmox_node_samples')) {
            foreach (ProxmoxHost::query()->orderBy('name')->get() as $host) {
                $now = cache()->get('watch:proxmox:' . $host->id);
                $current = is_array($now) ? (array) ($now['nodes'] ?? []) : [];
                $samples = DB::table('proxmox_node_samples')->where('host_id', $host->id)
                    ->where('created_at', '>=', now()->subDays(7))->get(['node', 'online', 'created_at']);
                $names = collect(array_keys($current))->merge($samples->pluck('node'))->map(fn ($n) => (string) $n)->unique()->sort()->values();

                foreach ($names as $name) {
                    $vps[] = array_merge([
                        'name' => $name,
                        'location' => $host->name,
                        'maintenance' => $inMaintenance('proxmox:' . $host->id),
                        'online' => array_key_exists($name, $current) ? (bool) $current[$name] : null,
                    ], $this->series($samples->where('node', $name), $windows('proxmox:' . $host->id)));
                }
            }
        }

        $announcements = $this->notifier->publicAnnouncements();
        $ongoing = $announcements->first(fn ($a) => in_array($a->kind, ['maintenance', 'update'], true) && $a->created_at->gt(now()->subHours(6)));
        $down = collect($nodes)->merge($vps)->contains(fn ($n) => $n['online'] === false && !$n['maintenance']);

        return [
            'title' => $this->title(),
            'state' => $down ? 'down' : ($active->isNotEmpty() || $ongoing ? 'maintenance' : 'ok'),
            'panel' => ['maintenance' => $inMaintenance('panel')],
            'nodes' => $nodes,
            'vps' => $vps,
            'maintenances' => ['active' => $active, 'upcoming' => $upcoming, 'recent' => $recent],
            'components' => $this->components(),
            'announcements' => $announcements,
            'checked_at' => now(),
        ];
    }

    /**
     * The maintenances the page can show: the ones still to come, running, or done in the last week.
     */
    private function maintenances(): Collection
    {
        if (!Schema::hasTable('status_maintenances')) {
            return collect();
        }

        return StatusMaintenance::query()->where('ends_at', '>=', now()->subDays(7))->orderBy('starts_at')->limit(50)->get();
    }

    /**
     * The 48 bars of the last 24 hours and the uptime of the week, from the samples of one thing. The time of its
     * maintenances shows as "maintenance" and does not count against the uptime.
     *
     * @param array<int, array{0: int, 1: int}> $windows
     */
    private function series(Collection $samples, array $windows): array
    {
        $inside = function (int $from, int $to) use ($windows): bool {
            foreach ($windows as [$start, $end]) {
                if ($start < $to && $end > $from) {
                    return true;
                }
            }

            return false;
        };

        $since = now()->subDay()->timestamp;
        $slots = [];
        $total = 0;
        $up = 0;
        foreach ($samples as $sample) {
            $at = (int) strtotime((string) $sample->created_at);
            if ($at >= $since) {
                $slots[intdiv($at, self::SLOT)][] = (bool) $sample->online;
            }
            if (!$inside($at, $at + 1)) {
                ++$total;
                $up += $sample->online ? 1 : 0;
            }
        }

        $bars = [];
        for ($t = intdiv($since, self::SLOT) + 1; $t <= intdiv(time(), self::SLOT); ++$t) {
            $list = $slots[$t] ?? null;
            $bar = $list === null ? null : (in_array(false, $list, true) ? (in_array(true, $list, true) ? 'partial' : 'down') : 'up');
            if ($bar !== 'up' && $inside($t * self::SLOT, ($t + 1) * self::SLOT)) {
                $bar = 'maintenance';
            }
            $bars[] = $bar;
        }

        return ['bars' => $bars, 'uptime' => $total > 0 ? round($up / $total * 100, 2) : null];
    }
}
