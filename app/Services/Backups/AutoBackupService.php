<?php

namespace Pterodactyl\Services\Backups;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Pterodactyl\Models\Backup;
use Pterodactyl\Models\Server;
use Illuminate\Support\Facades\Log;
use Pterodactyl\Models\ServerBackupPlan;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Exceptions\Service\Backup\TooManyBackupsException;
use Pterodactyl\Exceptions\Http\Connection\DaemonConnectionException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

/**
 * Makes a backup of a server on its own, at the interval the owner chose, and keeps only the last few of them.
 *
 * It never touches a backup that was made by hand or that is locked: the automatic backups are recognised by their
 * name (ServerBackupPlan::NAME_PREFIX), and only those are deleted to make room or to keep the last few.
 */
class AutoBackupService
{
    /**
     * The intervals that can be chosen, in hours. A day or a week starts at the hour of the day that was picked.
     */
    public const FREQUENCIES = ['6h' => 6, '12h' => 12, '24h' => 24, '7d' => 168];

    /**
     * How long to wait before trying again when the server could not be reached, or was busy.
     */
    private const RETRY_MINUTES = 15;

    public function __construct(
        private InitiateBackupService $initiateBackupService,
        private DeleteBackupService $deleteBackupService,
    ) {
    }

    /**
     * The frequency name ("24h") of an interval in hours.
     */
    public static function frequencyName(int $hours): string
    {
        return array_search($hours, self::FREQUENCIES, true) ?: '24h';
    }

    /**
     * When the first backup is due after a plan is saved.
     */
    public function firstRun(int $intervalHours, ?int $atHour, ?CarbonInterface $now = null): Carbon
    {
        $now = Carbon::instance($now ?? Carbon::now())->startOfMinute();

        if ($intervalHours < 24 || $atHour === null) {
            return $now->copy()->addHours($intervalHours);
        }

        // A day or a week begins with the next time the chosen hour comes round.
        $run = $now->copy()->setTime($atHour, 0);

        return $run->lte($now) ? $run->addDay() : $run;
    }

    /**
     * When the backup after one that ran at the given time is due.
     */
    public function followingRun(int $intervalHours, ?int $atHour, CarbonInterface $ranAt): Carbon
    {
        $ranAt = Carbon::instance($ranAt)->startOfMinute();
        $next = $ranAt->copy()->addHours($intervalHours);

        // Keep the chosen hour even after a late start or a daylight saving change.
        if ($intervalHours >= 24 && $atHour !== null) {
            $next->setTime($atHour, 0);
        }

        return $next;
    }

    /**
     * Creates or changes the plan of a server.
     *
     * @param array{enabled?: bool, frequency: string, hour?: int|null, keep: int, ignored?: string|null} $data
     *
     * @throws DisplayException
     */
    public function save(Server $server, array $data): ServerBackupPlan
    {
        if (!$server->backup_limit) {
            throw new DisplayException('Backups cannot be created for this server because its backup limit is 0.');
        }
        if (!isset(self::FREQUENCIES[$data['frequency']])) {
            throw new DisplayException('That frequency is not available.');
        }

        $hours = self::FREQUENCIES[$data['frequency']];
        $keep = max(1, min((int) $data['keep'], (int) $server->backup_limit));
        $atHour = $hours >= 24 && isset($data['hour']) ? max(0, min(23, (int) $data['hour'])) : null;
        $enabled = $data['enabled'] ?? true;

        $existing = ServerBackupPlan::forServer($server);
        $changedSchedule = !$existing || !$existing->enabled || $existing->interval_hours !== $hours || $existing->at_hour !== $atHour;

        $plan = ServerBackupPlan::query()->updateOrCreate(['server_id' => $server->id], [
            'enabled' => $enabled,
            'interval_hours' => $hours,
            'at_hour' => $atHour,
            'keep' => $keep,
            'ignored' => isset($data['ignored']) && trim($data['ignored']) !== '' ? trim($data['ignored']) : null,
            // The next backup only moves when the schedule itself was changed, so saving the number of backups to
            // keep does not push it back.
            'next_run_at' => !$enabled ? null : ($changedSchedule ? $this->firstRun($hours, $atHour) : $existing->next_run_at),
            'last_error' => $enabled ? ($existing?->last_error) : null,
        ]);

        return $plan;
    }

    /**
     * Makes the backups that are due, and cleans up after them. One server that cannot be reached does not stop the
     * others, and is tried again in a few minutes.
     *
     * @return array{started: int, failed: int}
     */
    public function runDue(?CarbonInterface $now = null, ?bool $prune = null): array
    {
        $now = Carbon::instance($now ?? Carbon::now());
        $started = $failed = 0;
        // Cleaning up looks at every plan, so it is done every ten minutes and not every minute.
        $prune ??= $now->minute % 10 === 0;

        $due = ServerBackupPlan::query()
            ->where('enabled', true)
            ->whereNotNull('next_run_at')
            ->where('next_run_at', '<=', $now)
            ->get();

        foreach ($due as $plan) {
            $server = $this->findServer($plan->server_id);
            if ($server === null) {
                $plan->delete();
                continue;
            }

            $outcome = $this->runPlan($plan, $server, $now);
            $outcome === true ? ++$started : ($outcome === false ? ++$failed : null);
        }

        // Old automatic backups are removed for every plan, not only the ones that just ran.
        foreach ($prune ? ServerBackupPlan::query()->where('enabled', true)->cursor() : [] as $plan) {
            $server = $this->findServer($plan->server_id);
            if ($server !== null) {
                try {
                    $this->prune($server, $plan);
                } catch (\Throwable $exception) {
                    Log::warning('Could not clean up the automatic backups of server ' . $server->id . ': ' . $exception->getMessage());
                }
            }
        }

        return ['started' => $started, 'failed' => $failed];
    }

    /**
     * Makes one backup. True when it was started, false when it could not be, null when the server is not in a state
     * to be backed up (suspended, installing, being moved...) and the turn is simply skipped.
     */
    public function runPlan(ServerBackupPlan $plan, Server $server, CarbonInterface $now): ?bool
    {
        $following = $this->followingRun($plan->interval_hours, $plan->at_hour, $now);

        if (!$this->canBeBackedUp($server)) {
            $plan->update(['next_run_at' => $following]);

            return null;
        }

        try {
            $this->makeRoom($server, $plan);

            $this->initiateBackupService
                ->setIsLocked(false)
                ->setIgnoredFiles($plan->ignored ? preg_split('/\R/', $plan->ignored) : [])
                ->handle($server, sprintf('%s %s', ServerBackupPlan::NAME_PREFIX, $now->format('Y-m-d H:i')));

            $plan->update(['last_run_at' => $now, 'last_error' => null, 'next_run_at' => $following]);

            return true;
        } catch (TooManyBackupsException) {
            return $this->failed($plan, 'The backup limit is reached and every backup is locked or was made by hand, so there was no room.', $following);
        } catch (TooManyRequestsHttpException) {
            return $this->failed($plan, 'A backup was made a moment ago, this one will be tried again shortly.', Carbon::instance($now)->addMinutes(self::RETRY_MINUTES));
        } catch (DaemonConnectionException) {
            return $this->failed($plan, 'The server could not be reached. It will be tried again shortly.', Carbon::instance($now)->addMinutes(self::RETRY_MINUTES));
        } catch (\Throwable $exception) {
            Log::warning('The automatic backup of server ' . $server->id . ' failed: ' . $exception->getMessage());

            return $this->failed($plan, 'The backup could not be started.', $following);
        }
    }

    /**
     * Deletes the automatic backups that are beyond the number to keep, oldest first, and the ones that failed.
     * A backup that is locked, or that was made by hand, is never touched.
     *
     * @return int how many were deleted
     */
    public function prune(Server $server, ServerBackupPlan $plan): int
    {
        $deleted = 0;

        $automatic = $this->automaticBackups($server);
        $successful = $automatic->filter(fn (Backup $b) => $b->completed_at !== null && $b->is_successful)->values();

        foreach ($successful->slice($plan->keep) as $backup) {
            if ($backup->is_locked) {
                continue;
            }
            $this->deleteBackupService->handle($backup);
            ++$deleted;
        }

        // A failed automatic backup is of no use: it is removed once it has been there for a day.
        foreach ($automatic as $backup) {
            if ($backup->completed_at !== null && !$backup->is_successful && $backup->created_at->lt(Carbon::now()->subDay())) {
                $this->deleteBackupService->handle($backup);
                ++$deleted;
            }
        }

        return $deleted;
    }

    /**
     * The automatic backups of a server, newest first.
     *
     * @return \Illuminate\Support\Collection<int, Backup>
     */
    protected function automaticBackups(Server $server): \Illuminate\Support\Collection
    {
        return Backup::query()
            ->where('server_id', $server->id)
            ->where('name', 'like', ServerBackupPlan::NAME_PREFIX . '%')
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * When the server is at its backup limit, the oldest automatic backup that is finished and not locked is deleted
     * to make room. Backups made by hand are never deleted here: if there is nothing else to delete, the limit is
     * reported instead.
     */
    protected function makeRoom(Server $server, ServerBackupPlan $plan): void
    {
        $stored = $this->countStored($server);
        if ($stored < (int) $server->backup_limit) {
            return;
        }

        $automatic = $this->automaticBackups($server)
            ->filter(fn (Backup $b) => !$b->is_locked && ($b->completed_at === null || $b->is_successful));

        // Only completed ones can be deleted, and the newest ones to keep stay.
        $deletable = $automatic->filter(fn (Backup $b) => $b->completed_at !== null)->values();
        $oldest = $deletable->last();
        if ($oldest === null) {
            throw new TooManyBackupsException((int) $server->backup_limit);
        }

        $this->deleteBackupService->handle($oldest);
    }

    protected function countStored(Server $server): int
    {
        return Backup::query()
            ->where('server_id', $server->id)
            ->where(function ($query) {
                $query->whereNull('completed_at')->orWhere('is_successful', true);
            })
            ->count();
    }

    protected function findServer(int $id): ?Server
    {
        return Server::query()->find($id);
    }

    protected function canBeBackedUp(Server $server): bool
    {
        return $server->status === null && $server->transfer === null && !$server->node->isUnderMaintenance();
    }

    private function failed(ServerBackupPlan $plan, string $message, CarbonInterface $next): bool
    {
        $plan->update(['last_error' => $message, 'next_run_at' => $next]);

        return false;
    }
}
