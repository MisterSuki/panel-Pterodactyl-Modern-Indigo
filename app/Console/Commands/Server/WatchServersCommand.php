<?php

namespace Pterodactyl\Console\Commands\Server;

use Illuminate\Console\Command;
use Pterodactyl\Services\Servers\ServerWatcher;

class WatchServersCommand extends Command
{
    protected $signature = 'p:servers:watch';

    protected $description = 'Looks at the servers: alerts (crash, start, stop, memory, disk), player statistics and node status.';

    public function handle(ServerWatcher $watcher): int
    {
        // The players and the nodes are written every five minutes; the alerts are looked at every minute.
        $watcher->run((int) date('i') % 5 === 0);

        return self::SUCCESS;
    }
}
