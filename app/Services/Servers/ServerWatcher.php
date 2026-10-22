<?php

namespace Pterodactyl\Services\Servers;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Pterodactyl\Models\ActivityLog;
use Pterodactyl\Models\Node;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\ServerAlert;
use Pterodactyl\Repositories\Wings\DaemonServerListRepository;
use Pterodactyl\Services\Helpers\Locales;
use Pterodactyl\Services\Notifications\AlertTexts;
use Pterodactyl\Services\Notifications\Notifier;

/**
 * Looks at every server once a minute (one request per node), and:
 *  - sends the alerts of the servers (crash, start, stop, memory and disk almost full) to the bell and to Discord;
 *  - every five minutes, writes how many players are on each running server (for its statistics) and whether each
 *    node answered (for the public status page).
 *
 * A server that goes offline is only called "crashed" a minute later, once Wings has sent its activity: a stop asked
 * from the console reaches the panel that way, about a minute after it happened.
 */
class ServerWatcher
{
    private const STATE_TTL = 86400;

    // An alert of memory or disk is sent again after this many seconds at most.
    private const FULL_REPEAT = 21600;

    public function __construct(
        private CacheRepository $cache,
        private Notifier $notifier,
        private DaemonServerListRepository $wings,
        private GameStatus $gameStatus,
        private FiveMStatus $fivem,
        private MinecraftPing $minecraft,
    ) {
    }

    /**
     * Marks that a power action was asked for the server (from the panel or a schedule), so stopping is not a crash.
     */
    public static function markPowerAction(Server $server): void
    {
        cache()->put('watch:power:' . $server->uuid, time(), 600);
    }

    /**
     * A power action asked from the panel (its buttons, the API or a schedule): its alert is sent right away, after the
     * answer to the request, instead of when the watch sees the server change a minute later.
     */
    public static function powerAction(Server $server, string $signal, ?string $by = null): void
    {
        self::markPowerAction($server);
        $event = match ($signal) {
            'start' => 'power_start',
            'restart' => 'power_restart',
            'stop' => 'power_stop',
            'kill' => 'power_kill',
            default => null,
        };
        if ($event === null) {
            return;
        }

        $send = function () use ($server, $event, $by) {
            try {
                $alert = ServerAlert::query()->where('server_id', $server->id)->first();
                $vars = $by ? ['by' => $by] : [];
                app(self::class)->fire($server, $by ? $event : $event . '_auto', ($alert ?? new ServerAlert())->settings(), $alert, $vars);
            } catch (\Throwable $exception) {
                Log::info('A power alert could not be sent.', ['server' => $server->uuid, 'error' => $exception->getMessage()]);
            }
        };

        // A schedule runs in the queue worker, where there is no answer to wait for.
        app()->runningInConsole() ? $send() : dispatch($send)->afterResponse();
    }

    public function run(bool $sample): void
    {
        $alerts = ServerAlert::query()->get()->keyBy('server_id');

        foreach (Node::query()->get() as $node) {
            try {
                $states = $this->wings->setNode($node)->all();
                $online = true;
            } catch (\Throwable) {
                $states = [];
                $online = false;
            }
            if ($sample) {
                DB::table('node_status_samples')->insert(['node_id' => $node->id, 'online' => $online, 'created_at' => now()]);
            }
            $this->cache->put('watch:node:' . $node->id, ['online' => $online, 'at' => time()], 900);
            if (!$online || $node->maintenance_mode) {
                continue;
            }

            $servers = Server::query()->with(['allocation', 'node', 'egg'])->where('node_id', $node->id)->whereNull('status')->get();
            foreach ($servers as $server) {
                $state = $states[$server->uuid] ?? null;
                if ($state === null) {
                    continue;
                }
                try {
                    $settings = ($alerts[$server->id] ?? new ServerAlert())->settings();
                    $this->watch($server, $state, $settings, $alerts[$server->id] ?? null);
                    if ($sample && $state['state'] === 'running') {
                        $this->samplePlayers($server);
                    }
                } catch (\Throwable $exception) {
                    Log::info('A server could not be watched.', ['server' => $server->uuid, 'error' => $exception->getMessage()]);
                }
            }
        }

        if ($sample && (int) date('i') === 0) {
            DB::table('server_player_samples')->where('created_at', '<', now()->subDays(30))->delete();
            DB::table('node_status_samples')->where('created_at', '<', now()->subDays(30))->delete();
            $this->notifier->prune();
        }
    }

    /**
     * @param array{state: string, memory: int, memory_limit: int, disk: int, uptime: int} $now
     * @param array<string, bool> $settings
     */
    private function watch(Server $server, array $now, array $settings, ?ServerAlert $alert): void
    {
        $key = 'watch:state:' . $server->uuid;
        $before = $this->cache->get($key);
        $this->cache->put($key, ['state' => $now['state'], 'uptime' => $now['uptime'], 'at' => time()], self::STATE_TTL);

        // A stop seen last time, now decided: crash or wanted.
        $pending = $this->cache->get('watch:offline:' . $server->uuid);
        if (is_int($pending) && time() - $pending >= 50) {
            $this->cache->forget('watch:offline:' . $server->uuid);
            // A stop asked from the panel was already announced when it was asked.
            if (!$this->panelAskedSince($server, $pending - 180)) {
                $wanted = $this->powerAskedSince($server, $pending - 180);
                $this->fire($server, $wanted ? 'stop' : 'crash', $settings, $alert);
            }
        }

        if (is_array($before)) {
            $was = $before['state'];
            if ($now['state'] === 'offline' && in_array($was, ['running', 'starting'], true)) {
                $this->cache->put('watch:offline:' . $server->uuid, time(), 900);
            } elseif ($now['state'] === 'running' && in_array($was, ['offline', 'starting'], true)) {
                if (!$this->panelAskedSince($server, (int) $before['at'] - 60)) {
                    $this->fire($server, 'start', $settings, $alert);
                }
            } elseif ($now['state'] === 'running' && $was === 'running' && $now['uptime'] > 0 && $now['uptime'] + 30000 < (int) $before['uptime']) {
                // Still running, but it started again since last time: restarted by Wings after a crash, unless asked.
                if (!$this->powerAskedSince($server, (int) $before['at'] - 60)) {
                    $this->fire($server, 'crash_restart', $settings, $alert);
                }
            }
        }

        if ($now['state'] !== 'running') {
            return;
        }
        if ($now['memory_limit'] > 0 && $now['memory'] / $now['memory_limit'] >= 0.9) {
            $this->fireOnce($server, 'memory', $settings, $alert, $now['memory'], $now['memory_limit']);
        }
        $diskLimit = (int) $server->disk * 1024 * 1024;
        if ($diskLimit > 0 && $now['disk'] / $diskLimit >= 0.9) {
            $this->fireOnce($server, 'disk', $settings, $alert, $now['disk'], $diskLimit);
        }
    }

    private function panelAskedSince(Server $server, int $since): bool
    {
        $marked = $this->cache->get('watch:power:' . $server->uuid);

        return is_int($marked) && $marked >= $since;
    }

    private function powerAskedSince(Server $server, int $since): bool
    {
        $marked = $this->cache->get('watch:power:' . $server->uuid);
        if (is_int($marked) && $marked >= $since) {
            return true;
        }

        return ActivityLog::query()
            ->where('event', 'like', 'server:power.%')
            ->where('timestamp', '>=', date('Y-m-d H:i:s', $since))
            ->whereHas('subjects', fn ($q) => $q->where('subject_type', (new Server())->getMorphClass())->where('subject_id', $server->id))
            ->exists();
    }

    private function fireOnce(Server $server, string $event, array $settings, ?ServerAlert $alert, int $used, int $limit): void
    {
        $key = "watch:full:$event:" . $server->uuid;
        if ($this->cache->has($key)) {
            return;
        }
        $this->cache->put($key, true, self::FULL_REPEAT);
        $this->fire($server, $event, $settings, $alert, ['used' => $this->size($used), 'limit' => $this->size($limit)]);
    }

    /**
     * Sends one alert where the server wants it (the bell of its people, its Discord webhook).
     */
    private function fire(Server $server, string $event, array $settings, ?ServerAlert $alert, array $vars = []): void
    {
        $setting = match (true) {
            $event === 'crash_restart' => 'crash',
            str_starts_with($event, 'power_start'), str_starts_with($event, 'power_restart') => 'start',
            str_starts_with($event, 'power_') => 'stop',
            default => $event,
        };
        if (empty($settings[$setting])) {
            return;
        }
        $vars['name'] = $server->name;
        $kind = match ($event) {
            'crash', 'crash_restart' => 'danger',
            'memory', 'disk', 'power_kill', 'power_kill_auto' => 'warning',
            'start', 'power_start', 'power_start_auto', 'power_restart', 'power_restart_auto' => 'success',
            default => 'info',
        };
        $url = '/server/' . $server->uuidShort;

        if ($settings['bell']) {
            $this->notifier->textToServer($server, $event, $vars, $kind, $url);
        }
        $webhook = $alert?->webhookUrl();
        if ($webhook) {
            [$title, $body] = AlertTexts::get($event, Locales::default(), $vars);
            [$open] = AlertTexts::get('open', Locales::default());
            $this->notifier->discord($webhook, $title, $body, $kind, rtrim((string) config('app.url'), '/') . $url, $open);
        }
    }

    private function samplePlayers(Server $server): void
    {
        $players = null;
        $max = null;

        if ($this->fivem->isFiveM($server)) {
            $status = $this->fivem->status($server);
            $players = $status['players'];
            $max = $status['max_players'];
        } elseif ($this->gameStatus->supports($server)) {
            $status = $this->gameStatus->status($server);
            $players = $status['online'] ? $status['players'] : null;
            $max = $status['max_players'];
        } elseif ($this->isMinecraft($server)) {
            $host = $server->allocation?->ip;
            if (!$host || in_array($host, ['0.0.0.0', '::'], true)) {
                $host = $server->node->fqdn;
            }
            if ($host && $server->allocation) {
                $answer = $this->minecraft->ask($host, (int) $server->allocation->port);
                $players = $answer['players'] ?? null;
                $max = $answer['max_players'] ?? null;
            }
        }

        if ($players === null) {
            return;
        }
        DB::table('server_player_samples')->insert([
            'server_id' => $server->id,
            'players' => min(65535, max(0, (int) $players)),
            'max_players' => $max !== null ? min(65535, (int) $max) : null,
            'created_at' => now(),
        ]);
    }

    private function isMinecraft(Server $server): bool
    {
        $features = (array) ($server->egg?->inherit_features ?? $server->egg?->features ?? []);

        return in_array('eula', $features, true) || in_array('java_version', $features, true);
    }

    private function size(int $bytes): string
    {
        return $bytes >= 1073741824 ? round($bytes / 1073741824, 1) . ' GB' : round($bytes / 1048576) . ' MB';
    }
}
