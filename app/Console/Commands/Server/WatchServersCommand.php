<?php

namespace Pterodactyl\Console\Commands\Server;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Pterodactyl\Services\Proxmox\ProxmoxWatcher;
use Pterodactyl\Services\Servers\ServerWatcher;

class WatchServersCommand extends Command
{
    protected $signature = 'p:servers:watch';

    protected $description = 'Looks at the servers: alerts (crash, start, stop, memory, disk), player statistics and node status.';

    public function handle(ServerWatcher $watcher, ProxmoxWatcher $proxmox): int
    {
        // The players and the nodes are written every five minutes; the alerts are looked at every minute.
        $sample = (int) date('i') % 5 === 0;
        $watcher->run($sample);

        // The Proxmox nodes too, for the VPS part of the status page.
        if ($sample) {
            try {
                $proxmox->sample();
            } catch (\Throwable $exception) {
                Log::info('The Proxmox nodes could not be looked at.', ['error' => $exception->getMessage()]);
            }
        }

        return self::SUCCESS;
    }
}
