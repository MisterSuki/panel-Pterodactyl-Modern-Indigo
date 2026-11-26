<?php

namespace Pterodactyl\Console\Commands\Server;

use Illuminate\Console\Command;
use Pterodactyl\Services\MiniVps\MiniVpsSetup;

class MiniVpsSetupCommand extends Command
{
    protected $signature = 'p:minivps:setup';

    protected $description = 'Creates the nest of the mini VPS and its egg, or brings them up to date.';

    public function handle(MiniVpsSetup $setup): int
    {
        foreach ($setup->run() as $name) {
            $this->line('Egg ready: ' . $name);
        }

        return self::SUCCESS;
    }
}
