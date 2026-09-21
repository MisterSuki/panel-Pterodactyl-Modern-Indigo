<?php

namespace Pterodactyl\Console\Commands\Server;

use Illuminate\Console\Command;
use Pterodactyl\Services\Backups\AutoBackupService;

class RunAutoBackupsCommand extends Command
{
    protected $signature = 'p:server:run-auto-backups';

    protected $description = 'Make the automatic backups that are due, and remove the old ones.';

    public function __construct(private AutoBackupService $autoBackupService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $result = $this->autoBackupService->runDue();

        if ($result['started'] > 0 || $result['failed'] > 0) {
            $this->info(sprintf('Started %d backup(s), %d could not be started.', $result['started'], $result['failed']));
        }

        return self::SUCCESS;
    }
}
