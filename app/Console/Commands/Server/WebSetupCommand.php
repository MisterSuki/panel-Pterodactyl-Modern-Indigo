<?php

namespace Pterodactyl\Console\Commands\Server;

use Illuminate\Console\Command;
use Pterodactyl\Services\WebHosting\WebHostingSetup;

class WebSetupCommand extends Command
{
    protected $signature = 'p:web:setup';

    protected $description = 'Creates the nest of the websites and its eggs, or brings them up to date.';

    public function handle(WebHostingSetup $setup): int
    {
        foreach ($setup->run() as $name) {
            $this->line('Egg ready: ' . $name);
        }

        return self::SUCCESS;
    }
}
