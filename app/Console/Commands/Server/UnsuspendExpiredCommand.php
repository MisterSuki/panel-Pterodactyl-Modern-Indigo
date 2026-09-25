<?php

namespace Pterodactyl\Console\Commands\Server;

use Illuminate\Console\Command;
use Pterodactyl\Services\Servers\SuspensionService;

class UnsuspendExpiredCommand extends Command
{
    protected $signature = 'p:server:unsuspend-expired';

    protected $description = 'Lift the suspension of every server whose suspension end date has passed.';

    public function __construct(private SuspensionService $suspensionService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $count = $this->suspensionService->unsuspendExpired();
        if ($count > 0) {
            $this->info("Unsuspended $count server(s).");
        }

        return self::SUCCESS;
    }
}
