<?php

namespace Pterodactyl\Console\Commands\Maintenance;

use Illuminate\Console\Command;
use Pterodactyl\Services\Admin\UpdateNotifier;

class CheckThemeUpdateCommand extends Command
{
    protected $signature = 'p:theme:check-update';

    protected $description = 'Tells the administrators (bell and Discord) when a new version of the theme is out.';

    public function handle(UpdateNotifier $notifier): int
    {
        $version = $notifier->check();
        $this->info($version ? 'The administrators were told about version ' . $version . '.' : 'Nothing new to tell.');

        return self::SUCCESS;
    }
}
