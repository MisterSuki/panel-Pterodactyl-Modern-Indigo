<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Http\Controllers\Api\Client\ClientApiController;
use Pterodactyl\Models\Permission;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\ServerAlert;
use Pterodactyl\Services\Helpers\Locales;
use Pterodactyl\Services\Notifications\AlertTexts;
use Pterodactyl\Services\Notifications\Notifier;

/**
 * The alerts of a server (where they go and which ones) and its player statistics.
 */
class ServerInsightsController extends ClientApiController
{
    public function __construct(private Notifier $notifier)
    {
        parent::__construct();
    }

    public function alerts(Request $request, Server $server): JsonResponse
    {
        abort_unless($request->user()->can(Permission::ACTION_SETTINGS_RENAME, $server), 403);

        return new JsonResponse($this->alertPayload($server));
    }

    public function saveAlerts(Request $request, Server $server): JsonResponse
    {
        abort_unless($request->user()->can(Permission::ACTION_SETTINGS_RENAME, $server), 403);
        $data = $request->validate([
            'webhook' => ['nullable', 'string', 'max:300'],
            'keep_webhook' => ['sometimes', 'boolean'],
            'events' => ['required', 'array'],
        ]);

        $alert = ServerAlert::query()->firstOrNew(['server_id' => $server->id]);
        $webhook = trim((string) ($data['webhook'] ?? ''));
        if ($webhook !== '') {
            if (!ServerAlert::validWebhook($webhook)) {
                throw new DisplayException('This is not a Discord webhook address (https://discord.com/api/webhooks/…).');
            }
            $alert->setWebhookUrl($webhook);
        } elseif (!$request->boolean('keep_webhook')) {
            $alert->setWebhookUrl(null);
        }
        $events = [];
        foreach (array_keys(ServerAlert::DEFAULTS) as $key) {
            $events[$key] = (bool) ($data['events'][$key] ?? false);
        }
        $alert->events = json_encode($events);
        $alert->save();

        return new JsonResponse($this->alertPayload($server));
    }

    public function testAlert(Request $request, Server $server): JsonResponse
    {
        abort_unless($request->user()->can(Permission::ACTION_SETTINGS_RENAME, $server), 403);
        $webhook = ServerAlert::query()->where('server_id', $server->id)->first()?->webhookUrl();
        if (!$webhook) {
            throw new DisplayException('Save a Discord webhook first.');
        }
        [$title, $body] = AlertTexts::get('test', Locales::default(), ['name' => $server->name]);
        [$open] = AlertTexts::get('open', Locales::default());
        $link = rtrim((string) config('app.url'), '/') . '/server/' . $server->uuidShort;
        if (!$this->notifier->discord($webhook, $title, $body, 'success', $link, $open)) {
            throw new DisplayException('Discord did not accept the message. Check the webhook address.');
        }

        return new JsonResponse(['sent' => true]);
    }

    /**
     * How many players were on the server: the curve of the last day or week, the peak, the average, and how busy
     * each hour of the day is (from the last week).
     */
    public function players(Request $request, Server $server): JsonResponse
    {
        abort_unless($request->user()->can(Permission::ACTION_WEBSOCKET_CONNECT, $server), 403);
        $range = $request->query('range') === '7d' ? '7d' : '24h';
        $since = $range === '7d' ? now()->subDays(7) : now()->subDay();
        $bucket = $range === '7d' ? 7200 : 900;

        $rows = DB::table('server_player_samples')->where('server_id', $server->id)
            ->where('created_at', '>=', $since)->orderBy('created_at')->get(['players', 'max_players', 'created_at']);

        $buckets = [];
        foreach ($rows as $row) {
            $at = strtotime((string) $row->created_at);
            $key = intdiv($at, $bucket) * $bucket;
            $buckets[$key][] = (int) $row->players;
        }
        $points = [];
        for ($t = intdiv($since->timestamp, $bucket) * $bucket; $t <= time(); $t += $bucket) {
            $values = $buckets[$t] ?? null;
            $points[] = ['at' => date(DATE_ATOM, $t), 'players' => $values ? round(array_sum($values) / count($values), 1) : null, 'peak' => $values ? max($values) : null];
        }

        $week = DB::table('server_player_samples')->where('server_id', $server->id)
            ->where('created_at', '>=', now()->subDays(7))->get(['players', 'created_at']);
        $hours = array_fill(0, 24, []);
        foreach ($week as $row) {
            $hours[(int) date('G', strtotime((string) $row->created_at))][] = (int) $row->players;
        }

        $values = $rows->pluck('players')->map(fn ($v) => (int) $v);
        $max = $rows->whereNotNull('max_players')->last()?->max_players;

        return new JsonResponse([
            'range' => $range,
            'samples' => $rows->count(),
            'peak' => $values->max() ?? 0,
            'average' => $values->count() ? round($values->avg(), 1) : 0,
            'max_players' => $max !== null ? (int) $max : null,
            'points' => $points,
            'hours' => array_map(fn ($list) => $list ? round(array_sum($list) / count($list), 1) : null, $hours),
            'timezone' => date_default_timezone_get(),
        ]);
    }

    private function alertPayload(Server $server): array
    {
        $alert = ServerAlert::query()->where('server_id', $server->id)->first() ?? new ServerAlert();
        $webhook = $alert->webhookUrl();

        return [
            'events' => $alert->settings(),
            // Only a hint of the webhook is given back: it is a secret (anyone with it can post in the channel).
            'webhook_set' => $webhook !== null,
            'webhook_hint' => $webhook ? substr($webhook, 0, 40) . '…' : null,
        ];
    }
}
