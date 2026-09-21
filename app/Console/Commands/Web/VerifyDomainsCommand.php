<?php

namespace Pterodactyl\Console\Commands\Web;

use Illuminate\Console\Command;
use Pterodactyl\Services\Web\WebHostingService;

class VerifyDomainsCommand extends Command
{
    protected $signature = 'p:web:verify-domains';

    protected $description = 'Check the domains of the web hosting that are waiting for their DNS to lead to the web server.';

    public function __construct(private WebHostingService $hosting)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $count = $this->hosting->verifyPending();
        if ($count > 0) {
            $this->info("$count domain(s) are now verified.");
        }

        return self::SUCCESS;
    }
}
