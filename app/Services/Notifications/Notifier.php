<?php

namespace Pterodactyl\Services\Notifications;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Pterodactyl\Models\PanelNotification;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\User;

/**
 * The bell of the panel: messages for one person, for the people of a server (its owner and subusers), or for
 * everybody (announcements of the administration: maintenance, updates). Also sends the Discord messages of alerts.
 */
class Notifier
{
    // Announcements older than this are not shown any more; messages to one person are kept as long.
    public const KEEP_DAYS = 30;

    public function toUser(int $userId, string $kind, string $title, ?string $body = null, ?string $url = null): void
    {
        PanelNotification::query()->create([
            'user_id' => $userId,
            'kind' => in_array($kind, PanelNotification::KINDS, true) ? $kind : 'info',
            'title' => mb_substr($title, 0, 191),
            'body' => $body !== null ? mb_substr($body, 0, 2000) : null,
            'url' => $url,
            'created_at' => now(),
        ]);
    }

    /**
     * The owner of the server and its subusers.
     */
    public function toServer(Server $server, string $kind, string $title, ?string $body = null, ?string $url = null): void
    {
        $ids = collect([$server->owner_id])->merge($server->subusers()->pluck('user_id'))->unique();
        foreach ($ids as $id) {
            $this->toUser((int) $id, $kind, $title, $body, $url);
        }
    }

    /**
     * A message of AlertTexts to the people of a server, each in their own language.
     */
    public function textToServer(Server $server, string $key, array $vars, string $kind, ?string $url = null): void
    {
        $ids = collect([$server->owner_id])->merge($server->subusers()->pluck('user_id'))->unique();
        foreach (User::query()->whereIn('id', $ids)->get(['id', 'language']) as $user) {
            [$title, $body] = AlertTexts::get($key, $user->language, $vars);
            $this->toUser($user->id, $kind, $title, $body, $url);
        }
    }

    public function textToUser(User $user, string $key, array $vars, string $kind, ?string $url = null): void
    {
        [$title, $body] = AlertTexts::get($key, $user->language, $vars);
        $this->toUser($user->id, $kind, $title, $body, $url);
    }

    public function broadcast(string $kind, string $title, ?string $body = null, ?string $url = null, bool $public = false): PanelNotification
    {
        return PanelNotification::query()->create([
            'user_id' => null,
            'kind' => in_array($kind, PanelNotification::KINDS, true) ? $kind : 'info',
            'title' => mb_substr($title, 0, 191),
            'body' => $body !== null ? mb_substr($body, 0, 2000) : null,
            'url' => $url,
            'public' => $public,
            'created_at' => now(),
        ]);
    }

    /**
     * The latest messages of a person (theirs and the announcements made since they joined), with whether each is read.
     *
     * @return array{unread: int, data: array<int, array<string, mixed>>}
     */
    public function forUser(User $user, int $limit = 30): array
    {
        $since = max($user->created_at?->timestamp ?? 0, now()->subDays(self::KEEP_DAYS)->timestamp);
        $query = PanelNotification::query()
            ->where(fn ($q) => $q->where('user_id', $user->id)->orWhereNull('user_id'))
            ->where('created_at', '>=', date('Y-m-d H:i:s', $since));

        $read = DB::table('panel_notification_reads')->where('user_id', $user->id)
            ->whereIn('notification_id', (clone $query)->select('id'))->pluck('notification_id')->flip();
        $total = (clone $query)->count();

        $rows = $query->orderByDesc('created_at')->orderByDesc('id')->limit($limit)->get();

        return [
            'unread' => max(0, $total - $read->count()),
            'data' => $rows->map(fn (PanelNotification $n) => [
                'id' => $n->id,
                'kind' => $n->kind,
                'title' => $n->title,
                'body' => $n->body,
                'url' => $n->url,
                'everyone' => $n->user_id === null,
                'read' => $read->has($n->id),
                'created_at' => $n->created_at?->toAtomString(),
            ])->values()->all(),
        ];
    }

    /**
     * @param int[]|null $ids Null: everything the person can see.
     */
    public function markRead(User $user, ?array $ids = null): void
    {
        $query = PanelNotification::query()->where(fn ($q) => $q->where('user_id', $user->id)->orWhereNull('user_id'));
        if ($ids !== null) {
            $query->whereIn('id', $ids);
        }
        $rows = $query->pluck('id')->map(fn ($id) => ['notification_id' => $id, 'user_id' => $user->id])->all();
        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('panel_notification_reads')->insertOrIgnore($chunk);
        }
    }

    /**
     * Sends a message to a Discord webhook (an embed with a colour). Never throws: an alert that cannot be sent is
     * only written in the log.
     */
    public function discord(string $webhook, string $title, string $body, string $kind = 'info', ?string $footer = null): bool
    {
        $colors = ['success' => 0x30D158, 'warning' => 0xFF9F0A, 'danger' => 0xFF453A, 'info' => 0x0A84FF];
        try {
            $response = Http::connectTimeout(3)->timeout(5)->post($webhook, [
                'username' => mb_substr((string) config('app.name', 'Pterodactyl'), 0, 80),
                'embeds' => [[
                    'title' => mb_substr($title, 0, 250),
                    'description' => mb_substr($body, 0, 2000),
                    'color' => $colors[$kind] ?? $colors['info'],
                    'footer' => $footer ? ['text' => mb_substr($footer, 0, 200)] : null,
                    'timestamp' => now()->toIso8601String(),
                ]],
                'allowed_mentions' => ['parse' => []],
            ]);

            return $response->successful();
        } catch (\Throwable $exception) {
            Log::info('A Discord alert could not be sent.', ['error' => $exception->getMessage()]);

            return false;
        }
    }

    public function prune(): void
    {
        PanelNotification::query()->where('created_at', '<', now()->subDays(self::KEEP_DAYS + 30))->delete();
    }

    /**
     * @return Collection<int, PanelNotification>
     */
    public function publicAnnouncements(int $days = 7): Collection
    {
        return PanelNotification::query()->whereNull('user_id')->where('public', true)
            ->where('created_at', '>=', now()->subDays($days))->orderByDesc('created_at')->limit(10)->get();
    }
}
