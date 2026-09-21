<?php

namespace Pterodactyl\Console\Commands\Shop;

use Illuminate\Console\Command;
use Pterodactyl\Services\Shop\ShopService;

class ExpireShopOrdersCommand extends Command
{
    protected $signature = 'p:shop:expire';

    protected $description = 'Suspend the servers bought in the shop whose paid time has ended (nothing is deleted).';

    public function __construct(private ShopService $shop)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $count = $this->shop->expireDue();
        if ($count > 0) {
            $this->info("Suspended $count server(s) that are not paid for.");
        }

        return self::SUCCESS;
    }
}
