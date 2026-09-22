<?php

namespace Pterodactyl\Console\Commands\Shop;

use Illuminate\Console\Command;
use Pterodactyl\Services\Shop\ResourceBillingService;

class BillResourcesCommand extends Command
{
    protected $signature = 'p:shop:invoice {--suspend : Only suspend the servers with an overdue invoice, do not make invoices}';

    protected $description = 'On the first of the month, make each client an invoice for the resources of their servers; suspend those with an overdue invoice.';

    public function __construct(private ResourceBillingService $billing)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        if (!$this->option('suspend')) {
            // Only on the first of the month (the scheduler runs it daily, so it is cheap to check here too).
            if ((int) now()->day === 1) {
                $made = $this->billing->buildInvoices();
                if ($made > 0) {
                    $this->info("Made $made invoice(s).");
                }
            }
        }

        $suspended = $this->billing->suspendOverdue();
        if ($suspended > 0) {
            $this->info("Suspended $suspended server(s) with an overdue invoice.");
        }

        return self::SUCCESS;
    }
}
