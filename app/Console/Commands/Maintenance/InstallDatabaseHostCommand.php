<?php

namespace Pterodactyl\Console\Commands\Maintenance;

use Illuminate\Console\Command;
use Pterodactyl\Services\Databases\Hosts\DatabaseHostInstaller;

class InstallDatabaseHostCommand extends Command
{
    protected $signature = 'p:databases:install-host';

    protected $description = 'Makes a database host over SSH (started by the Database Hosts page of the administration).';

    public function handle(DatabaseHostInstaller $installer): int
    {
        return $installer->run() ? self::SUCCESS : self::FAILURE;
    }
}
