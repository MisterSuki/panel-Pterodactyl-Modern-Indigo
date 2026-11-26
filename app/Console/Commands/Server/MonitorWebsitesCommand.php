<?php

namespace Pterodactyl\Console\Commands\Server;

use Illuminate\Console\Command;
use Pterodactyl\Services\WebHosting\WebsiteMonitorService;

/**
 * Checks that the websites answer (every minute, from the schedule of the panel).
 */
class MonitorWebsitesCommand extends Command
{
    protected $signature = 'p:web:monitor';

    protected $description = 'Checks that the websites answer, and sends the alerts (down, back, certificate).';

    public function handle(WebsiteMonitorService $monitor): int
    {
        $this->line('Websites checked: ' . $monitor->run());

        return self::SUCCESS;
    }
}
