<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers;

use Pterodactyl\Models\Server;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Facades\Activity;
use Pterodactyl\Models\ServerBackupPlan;
use Pterodactyl\Services\Backups\AutoBackupService;
use Pterodactyl\Http\Controllers\Api\Client\ClientApiController;
use Pterodactyl\Http\Requests\Api\Client\Servers\Backups\GetBackupPlanRequest;
use Pterodactyl\Http\Requests\Api\Client\Servers\Backups\UpdateBackupPlanRequest;

/**
 * The automatic backups of a server, seen from the Backups page.
 */
class BackupPlanController extends ClientApiController
{
    public function __construct(private AutoBackupService $service)
    {
        parent::__construct();
    }

    public function show(GetBackupPlanRequest $request, Server $server): JsonResponse
    {
        return new JsonResponse(['object' => 'backup_plan', 'attributes' => $this->present(ServerBackupPlan::forServer($server))]);
    }

    /**
     * @throws \Pterodactyl\Exceptions\DisplayException
     */
    public function update(UpdateBackupPlanRequest $request, Server $server): JsonResponse
    {
        $plan = $this->service->save($server, $request->validated());

        Activity::event('server:backup.auto-update')
            ->property(['enabled' => $plan->enabled, 'frequency' => AutoBackupService::frequencyName($plan->interval_hours), 'keep' => $plan->keep])
            ->log();

        return new JsonResponse(['object' => 'backup_plan', 'attributes' => $this->present($plan)]);
    }

    public function destroy(UpdateBackupPlanRequest $request, Server $server): JsonResponse
    {
        ServerBackupPlan::query()->where('server_id', $server->id)->delete();

        Activity::event('server:backup.auto-delete')->log();

        return new JsonResponse(['object' => 'backup_plan', 'attributes' => null]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function present(?ServerBackupPlan $plan): ?array
    {
        if ($plan === null) {
            return null;
        }

        return [
            'enabled' => $plan->enabled,
            'frequency' => AutoBackupService::frequencyName($plan->interval_hours),
            'hour' => $plan->at_hour,
            'keep' => $plan->keep,
            'ignored' => $plan->ignored,
            'next_run_at' => $plan->enabled ? $plan->next_run_at?->toIso8601String() : null,
            'last_run_at' => $plan->last_run_at?->toIso8601String(),
            'last_error' => $plan->last_error,
        ];
    }
}
