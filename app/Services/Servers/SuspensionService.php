<?php

namespace Pterodactyl\Services\Servers;

use Carbon\CarbonInterface;
use Webmozart\Assert\Assert;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\ServerSuspension;
use Illuminate\Support\Facades\Log;
use Pterodactyl\Repositories\Wings\DaemonServerRepository;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class SuspensionService
{
    public const ACTION_SUSPEND = 'suspend';
    public const ACTION_UNSUSPEND = 'unsuspend';

    /**
     * The longest reason that is kept.
     */
    public const MAX_REASON_LENGTH = 500;

    /**
     * SuspensionService constructor.
     */
    public function __construct(
        private DaemonServerRepository $daemonServerRepository,
    ) {
    }

    /**
     * Suspends a server on the system, or lifts the suspension.
     *
     * A suspension can carry a reason, which the owner sees, and an end date, when it lifts itself. Lifting a
     * suspension removes those details.
     *
     * @param array{reason?: string|null, until?: CarbonInterface|null, by?: int|null} $details
     *
     * @throws \Throwable
     */
    public function toggle(Server $server, string $action = self::ACTION_SUSPEND, array $details = []): void
    {
        Assert::oneOf($action, [self::ACTION_SUSPEND, self::ACTION_UNSUSPEND]);

        $isSuspending = $action === self::ACTION_SUSPEND;
        // Nothing needs to happen if we're suspending the server, and it is already
        // suspended in the database. Additionally, nothing needs to happen if the server
        // is not suspended, and we try to un-suspend the instance.
        if ($isSuspending === $server->isSuspended()) {
            return;
        }

        // Check if the server is currently being transferred.
        if (!is_null($server->transfer)) {
            throw new ConflictHttpException('Cannot toggle suspension status on a server that is currently being transferred.');
        }

        // Update the server's suspension status.
        $server->update([
            'status' => $isSuspending ? Server::STATUS_SUSPENDED : null,
        ]);

        try {
            // Tell wings to re-sync the server state.
            $this->daemonServerRepository->setServer($server)->sync();
        } catch (\Exception $exception) {
            // Rollback the server's suspension status if wings fails to sync the server.
            $server->update([
                'status' => $isSuspending ? null : Server::STATUS_SUSPENDED,
            ]);
            throw $exception;
        }

        if ($isSuspending) {
            $this->remember($server, $details);
        } else {
            ServerSuspension::query()->where('server_id', $server->id)->delete();
        }
    }

    /**
     * Changes the reason and the end date of a server that is already suspended, without touching the server.
     *
     * @param array{reason?: string|null, until?: CarbonInterface|null, by?: int|null} $details
     */
    public function updateDetails(Server $server, array $details): void
    {
        if (!$server->isSuspended()) {
            throw new ConflictHttpException('This server is not suspended.');
        }

        $this->remember($server, $details);
    }

    /**
     * Lifts every suspension whose end date has passed. One server that cannot be reached does not stop the
     * others, and is tried again the next minute.
     *
     * @return int how many servers were unsuspended
     */
    public function unsuspendExpired(): int
    {
        $count = 0;

        $expired = ServerSuspension::query()
            ->whereNotNull('suspended_until')
            ->where('suspended_until', '<=', now())
            ->get();

        foreach ($expired as $suspension) {
            $server = $this->findServer($suspension->server_id);

            // The server was unsuspended by hand in the meantime: only the leftover details go.
            if ($server === null || !$server->isSuspended()) {
                $suspension->delete();
                continue;
            }

            try {
                $this->toggle($server, self::ACTION_UNSUSPEND);
                ++$count;
            } catch (\Throwable $exception) {
                Log::warning('Could not lift the suspension of server ' . $server->id . ': ' . $exception->getMessage());
            }
        }

        return $count;
    }

    protected function findServer(int $id): ?Server
    {
        return Server::query()->find($id);
    }

    /**
     * @param array{reason?: string|null, until?: CarbonInterface|null, by?: int|null} $details
     */
    private function remember(Server $server, array $details): void
    {
        // Only what is given is written, so an update can change the reason and keep the end date.
        $values = [];
        if (array_key_exists('reason', $details)) {
            $reason = trim((string) $details['reason']);
            $values['reason'] = $reason === '' ? null : mb_substr($reason, 0, self::MAX_REASON_LENGTH);
        }
        if (array_key_exists('until', $details)) {
            $values['suspended_until'] = $details['until'];
        }
        if (array_key_exists('by', $details)) {
            $values['suspended_by'] = $details['by'];
        }

        ServerSuspension::query()->updateOrCreate(['server_id' => $server->id], $values);
    }
}
