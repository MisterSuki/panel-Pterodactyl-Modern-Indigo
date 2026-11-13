<?php

namespace Pterodactyl\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Pterodactyl\Models\ProxmoxVm;
use Pterodactyl\Services\Proxmox\ProxmoxProvisioner;

/**
 * One short step of the creation or deletion of a VPS on Proxmox (see ProxmoxProvisioner). While there is more to do,
 * the job puts itself back in the queue for a few seconds later, so no worker ever waits for a long copy of a disk.
 */
class ProxmoxVmJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    // The provisioner retries the steps itself; a job that failed is not run again by the worker.
    public int $tries = 1;

    public int $timeout = 60;

    public function __construct(public int $vmId)
    {
        $this->onQueue('standard');
    }

    public function handle(ProxmoxProvisioner $provisioner): void
    {
        $vm = ProxmoxVm::query()->with(['host', 'ip'])->find($this->vmId);
        if (!$vm) {
            return;
        }

        if ($provisioner->step($vm)) {
            self::dispatch($this->vmId)->delay(now()->addSeconds(5));
        }
    }
}
