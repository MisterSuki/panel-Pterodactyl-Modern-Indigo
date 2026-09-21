<?php

namespace Pterodactyl\Services\Admin;

use Pterodactyl\Models\ActivityLog;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\User;

/**
 * What a person is doing on the panel, for the staff who help them: where they are right now and the latest things
 * they did. Both already exist: the page comes from the presence list and the actions from the activity log that the
 * panel keeps for every account. Nothing new is recorded, and nothing typed by the person is shown (no file content,
 * no console command).
 */
class UserLiveService
{
    /**
     * A person who was seen less than this many seconds ago is on the panel.
     */
    public const ACTIVE_SECONDS = OverviewService::ACTIVE_SECONDS;

    /**
     * Only what is needed to know where things stand is shown, not every detail of the log.
     */
    public const LIMIT = 12;

    /**
     * @return array<string, mixed>
     */
    public function build(User $user): array
    {
        $now = now();
        $online = $user->last_seen_at && $user->last_seen_at->gte($now->copy()->subSeconds(self::ACTIVE_SECONDS));
        $serverName = null;
        if ($online && $user->last_seen_server_id) {
            $serverName = Server::query()->without('allocation')->whereKey($user->last_seen_server_id)->value('name');
        }

        return [
            'object' => 'user_live',
            'user' => [
                'id' => $user->id,
                'username' => $user->username,
                'avatar' => $user->avatarUrl(),
            ],
            'online' => $online,
            'page' => $online ? $user->last_seen_page : null,
            'server' => $serverName,
            'seconds' => $user->last_seen_at ? max(0, $now->timestamp - $user->last_seen_at->timestamp) : null,
            'activity' => $this->recent($user),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function recent(User $user, int $limit = self::LIMIT): array
    {
        $logs = ActivityLog::query()
            ->where('actor_type', $user->getMorphClass())
            ->where('actor_id', $user->id)
            ->whereNotIn('event', ActivityLog::DISABLED_EVENTS)
            ->orderByDesc('timestamp')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        $serverIds = $logs->flatMap(fn (ActivityLog $log) => $log->subjects
            ->where('subject_type', (new Server())->getMorphClass())
            ->pluck('subject_id'))->unique()->all();
        $names = $serverIds === [] ? collect() : Server::query()->without('allocation')->whereIn('id', $serverIds)->pluck('name', 'id');

        $now = now()->timestamp;

        return $logs->map(function (ActivityLog $log) use ($names, $now) {
            $subject = $log->subjects->firstWhere('subject_type', (new Server())->getMorphClass());

            return [
                'event' => $log->event,
                'text' => $this->describe($log),
                'server' => $subject ? ($names[$subject->subject_id] ?? null) : null,
                'seconds' => max(0, $now - $log->timestamp->timestamp),
                'at' => $log->timestamp->toAtomString(),
            ];
        })->values()->all();
    }

    /**
     * The sentence of the event, from the same texts as the activity log of the dashboard. The values of the event that
     * are not plain words (lists of files, for instance) are left out.
     */
    public function describe(ActivityLog $log): string
    {
        $key = 'activity.' . str_replace(':', '.', $log->event);
        $params = [];
        foreach (($log->properties ?? collect())->all() as $name => $value) {
            if (is_scalar($value) && !is_bool($value)) {
                $params[(string) $name] = mb_substr((string) $value, 0, 80);
            }
        }

        $text = trans($key, $params);

        return is_string($text) && $text !== $key ? $text : $log->event;
    }
}
