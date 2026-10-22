<?php

namespace Pterodactyl\Services\Notifications;

use Illuminate\Support\Facades\DB;
use Pterodactyl\Contracts\Repository\SettingsRepositoryInterface;
use Pterodactyl\Models\Node;

/**
 * The public status page: whether it is open, its title, and what it shows (the nodes, how often they answered, and
 * the announcements the administration made public).
 */
class StatusPage
{
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

    public function save(bool $enabled, string $title, bool $showNodes): void
    {
        $this->settings->set('status:enabled', $enabled ? '1' : '0');
        $this->settings->set('status:title', mb_substr(trim($title), 0, 80));
        $this->settings->set('status:show_nodes', $showNodes ? '1' : '0');
    }

    /**
     * Everything the page shows.
     */
    public function build(): array
    {
        $nodes = [];
        $since = now()->subDay();
        $slot = 1800;
        foreach (Node::query()->with('location')->orderBy('name')->get() as $node) {
            $now = cache()->get('watch:node:' . $node->id);
            $samples = DB::table('node_status_samples')->where('node_id', $node->id)
                ->where('created_at', '>=', $since)->get(['online', 'created_at']);

            $slots = [];
            foreach ($samples as $sample) {
                $key = intdiv(strtotime((string) $sample->created_at), $slot);
                $slots[$key][] = (bool) $sample->online;
            }
            $bars = [];
            for ($t = intdiv($since->timestamp, $slot) + 1; $t <= intdiv(time(), $slot); ++$t) {
                $list = $slots[$t] ?? null;
                $bars[] = $list === null ? null : (in_array(false, $list, true) ? (in_array(true, $list, true) ? 'partial' : 'down') : 'up');
            }

            $week = DB::table('node_status_samples')->where('node_id', $node->id)->where('created_at', '>=', now()->subDays(7));
            $total = (clone $week)->count();
            $up = (clone $week)->where('online', true)->count();

            $nodes[] = [
                'name' => $node->name,
                'location' => $node->location?->short,
                'maintenance' => (bool) $node->maintenance_mode,
                'online' => is_array($now) ? (bool) $now['online'] : null,
                'uptime' => $total > 0 ? round($up / $total * 100, 2) : null,
                'bars' => $bars,
            ];
        }

        $announcements = $this->notifier->publicAnnouncements();
        $ongoing = $announcements->first(fn ($a) => in_array($a->kind, ['maintenance', 'update'], true) && $a->created_at->gt(now()->subHours(6)));
        $down = collect($nodes)->contains(fn ($n) => $n['online'] === false && !$n['maintenance']);

        return [
            'title' => $this->title(),
            'state' => $down ? 'down' : ($ongoing ? 'maintenance' : 'ok'),
            'nodes' => $this->showNodes() ? $nodes : [],
            'announcements' => $announcements,
            'checked_at' => now(),
        ];
    }
}
