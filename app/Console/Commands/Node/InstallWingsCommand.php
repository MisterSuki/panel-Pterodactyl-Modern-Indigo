<?php

namespace Pterodactyl\Console\Commands\Node;

use Illuminate\Console\Command;
use Pterodactyl\Models\Node;
use Pterodactyl\Services\Nodes\WingsInstaller;

class InstallWingsCommand extends Command
{
    protected $signature = 'p:nodes:install-wings {node : The ID of the node}';

    protected $description = 'Installs Wings on the machine of a node over SSH (started by the configuration page of the node).';

    public function handle(WingsInstaller $installer): int
    {
        /** @var Node|null $node */
        $node = Node::query()->find((int) $this->argument('node'));
        if (!$node) {
            $this->error('No node has this ID.');

            return self::FAILURE;
        }

        return $installer->run($node) ? self::SUCCESS : self::FAILURE;
    }
}
