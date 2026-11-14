<?php

namespace Pterodactyl\Console\Commands\Maintenance;

use Illuminate\Console\Command;
use Pterodactyl\Services\Databases\PhpMyAdminInstaller;

class InstallPhpMyAdminCommand extends Command
{
    protected $signature = 'p:phpmyadmin:install';

    protected $description = 'Installs phpMyAdmin over SSH (started by the Database Hosts page of the administration).';

    public function handle(PhpMyAdminInstaller $installer): int
    {
        return $installer->run() ? self::SUCCESS : self::FAILURE;
    }
}
